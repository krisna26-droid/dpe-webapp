<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordStudentAttendanceRequest;
use App\Http\Requests\StudentAttendanceIndexRequest;
use App\Http\Requests\StudentAttendanceRosterRequest;
use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\StudentAttendanceAudit;
use App\Services\StudentAttendanceAccessService;
use App\Services\StudentAttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentAttendanceController extends Controller
{
    public function __construct(private StudentAttendanceService $attendance, private StudentAttendanceAccessService $access) {}

    public function index(StudentAttendanceIndexRequest $request): View
    {
        $actor = $request->user();
        $filters = $request->validated();
        $period = Carbon::createFromFormat('!Y-m', $filters['period'] ?? now()->format('Y-m'));
        $months = collect(range(0, 2))->map(fn ($offset) => $period->copy()->addMonths($offset));
        $month = Carbon::createFromFormat('!Y-m', $filters['month'] ?? $period->format('Y-m'));
        if (! $months->contains(fn ($date) => $date->format('Y-m') === $month->format('Y-m'))) {
            throw ValidationException::withMessages(['month' => 'Pilih bulan dalam rentang tiga bulan yang ditampilkan.']);
        }
        $visible = $this->access->sessions($actor);
        $branches = Branch::query()->whereIn('id', (clone $visible)->select('branch_id'))->orderBy('name')->get();
        if (! empty($filters['branch_id'])) {
            abort_unless($branches->contains('id', $filters['branch_id']), 403);
            $visible->where('branch_id', $filters['branch_id']);
        }
        $groups = ClassGroup::query()->with('branch')->whereIn('id', (clone $visible)->select('class_group_id'))->orderBy('name')->get();
        $groupId = $filters['class_group_id'] ?? ($groups->first()?->id ?? 'private');
        if ($groupId !== 'private') {
            abort_unless($groups->contains('id', $groupId), 403);
            $visible->where('class_group_id', $groupId);
        } else {
            $visible->whereNull('class_group_id');
        }
        $visible->whereRaw('COALESCE(actual_start_at, planned_start_at) >= ?', [$month->toDateTimeString()])
            ->whereRaw('COALESCE(actual_start_at, planned_start_at) < ?', [$month->copy()->addMonth()->toDateTimeString()]);
        $sessions = (clone $visible)->with($this->relations())->orderByRaw('COALESCE(actual_start_at, planned_start_at)')->orderBy('id')->get();
        $students = $sessions->flatMap(fn ($s) => $this->attendance->roster($s));
        if ($groupId !== 'private') {
            $group = $groups->firstWhere('id', $groupId);
            $group->load('memberships.student.enrollments');
            foreach ($group->memberships as $membership) {
                if ($membership->student && $membership->starts_on->toDateString() <= $month->copy()->endOfMonth()->toDateString()
                    && ($membership->ends_on === null || $membership->ends_on->toDateString() >= $month->toDateString())) {
                    $students->push($membership->student);
                }
            }
        }
        $studentOptions = $students->unique('id')->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        if (! empty($filters['student_id'])) {
            abort_unless($studentOptions->contains('id', $filters['student_id']), 403);
            $students = $studentOptions->where('id', $filters['student_id']);
            if ($groupId === 'private') {
                $sessions = $sessions->filter(fn ($s) => $this->attendance->roster($s)->contains('id', $filters['student_id']))->values();
                $visible->whereHas('sessionStudents', fn ($q) => $q->where('student_id', $filters['student_id']));
            }
        } else {
            $students = $studentOptions;
        }
        if (($filters['q'] ?? '') !== '') {
            $needle = mb_strtolower($filters['q']);
            $students = $students->filter(fn ($student) => str_contains(mb_strtolower($student->full_name), $needle));
        }
        $rows = $students->map(function ($student) use ($sessions) {
            $cells = $sessions->mapWithKeys(fn ($session) => [$session->id => $this->attendance->cell($session, $student)]);

            return ['student' => $student, 'cells' => $cells, 'summary' => $this->summarize($cells)];
        });
        $filters = array_merge($filters, ['period' => $period->format('Y-m'), 'month' => $month->format('Y-m'), 'class_group_id' => $groupId]);

        return view('student-attendance.index', [
            'filters' => $filters, 'period' => $period, 'months' => $months, 'month' => $month,
            'branches' => $branches, 'groups' => $groups, 'studentOptions' => $studentOptions,
            'sessions' => $sessions, 'rows' => $rows, 'summary' => $this->summarize($rows->flatMap(fn ($row) => $row['cells']->values()->all())),
            'statuses' => StudentAttendanceService::STATUSES, 'routeBase' => $this->routeBase($request),
            'schedule' => (clone $visible)->with(['branch', 'teacher.user', 'program', 'classGroup'])->orderByRaw('COALESCE(actual_start_at, planned_start_at)')->paginate(15)->withQueryString(),
        ]);
    }

    public function show(Request $request, LessonSession $lessonSession): View
    {
        $this->access->authorize($request->user(), $lessonSession);
        $lessonSession->load($this->relations());
        $students = $this->attendance->roster($lessonSession);
        $cells = $students->mapWithKeys(fn ($s) => [$s->id => $this->attendance->cell($lessonSession, $s)]);
        $canManageRoster = $this->access->canManageRoster($request->user()) && $lessonSession->class_group_id === null;
        $candidates = collect();
        if ($canManageRoster) {
            $date = $this->attendance->meetingAt($lessonSession)->toDateString();
            $candidates = Student::query()->whereDate('began_on', '<=', $date)
                ->whereHas('enrollments', fn ($q) => $q->where('branch_id', $lessonSession->branch_id)
                    ->where('program_id', $lessonSession->program_id)->whereDate('starts_on', '<=', $date)
                    ->where(fn ($e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date)))
                ->orderBy('full_name')->get()->merge($students)->unique('id')->sortBy('full_name')->values();
        }

        return view('student-attendance.show', [
            'lessonSession' => $lessonSession, 'students' => $students, 'cells' => $cells,
            'meetingAt' => $this->attendance->meetingAt($lessonSession), 'canManageRoster' => $canManageRoster,
            'isSuperseded' => $this->attendance->isSuperseded($lessonSession),
            'candidates' => $candidates, 'rosterSignature' => $this->attendance->rosterSignature($lessonSession),
            'routeBase' => $this->routeBase($request), 'statuses' => StudentAttendanceService::STATUSES,
        ]);
    }

    public function edit(Request $request, LessonSession $lessonSession, Student $student): View
    {
        $this->access->authorize($request->user(), $lessonSession);
        $lessonSession->load($this->relations());
        abort_unless($this->attendance->roster($lessonSession)->contains('id', $student->id), 403);

        return view('student-attendance.edit', [
            'lessonSession' => $lessonSession, 'student' => $student,
            'cell' => $this->attendance->cell($lessonSession, $student),
            'history' => StudentAttendanceAudit::query()->with('actor')->where('session_id', $lessonSession->id)->where('student_id', $student->id)->orderByDesc('id')->get(),
            'routeBase' => $this->routeBase($request), 'statuses' => StudentAttendanceService::STATUSES,
        ]);
    }

    public function save(RecordStudentAttendanceRequest $request, LessonSession $lessonSession, Student $student): RedirectResponse|JsonResponse
    {
        try {
            $this->attendance->record($request->user(), $lessonSession, $student, $request->validated());
        } catch (ValidationException $exception) {
            if ($request->expectsJson() && isset($exception->errors()['expected_version'])) {
                return response()->json([
                    'message' => 'Catatan sudah berubah. Periksa status terbaru dan buka konfirmasi kembali.',
                    'errors' => $exception->errors(),
                    'cell' => $this->responseCell($request, $lessonSession, $student),
                ], 422);
            }
            throw $exception;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Kehadiran siswa berhasil disimpan.',
                'cell' => $this->responseCell($request, $lessonSession, $student),
            ]);
        }

        return back()->with('success', 'Kehadiran siswa berhasil disimpan.');
    }

    public function roster(StudentAttendanceRosterRequest $request, LessonSession $lessonSession): RedirectResponse
    {
        $this->attendance->setRoster($request->user(), $lessonSession, $request->validated('student_ids', []), $request->validated('expected_roster'));

        return redirect()->route($this->routeBase($request).'.show', $lessonSession)->with('success', 'Daftar peserta berhasil disimpan.');
    }

    private function summarize(Collection $cells): array
    {
        return $this->attendance->summarize($cells);
    }

    private function responseCell(Request $request, LessonSession $lessonSession, Student $student): array
    {
        $lessonSession->refresh()->load($this->relations());
        $this->access->authorize($request->user(), $lessonSession);
        $cell = $this->attendance->cell($lessonSession, $student);

        return [
            'session_id' => $lessonSession->id, 'student_id' => $student->id,
            'status' => $cell['status'], 'label' => $cell['label'],
            'is_attending' => $cell['is_attending'], 'can_record' => $cell['can_record'],
            'requires_reason' => (bool) $cell['requires_reason'], 'version' => (int) $cell['version'],
            'recorded_at_label' => $cell['record']?->recorded_at?->format('d M Y H:i') ?: '—',
            'has_recorded_status' => $cell['record']?->attendance_status !== null,
            'contribution' => $cell['contribution'],
        ];
    }

    private function relations(): array
    {
        return ['branch', 'teacher.user', 'program', 'classGroup.memberships.student.enrollments', 'sessionStudents.student.enrollments', 'replacementSessions'];
    }

    private function routeBase(Request $request): string
    {
        return Str::beforeLast($request->route()->getName(), '.');
    }
}
