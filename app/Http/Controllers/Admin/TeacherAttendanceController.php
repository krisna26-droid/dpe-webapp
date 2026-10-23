<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarkTeacherAttendanceExceptionRequest;
use App\Http\Requests\Admin\ReviewTeacherAttendanceRequest;
use App\Http\Requests\Admin\TeacherAttendanceIndexRequest;
use App\Models\Branch;
use App\Models\LessonSession;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Services\TeacherAttendanceExceptionService;
use App\Services\TeacherAttendanceLocationService;
use App\Services\TeacherAttendanceReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TeacherAttendanceController extends Controller
{
    public function __construct(
        private readonly TeacherAttendanceReviewService $reviewService,
        private readonly TeacherAttendanceExceptionService $exceptionService,
        private readonly TeacherAttendanceLocationService $locationService
    ) {}

    public function index(TeacherAttendanceIndexRequest $request): View
    {
        $actor = $request->user();
        $visibleBranchIds = $this->reviewService->visibleBranchIds($actor);
        $filters = $request->validated();
        $dateFrom = Carbon::createFromFormat(
            'Y-m-d',
            $filters['date_from'] ?? now()->startOfMonth()->toDateString()
        )->startOfDay();
        $dateTo = Carbon::createFromFormat(
            'Y-m-d',
            $filters['date_to'] ?? now()->toDateString()
        )->endOfDay();

        $selectedBranchId = $filters['branch_id'] ?? null;
        $queryBranchIds = $visibleBranchIds;

        if ($selectedBranchId !== null && $visibleBranchIds->contains($selectedBranchId)) {
            $queryBranchIds = collect([$selectedBranchId]);
        }
        abort_if($selectedBranchId !== null && ! $visibleBranchIds->contains($selectedBranchId), 403);

        $baseQuery = TeacherAttendance::query()
            ->whereIn('branch_id', $queryBranchIds)
            ->whereDate('attendance_date', '>=', $dateFrom->toDateString())
            ->whereDate('attendance_date', '<=', $dateTo->toDateString())
            ->when(! empty($filters['attendance_mode']), fn ($query) => $query->where('attendance_mode', $filters['attendance_mode']))
            ->when(
                ! empty($filters['teacher_id']),
                fn ($query) => $query->where('teacher_id', $filters['teacher_id'])
            )
            ->when(
                ! empty($filters['status']),
                fn ($query) => $query->where('status', $filters['status'])
            );

        if (! empty($filters['attendance_type'])) {
            if ($filters['attendance_type'] === 'unrecorded') {
                $baseQuery->where('attendance_type', '!=', 'present');
            } else {
                $baseQuery->where('attendance_type', $filters['attendance_type']);
            }
        }

        $attendances = (clone $baseQuery)
            ->with([
                'session',
                'branch',
                'teacher.user',
                'reviewedBy',
            ])
            ->orderByDesc(
                'created_at'
            )
            ->paginate(15)
            ->withQueryString();

        $exceptionQuery = $this->exceptionService->dueSessions(
            $actor,
            $dateFrom,
            $dateTo,
            $selectedBranchId,
            $filters['teacher_id'] ?? null
        );
        $pendingBranchDays = $this->exceptionService->dueBranchDays($actor, $dateFrom, $dateTo, $selectedBranchId, $filters['teacher_id'] ?? null);

        if (
            ! empty($filters['status'])
            || (! empty($filters['attendance_type']) && $filters['attendance_type'] !== 'unrecorded')
        ) {
            $exceptionQuery->whereRaw('1 = 0');
            $pendingBranchDays = collect();
        }
        if (! empty($filters['attendance_mode']) && $filters['attendance_mode'] !== 'private_session') {
            $exceptionQuery->whereRaw('1 = 0');
        }
        if (! empty($filters['attendance_mode']) && ! in_array($filters['attendance_mode'], ['branch_visit', 'branch_day'], true)) {
            $pendingBranchDays = collect();
        }

        $exceptionCount = (clone $exceptionQuery)->count();
        $pendingSessions = $exceptionQuery
            ->paginate(10, ['*'], 'exception_page')
            ->withQueryString();

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'pending_review' => (clone $baseQuery)->where('status', 'pending_review')->count(),
            'approved' => (clone $baseQuery)
                ->where('status', 'approved')
                ->where('attendance_type', 'present')
                ->count(),
            'needs_correction' => (clone $baseQuery)->where('status', 'needs_correction')->count(),
            'without_record' => $exceptionCount + $pendingBranchDays->count(),
            'branch_visits' => (clone $baseQuery)->where('attendance_mode', 'branch_visit')->count(),
            'private_sessions' => (clone $baseQuery)->where('attendance_mode', 'private_session')->count(),
        ];

        $branches = Branch::query()
            ->whereIn('id', $visibleBranchIds)
            ->orderBy('name')
            ->get();

        $teachers = Teacher::query()
            ->with('user')
            ->where(function ($query) use ($queryBranchIds, $dateFrom, $dateTo) {
                $query->whereHas('branchAssignments', fn ($assignment) => $assignment->whereIn('branch_id', $queryBranchIds)
                    ->whereDate('starts_on', '<=', $dateTo->toDateString())
                    ->where(fn ($period) => $period->whereNull('ends_on')->orWhereDate('ends_on', '>=', $dateFrom->toDateString())))
                    ->orWhereIn('id', LessonSession::query()->select('teacher_id')->whereIn('branch_id', $queryBranchIds)
                        ->whereBetween('planned_start_at', [$dateFrom, $dateTo]))
                    ->orWhereHas('attendances', fn ($attendance) => $attendance->whereIn('branch_id', $queryBranchIds)
                        ->whereDate('attendance_date', '>=', $dateFrom->toDateString())->whereDate('attendance_date', '<=', $dateTo->toDateString()));
            })
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Teacher $teacher) => strtolower((string) (
                $teacher->user?->full_name
                ?: $teacher->user?->username
                ?: $teacher->id
            )))
            ->values();

        return view('admin.teacher-attendance.index', [
            'attendances' => $attendances,
            'pendingSessions' => $pendingSessions,
            'pendingBranchDays' => $pendingBranchDays,
            'branches' => $branches,
            'teachers' => $teachers,
            'stats' => $stats,
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'branch_id' => $selectedBranchId,
                'teacher_id' => $filters['teacher_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'attendance_type' => $filters['attendance_type'] ?? null,
                'attendance_mode' => $filters['attendance_mode'] ?? null,
            ],
            'statusOptions' => [
                'in_progress' => 'Sedang mengajar',
                'pending_review' => 'Menunggu pemeriksaan',
                'approved' => 'Disetujui',
                'needs_correction' => 'Perlu koreksi',
                'recorded' => 'Dicatat Admin',
            ],
            'modeOptions' => [
                'branch_visit' => 'Kunjungan cabang',
                'private_session' => 'Private di rumah siswa',
                'branch_day' => 'Ketidakhadiran cabang per hari',
                'legacy_session' => 'Riwayat sesi lama',
            ],
            'attendanceTypeOptions' => [
                'present' => 'Hadir',
                'absent' => 'Tidak hadir',
                'leave' => 'Izin',
                'sick' => 'Sakit',
                'unrecorded' => 'Semua ketidakhadiran',
            ],
            'exceptionTypeOptions' => [
                'absent' => 'Tidak hadir',
                'leave' => 'Izin',
                'sick' => 'Sakit',
            ],
        ]);
    }

    public function markException(
        MarkTeacherAttendanceExceptionRequest $request,
        LessonSession $lessonSession
    ): RedirectResponse {
        $this->exceptionService->mark(
            $lessonSession,
            $request->user(),
            $request->validated('attendance_type'),
            $request->validated('manual_note')
        );

        $label = [
            'absent' => 'tidak hadir',
            'leave' => 'izin',
            'sick' => 'sakit',
        ][$request->validated('attendance_type')] ?? 'ketidakhadiran';

        return redirect()
            ->route('admin.teacher-attendance.index', array_filter([
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'branch_id' => $request->input('branch_id'),
                'teacher_id' => $request->input('teacher_id'),
            ]))
            ->with('success', ($lessonSession->isAtStudentHome() ? 'Sesi private' : 'Hari mengajar di cabang')." berhasil ditandai {$label}.");
    }

    public function show(
        Request $request,
        TeacherAttendance $teacherAttendance
    ): View {
        $this->reviewService->authorizeView(
            $teacherAttendance,
            $request->user()
        );

        $teacherAttendance->load([
            'session.branch',
            'session.program',
            'session.classGroup',
            'teacher.user',
            'branch',
            'checkInPhoto',
            'checkOutPhoto',
            'reviewedBy',
            'recordedBy',
        ]);

        return view('admin.teacher-attendance.show', [
            'attendance' => $teacherAttendance,
            'checkInLocation' => $this->locationService->point($teacherAttendance, 'check-in'),
            'checkOutLocation' => $this->locationService->point($teacherAttendance, 'check-out'),
            'auditLogs' => DB::table('teacher_attendance_audit_logs as logs')
                ->leftJoin('users', 'users.id', '=', 'logs.actor_user_id')
                ->where('logs.teacher_attendance_id', $teacherAttendance->id)
                ->select('logs.*', 'users.full_name as actor_name')->orderBy('logs.happened_at')->get(),
        ]);
    }

    public function review(
        ReviewTeacherAttendanceRequest $request,
        TeacherAttendance $teacherAttendance
    ): RedirectResponse {
        $this->reviewService->review(
            $teacherAttendance,
            $request->user(),
            $request->validated('status'),
            $request->validated('review_note')
        );

        $message = $request->validated('status') === 'approved'
            ? 'Absensi guru berhasil disetujui.'
            : 'Absensi guru ditandai perlu koreksi.';

        return redirect()
            ->route('admin.teacher-attendance.show', $teacherAttendance)
            ->with('success', $message);
    }

    public function location(
        Request $request,
        TeacherAttendance $teacherAttendance,
        string $side
    ): JsonResponse {
        $this->reviewService->authorizeView($teacherAttendance, $request->user());
        abort_unless(in_array($side, ['check-in', 'check-out'], true), 404);

        // Always use the stored point; query-string coordinates are never accepted.
        $point = $this->locationService->point($teacherAttendance, $side);
        $address = $point === null ? null : $this->locationService->address($point);

        return response()->json([
            'status' => $address === null ? 'unavailable' : 'resolved',
            'address' => $address,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function photo(
        Request $request,
        TeacherAttendance $teacherAttendance,
        string $side
    ): Response {
        $this->reviewService->authorizeView(
            $teacherAttendance,
            $request->user()
        );

        $teacherAttendance->loadMissing([
            'checkInPhoto',
            'checkOutPhoto',
        ]);

        $file = match ($side) {
            'check-in' => $teacherAttendance->checkInPhoto,
            'check-out' => $teacherAttendance->checkOutPhoto,
            default => null,
        };

        abort_unless($file !== null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->storage_key), 404);

        return response()->file(
            $disk->path($file->storage_key),
            [
                'Content-Type' => $file->mime_type,
                'Cache-Control' => 'private, no-store',
            ]
        );
    }
}
