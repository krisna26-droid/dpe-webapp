<?php

namespace App\Services;

use App\Models\MonthlyReport;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OperationsMonitoringService
{
    public function __construct(private OperationsAccessService $access, private StudentProgressService $progress,
        private PortalPaymentService $payments, private StudentPortalService $portal) {}

    public function videoState(MonthlyReport $report): array
    {
        // Approved content keeps the target and video list that were reviewed.
        if ($report->status === 'approved') {
            $snapshot = $report->approved_snapshot;
            $public = is_array($snapshot) && is_array($snapshot['public_content'] ?? null) ? $snapshot['public_content'] : null;
            if (! $public || ! is_array($public['videos'] ?? null) || ! is_numeric($public['video_target'] ?? null)) {
                return ['target' => null, 'count' => null, 'missing' => null, 'label' => 'Salinan persetujuan perlu diperiksa', 'fingerprint' => 'legacy'];
            }
            $target = max(0, (int) $public['video_target']);
            $count = count($public['videos']);
            $fingerprint = hash('sha256', json_encode([$target, $public['videos']], JSON_THROW_ON_ERROR));
        } else {
            $videos = $this->progress->videoRows($report)->filter(fn ($video) => $this->progress->validVideo($report, $video));
            $target = $this->progress->targetReady($report) ? $report->video_target : null;
            $count = $videos->count();
            $fingerprint = hash('sha256', json_encode([$target, $videos->map->getAttributes()->all()], JSON_THROW_ON_ERROR));
        }
        $missing = $target === null ? null : max(0, $target - $count);

        return ['target' => $target, 'count' => $count, 'missing' => $missing,
            'label' => $target === null ? 'Target belum ditetapkan Admin' : $count.' / '.$target.' video'.($missing ? ' · kurang '.$missing : ' · lengkap'),
            'fingerprint' => $fingerprint];
    }

    public function deadline(MonthlyReport $report): ?string
    {
        return $this->dueState($report->due_on?->toDateString(), $report->status === 'approved', $report->branch?->timezone_name);
    }

    public function dueState(?string $due, bool $approved, ?string $zone): ?string
    {
        if ($approved || ! $due) {
            return null;
        }
        $today = now($this->access->timezone($zone))->toDateString();
        if ($due < $today) {
            return 'overdue';
        }

        return $due <= Carbon::parse($today)->addDays(3)->toDateString() ? 'near' : null;
    }

    public function data(User $actor, array $input = [], bool $includeFinal = true): array
    {
        $actor = $this->access->actor($actor);
        abort_if($actor->role_code === 'student', 403);
        $filters = array_replace(['month' => now()->format('Y-m'), 'branch_id' => null, 'teacher_id' => null, 'q' => null,
            'status' => 'all', 'final_status' => 'all'], array_filter($input, fn ($value) => $value !== null && $value !== ''));
        $branches = $this->access->branches($actor);
        abort_if($filters['branch_id'] && ! $branches->contains('id', $filters['branch_id']), 403);
        $start = $filters['month'].'-01';
        $monthly = $this->access->monthly($actor)->whereDate('report_month', $start);
        $quarterly = $this->access->quarterly($actor)->whereHas('cycle', fn ($cycle) => $cycle
            ->whereDate('start_month', '<=', $start)->whereDate('end_month', '>=', $start));
        if ($filters['branch_id']) {
            $monthly->where('branch_id', $filters['branch_id']);
            $quarterly->where('branch_id', $filters['branch_id']);
        }
        $teacherIds = (clone $monthly)->pluck('teacher_id')->merge((clone $quarterly)->pluck('teacher_id'))->unique();
        $teachers = Teacher::with('user')->whereIn('id', $teacherIds)->get()->sortBy('user.full_name')->values();
        abort_if($filters['teacher_id'] && ! $teacherIds->contains($filters['teacher_id']), 403);
        foreach ([$monthly, $quarterly] as $query) {
            if ($filters['teacher_id']) {
                $query->where('teacher_id', $filters['teacher_id']);
            }
            if ($filters['q']) {
                $query->whereHas('student', fn ($student) => $student->where('full_name', 'like', '%'.$filters['q'].'%'));
            }
        }
        if ($filters['status'] !== 'all') {
            $monthly->where('status', $filters['status']);
        }
        if ($filters['final_status'] !== 'all') {
            $quarterly->where('status', $filters['final_status']);
        }
        $rows = $monthly->with(['student', 'branch', 'teacher.user'])->orderBy('due_on')->orderBy('id')->get()
            ->map(fn ($report) => ['report' => $report, 'path' => $this->access->reportPath($actor, $report), 'video' => $this->videoState($report),
                'deadline' => $this->deadline($report), 'paid' => $this->payments->paid($report->student_id, $filters['month'])]);
        $finalRows = $includeFinal ? $quarterly->with(['student', 'branch', 'teacher.user', 'cycle'])->orderBy('id')->get()
            ->map(fn ($report) => ['report' => $report, 'path' => $this->access->reportPath($actor, $report), 'portal' => $this->portal->quarterlyState($report),
                'deadline' => $this->dueState($report->cycle?->share_due_on?->toDateString(), $report->status === 'approved', $report->branch?->timezone_name)]) : collect();
        $summary = $this->summary($rows);
        $teacherSummary = $rows->groupBy('report.teacher_id')->map(fn ($items) => ['teacher' => $items->first()['report']->teacher,
            'summary' => $this->summary($items)])->sortBy('teacher.user.full_name')->values();

        return compact('actor', 'filters', 'branches', 'teachers', 'rows', 'finalRows', 'summary', 'teacherSummary')
            + ['monthLabel' => Carbon::parse($start)->locale('id')->translatedFormat('F Y')];
    }

    private function summary(Collection $rows): array
    {
        return ['total' => $rows->count(), 'approved' => $rows->where('report.status', 'approved')->count(),
            'draft' => $rows->where('report.status', 'draft')->count(), 'submitted' => $rows->where('report.status', 'submitted')->count(),
            'revision' => $rows->where('report.status', 'revision')->count(), 'pending' => $rows->where('report.status', '!=', 'approved')->count(),
            'overdue' => $rows->where('deadline', 'overdue')->count(), 'video_missing' => $rows->filter(fn ($row) => $row['video']['missing'] === null || $row['video']['missing'] > 0)->count(),
            'unpaid' => $rows->where('paid', false)->count()];
    }
}
