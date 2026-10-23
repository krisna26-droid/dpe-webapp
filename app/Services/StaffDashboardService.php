<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\StudentEnrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class StaffDashboardService
{
    public function __construct(private OperationsAccessService $access, private OperationsMonitoringService $monitor,
        private StudentProgressAccessService $progress, private StudentAttendanceAccessService $attendance) {}

    public function data(User $actor, array $filters = []): array
    {
        $actor = $this->access->actor($actor);
        $monitoring = $this->monitor->data($actor, [], false);
        $todaySessions = $this->attendance->sessions($actor)->with(['branch', 'teacher.user'])
            ->whereDate('planned_start_at', today())->where('status', '!=', 'cancelled')->notSuperseded()->orderBy('planned_start_at')->get();

        $data = compact('actor', 'monitoring', 'todaySessions') + ['activeBranches' => Branch::where('is_active', true)->count(),
            'activeUsers' => $actor->role_code === 'superadmin' ? User::where('is_active', true)->count() : null,
            'myStudents' => $actor->role_code === 'teacher' ? $this->progress->students($actor, CarbonImmutable::now()->startOfMonth())->where('status', 'active')->count() : null];

        if ($actor->role_code === 'superadmin') {
            // The period selector belongs to the new panel; existing "bulan ini" metrics retain their current-month scope.
            $overviewMonitoring = ! empty($filters['month']) && $filters['month'] !== $monitoring['filters']['month']
                ? $this->monitor->data($actor, ['month' => $filters['month']], false) : $monitoring;
            $data['dashboardOverview'] = $this->superAdminOverview($overviewMonitoring);
        }

        return $data;
    }

    private function superAdminOverview(array $monitoring): array
    {
        $attention = $monitoring['rows']->where('report.status', '!=', 'approved')
            ->groupBy(fn (array $row) => $row['report']->teacher_id.'|'.$row['report']->branch_id)
            ->map(function (Collection $rows): array {
                $report = $rows->first()['report'];

                return ['teacher_id' => $report->teacher_id, 'branch_id' => $report->branch_id,
                    'teacher_name' => $report->teacher?->user?->full_name ?: $report->teacher?->user?->username ?: 'Teacher belum tersedia',
                    'branch_name' => $report->branch?->name ?: 'Cabang belum tersedia',
                    'pending' => $rows->count(), 'overdue' => $rows->where('deadline', 'overdue')->count()];
            })
            ->sortBy([['overdue', 'desc'], ['pending', 'desc'], ['teacher_name', 'asc'], ['branch_name', 'asc']]);

        $today = CarbonImmutable::today()->toDateString();
        $branches = $monitoring['branches']->where('is_active', true);
        $studentCounts = StudentEnrollment::query()->whereIn('branch_id', $branches->pluck('id'))
            ->whereDate('starts_on', '<=', $today)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
            ->whereHas('student', fn ($query) => $query->where('status', 'active')->whereDate('began_on', '<=', $today))
            ->select('branch_id')->selectRaw('COUNT(DISTINCT student_id) AS student_count')
            ->groupBy('branch_id')->pluck('student_count', 'branch_id');
        $branchRows = $branches->map(fn (Branch $branch) => ['id' => $branch->id, 'name' => $branch->name,
            'students' => (int) ($studentCounts[$branch->id] ?? 0)])
            ->sortBy([['students', 'desc'], ['name', 'asc']])->values();

        return ['month' => $monitoring['filters']['month'], 'month_label' => $monitoring['monthLabel'],
            'summary' => $monitoring['summary'], 'teacher_attention' => $attention->take(3)->values(),
            'teacher_attention_total' => $attention->count(), 'branches' => $branchRows,
            'branch_max' => max(1, (int) $branchRows->max('students')), 'branch_as_of' => CarbonImmutable::today()->format('d/m/Y')];
    }
}
