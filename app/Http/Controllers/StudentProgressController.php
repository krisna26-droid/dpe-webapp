<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\LearningVideo;
use App\Models\MonthlyProgressAudit;
use App\Models\MonthlyReport;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\StudentProgressAccessService;
use App\Services\StudentProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentProgressController extends Controller
{
    public function __construct(private StudentProgressService $progress, private StudentProgressAccessService $access) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'q' => ['nullable', 'string', 'max:100'], 'branch_id' => ['nullable', 'uuid']]);
        $filters['month'] = $filters['month'] ?? now()->format('Y-m');
        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month']);
        $query = $this->access->students($request->user(), $month);
        $teacherStudentIds = $request->user()->role_code === 'teacher' ? (clone $query)->select('students.id') : null;
        $reports = fn ($q) => $q->whereDate('report_month', $month->toDateString())
            ->whereIn('id', $this->access->reports($request->user())->select('id'))->with(['branch', 'teacher.user', 'reportSkills']);
        $query->with(['monthlyReports' => $reports, 'enrollments' => function ($q) use ($request, $month) {
            $q->whereDate('starts_on', '<=', $month->endOfMonth()->toDateString())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month->toDateString()))->with('branch');
            if ($request->user()->role_code === 'admin') {
                $q->whereIn('branch_id', $this->access->branchIds($request->user()));
            }
        }]);
        if (($filters['q'] ?? '') !== '') {
            $query->where('full_name', 'like', '%'.$filters['q'].'%');
        }
        if (! empty($filters['branch_id'])) {
            $query->where(fn ($q) => $q->whereHas('monthlyReports', fn ($r) => $r->whereDate('report_month', $month->toDateString())->where('branch_id', $filters['branch_id'])
                ->whereIn('id', $this->access->reports($request->user())->select('id')))
                ->orWhereHas('enrollments', fn ($e) => $e->where('branch_id', $filters['branch_id'])->whereDate('starts_on', '<=', $month->endOfMonth()->toDateString())
                    ->where(fn ($e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month->toDateString()))));
        }
        $students = $query->orderBy('full_name')->orderBy('id')->paginate(15)->withQueryString();
        $otherOwnerStudents = $request->user()->role_code === 'teacher'
            ? MonthlyReport::query()->whereIn('student_id', $students->getCollection()->pluck('id'))->whereDate('report_month', $month->toDateString())
                ->whereNotIn('id', $this->access->reports($request->user())->select('id'))->pluck('student_id')->flip()
            : collect();
        $categories = $this->progress->categories();
        $rows = $students->getCollection()->map(function ($student) use ($categories, $otherOwnerStudents) {
            $report = $student->monthlyReports->first();
            $completed = $report ? $categories->filter(fn ($s) => $s && $report->reportSkills->contains(fn ($r) => $r->skill_id === $s->id
                && in_array($r->trend, StudentProgressService::TRENDS, true) && filled($r->description)))->count() : 0;

            return ['student' => $student, 'report' => $report, 'completed' => $completed, 'otherReportOwner' => $otherOwnerStudents->has($student->id),
                'videoCount' => $report ? $this->progress->videoRows($report)->filter(fn ($v) => $this->progress->validVideo($report, $v))->count() : 0,
                'targetReady' => $report ? $this->progress->targetReady($report) : false];
        });
        $branches = Branch::query()->where(function ($q) use ($request, $month, $teacherStudentIds) {
            if ($request->user()->role_code === 'admin') {
                $q->whereIn('id', $this->access->branchIds($request->user()));
            } elseif ($request->user()->role_code === 'teacher') {
                $q->whereIn('id', $this->access->reports($request->user())->whereDate('report_month', $month->toDateString())->select('branch_id'))
                    ->orWhereIn('id', StudentEnrollment::query()->select('branch_id')->whereIn('student_id', $teacherStudentIds)
                        ->whereDate('starts_on', '<=', $month->endOfMonth()->toDateString())
                        ->where(fn ($e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month->toDateString())));
            }
        })->orderBy('name')->get();

        return view('student-progress.index', compact('students', 'rows', 'filters', 'month', 'branches') + $this->common($request));
    }

    public function preparation(Request $request, Student $student): View|RedirectResponse
    {
        $month = $this->month($request);
        abort_unless($this->access->canManage($request->user()), 403);
        $existing = MonthlyReport::where('student_id', $student->id)->whereDate('report_month', $month->toDateString())->first();
        if ($existing) {
            $this->access->authorize($request->user(), $existing);

            return redirect()->route($this->base($request).'.edit', $existing);
        }
        try {
            $context = $this->progress->preparationContext($request->user(), $student, $month);
        } catch (ValidationException $error) {
            return redirect()->route($this->base($request).'.index', ['month' => $month->format('Y-m')])->withErrors($error->errors());
        }

        return view('student-progress.prepare', compact('student', 'month') + $context + $this->common($request));
    }

    public function prepare(Request $request, Student $student): RedirectResponse
    {
        $month = $this->month($request);
        $report = $this->progress->prepare($request->user(), $student, $month, $request->only('teacher_id', 'video_target'));

        return redirect()->route($this->base($request).'.edit', $report)->with('success', 'Periode siswa siap dilengkapi.');
    }

    public function edit(Request $request, MonthlyReport $monthlyReport): View
    {
        $this->access->authorize($request->user(), $monthlyReport);
        $monthlyReport->load(['student', 'branch', 'teacher.user', 'cycle', 'reportSkills']);
        $categories = $this->progress->categories();
        $videos = $this->progress->videoRows($monthlyReport);
        $videoCount = $videos->filter(fn ($v) => $this->progress->validVideo($monthlyReport, $v))->count();
        $completed = $categories->filter(fn ($s) => $s && $monthlyReport->reportSkills->contains(fn ($r) => $r->skill_id === $s->id
            && in_array($r->trend, StudentProgressService::TRENDS, true) && filled($r->description)))->count();
        $history = MonthlyProgressAudit::where('report_id', $monthlyReport->id)->with('actor')->orderByDesc('id')
            ->paginate(10, ['*'], 'history_page')->withQueryString()->fragment('progress-history');

        return view('student-progress.edit', compact('monthlyReport', 'categories', 'videos', 'videoCount', 'completed', 'history')
            + ['targetReady' => $this->progress->targetReady($monthlyReport), 'expectedSnapshot' => $this->progress->signature($monthlyReport),
                'blockReason' => $this->progress->blockReason($monthlyReport), 'progress' => $this->progress] + $this->common($request));
    }

    public function save(Request $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        [$destination, $parameters] = $this->saveDestination($request, $monthlyReport);
        $changed = $this->progress->save($request->user(), $monthlyReport, $request->all());

        return redirect()->route($destination, $parameters)->with('success', $changed ? 'Draf perkembangan dan pesan berhasil disimpan.' : 'Data sama dengan isian yang sudah tersimpan.');
    }

    public function target(Request $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        $this->progress->setTarget($request->user(), $monthlyReport, $request->only('video_target', 'expected_snapshot'));

        return redirect()->route($this->base($request).'.edit', $monthlyReport)->with('success', 'Target video periode ini berhasil disimpan.');
    }

    public function videoCreate(Request $request, MonthlyReport $monthlyReport): View
    {
        return $this->videoForm($request, $monthlyReport);
    }

    public function videoEdit(Request $request, MonthlyReport $monthlyReport, LearningVideo $learningVideo): View
    {
        return $this->videoForm($request, $monthlyReport, $learningVideo);
    }

    public function videoConfirm(Request $request, MonthlyReport $monthlyReport, LearningVideo $learningVideo): View
    {
        return $this->videoForm($request, $monthlyReport, $learningVideo, true);
    }

    public function videoStore(Request $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        $this->progress->saveVideo($request->user(), $monthlyReport, $request->all());

        return $this->videoRedirect($request, $monthlyReport, 'Tautan video berhasil disimpan.');
    }

    public function videoUpdate(Request $request, MonthlyReport $monthlyReport, LearningVideo $learningVideo): RedirectResponse
    {
        $this->progress->saveVideo($request->user(), $monthlyReport, $request->all(), $learningVideo);

        return $this->videoRedirect($request, $monthlyReport, 'Tautan video berhasil diperbarui.');
    }

    public function videoDelete(Request $request, MonthlyReport $monthlyReport, LearningVideo $learningVideo): RedirectResponse
    {
        $this->progress->deleteVideo($request->user(), $monthlyReport, $learningVideo, $request->only('expected_snapshot'));

        return $this->videoRedirect($request, $monthlyReport, 'Tautan video dihapus dari periode siswa ini.');
    }

    private function videoForm(Request $request, MonthlyReport $monthlyReport, ?LearningVideo $learningVideo = null, bool $confirmDelete = false): View
    {
        $this->access->authorize($request->user(), $monthlyReport);
        $monthlyReport->load(['student', 'branch', 'teacher.user']);
        if ($learningVideo) {
            abort_unless($this->progress->canEditVideo($request->user(), $monthlyReport, $learningVideo), 403);
        }

        return view('student-progress.video', compact('monthlyReport', 'learningVideo', 'confirmDelete')
            + ['sessions' => $this->progress->sessions($request->user(), $monthlyReport), 'blockReason' => $this->progress->blockReason($monthlyReport),
                'expectedSnapshot' => $this->progress->signature($monthlyReport),
                'minDate' => max($monthlyReport->report_month->toDateString(), $monthlyReport->student->began_on->toDateString()),
                'maxDate' => min($monthlyReport->report_month->copy()->endOfMonth()->toDateString(), $this->progress->today($monthlyReport))] + $this->common($request));
    }

    private function videoRedirect(Request $request, MonthlyReport $report, string $message): RedirectResponse
    {
        return redirect()->to(route($this->base($request).'.edit', $report).'#learning-videos')->with('success', $message);
    }

    private function saveDestination(Request $request, MonthlyReport $report): array
    {
        $next = $request->validate(['next' => ['sometimes', 'nullable', 'string', 'max:80']])['next'] ?? null;
        $base = $this->base($request);
        if ($next === 'monthly_report') {
            return [$request->user()->role_code.'.monthly-reports.show', [$report]];
        }
        if ($next === 'video_create') {
            return [$base.'.videos.create', [$report]];
        }
        if (str_starts_with($next ?? '', 'video_')) {
            $parts = explode(':', $next, 2);
            $routes = ['video_edit' => '.videos.edit', 'video_remove' => '.videos.confirm'];
            if (count($parts) !== 2 || ! isset($routes[$parts[0]]) || ! Str::isUuid($parts[1])) {
                throw ValidationException::withMessages(['next' => 'Tujuan pengelolaan video tidak valid. Muat ulang halaman perkembangan siswa.']);
            }
            $this->access->authorize($request->user(), $report);
            $video = LearningVideo::whereKey($parts[1])->firstOrFail();
            abort_unless($this->progress->canEditVideo($request->user(), $report, $video), 403);

            return [$base.$routes[$parts[0]], [$report, $video]];
        }

        return [$base.'.edit', [$report]];
    }

    private function month(Request $request): CarbonImmutable
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);

        return CarbonImmutable::createFromFormat('!Y-m', $data['month']);
    }

    private function base(Request $request): string
    {
        return explode('.', $request->route()->getName())[0].'.student-progress';
    }

    private function common(Request $request): array
    {
        return ['routeBase' => $this->base($request), 'canManage' => $this->access->canManage($request->user()), 'monthlyReportBase' => $request->user()->role_code.'.monthly-reports'];
    }
}
