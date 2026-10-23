<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\TeacherAttendanceCaptureRequest;
use App\Models\Branch;
use App\Models\LessonSession;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\TeacherAttendanceService;
use App\Services\TeacherBranchVisitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private TeacherAttendanceService $attendanceService,
        private TeacherBranchVisitService $visitService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $teacherId = $user->role_code === 'teacher'
            ? $user->teacherProfile?->id
            : null;

        if ($user->role_code === 'teacher' && $teacherId === null) {
            abort(403, 'Akun teacher belum memiliki profil guru.');
        }

        $todaySessions = LessonSession::query()
            ->with([
                'branch',
                'program',
                'classGroup',
                'teacher.user',
                'teacherAttendance.checkInPhoto',
                'teacherAttendance.checkOutPhoto',
                'teacherAttendance.recordedBy',
            ])
            ->when(
                $teacherId !== null,
                fn ($query) => $query->where('teacher_id', $teacherId)
            )
            ->where(function ($query) {
                $query->where(fn ($today) => $today->where('teaching_location', 'student_home')
                    ->whereDate('planned_start_at', now()->toDateString())->notSuperseded()
                    ->whereHas('teacher', fn ($teacher) => $teacher->where('is_active', true)->whereHas('user', fn ($user) => $user->where('is_active', true)->where('role_code', 'teacher'))))
                    ->orWhereHas('teacherAttendance', fn ($attendance) => $attendance->whereNotNull('check_in_at')->whereNull('check_out_at'));
            })
            ->orderBy('planned_start_at')
            ->get();

        $recentAttendances = TeacherAttendance::query()
            ->with([
                'session.branch',
                'session.program',
                'teacher.user',
                'branch',
                'reviewedBy',
            ])
            ->when(
                $teacherId !== null,
                fn ($query) => $query->where('teacher_id', $teacherId)
            )
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $activeVisits = TeacherAttendance::query()->with(['branch', 'teacher.user'])
            ->where('attendance_mode', 'branch_visit')->whereNotNull('check_in_at')->whereNull('check_out_at')
            ->when($teacherId !== null, fn ($query) => $query->where('teacher_id', $teacherId))->get();

        $branchIds = TeacherBranchAssignment::query()->where('teacher_id', $teacherId)
            ->whereDate('starts_on', '<=', now()->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()->toDateString()))
            ->pluck('branch_id');
        $branches = Branch::query()->whereIn('id', $branchIds)->where('is_active', true)->orderBy('name')->get();
        $branchSessions = LessonSession::query()->with(['branch', 'program', 'classGroup', 'teacher.user'])
            ->where('teaching_location', 'branch')->whereDate('planned_start_at', now()->toDateString())->notSuperseded()
            ->when($teacherId !== null, fn ($query) => $query->where('teacher_id', $teacherId))->orderBy('planned_start_at')->get();
        $manualBranchDays = TeacherAttendance::query()->with(['branch', 'recordedBy'])
            ->whereIn('attendance_mode', ['branch_day', 'legacy_session'])
            ->whereIn('attendance_type', ['absent', 'leave', 'sick'])->whereDate('attendance_date', now()->toDateString())
            ->when($teacherId !== null, fn ($query) => $query->where('teacher_id', $teacherId))->get();

        return view('teacher.attendance.index', [
            'todaySessions' => $todaySessions,
            'activeVisits' => $activeVisits,
            'branches' => $branches,
            'branchSessions' => $branchSessions,
            'manualBranchDays' => $manualBranchDays,
            'recentAttendances' => $recentAttendances,
            'todayLabel' => now()->format('d M Y'),
        ]);
    }

    public function checkIn(
        TeacherAttendanceCaptureRequest $request,
        LessonSession $lessonSession
    ): RedirectResponse {
        $teacher = $this->teacherForSession($request->user(), $lessonSession);

        $this->attendanceService->checkIn(
            $lessonSession,
            $teacher,
            $request->validated(),
            $request->user()->id
        );

        return redirect()
            ->route('teacher.attendance.index')
            ->with('success', 'Absen mulai private berhasil dicatat.');
    }

    public function checkOut(
        TeacherAttendanceCaptureRequest $request,
        LessonSession $lessonSession
    ): RedirectResponse {
        $teacher = $this->teacherForSession($request->user(), $lessonSession);

        $this->attendanceService->checkOut(
            $lessonSession,
            $teacher,
            $request->validated(),
            $request->user()->id
        );

        return redirect()
            ->route('teacher.attendance.index')
            ->with('success', 'Absen selesai berhasil dicatat dan menunggu pemeriksaan.');
    }

    public function arrive(TeacherAttendanceCaptureRequest $request, Branch $branch): RedirectResponse
    {
        $teacher = $request->user()->teacherProfile;
        abort_unless($request->user()->role_code === 'teacher' && $teacher !== null, 403);
        $this->visitService->arrive($teacher, $branch, $request->validated());

        return redirect()->route('teacher.attendance.index')->with('success', 'Absen datang berhasil dicatat.');
    }

    public function depart(TeacherAttendanceCaptureRequest $request, TeacherAttendance $teacherAttendance): RedirectResponse
    {
        $teacher = $request->user()->role_code === 'superadmin'
            ? $teacherAttendance->teacher
            : $request->user()->teacherProfile;
        abort_unless($teacher !== null, 403);
        $this->visitService->depart($teacher, $teacherAttendance, $request->validated(), $request->user()->id);

        return redirect()->route('teacher.attendance.index')->with('success', 'Absen pulang berhasil dicatat dan menunggu pemeriksaan.');
    }

    private function teacherForSession(
        User $user,
        LessonSession $lessonSession
    ): Teacher {
        if ($user->role_code === 'superadmin') {
            return $lessonSession->teacher()->with('user')->firstOrFail();
        }

        $teacher = $user->teacherProfile;

        abort_unless(
            $teacher !== null
            && (string) $lessonSession->teacher_id === (string) $teacher->id,
            403,
            'Anda tidak dapat mencatat absensi untuk sesi ini.'
        );

        return $teacher;
    }
}
