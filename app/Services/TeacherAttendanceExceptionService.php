<?php

namespace App\Services;

use App\Models\LessonSession;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeacherAttendanceExceptionService
{
    public function __construct(
        private readonly TeacherAttendanceReviewService $reviewService,
        private readonly TeacherAttendanceAuditService $audit
    ) {}

    /**
     * Ambil sesi yang sudah waktunya dimulai tetapi belum memiliki catatan
     * absensi guru. Sesi mendatang dan sesi yang dibatalkan tidak ditampilkan.
     */
    public function dueSessions(
        User $actor,
        Carbon $dateFrom,
        Carbon $dateTo,
        ?string $branchId = null,
        ?string $teacherId = null
    ): Builder {
        $visibleBranchIds = $this->reviewService->visibleBranchIds($actor);
        $queryBranchIds = $visibleBranchIds;

        if ($branchId !== null && $visibleBranchIds->contains($branchId)) {
            $queryBranchIds = collect([$branchId]);
        }

        return LessonSession::query()
            ->where('teaching_location', 'student_home')
            ->whereIn('branch_id', $queryBranchIds)
            ->whereBetween('planned_start_at', [
                $dateFrom->copy()->startOfDay(),
                $dateTo->copy()->endOfDay(),
            ])
            ->where('planned_start_at', '<=', now())
            ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?)', ['cancelled', 'canceled', 'dibatalkan'])
            ->notSuperseded()
            ->whereDoesntHave('teacherAttendance')
            ->when(
                $teacherId !== null,
                fn ($query) => $query->where('teacher_id', $teacherId)
            )
            ->with([
                'branch',
                'teacher.user',
                'program',
                'classGroup',
            ])
            ->orderBy('planned_start_at');
    }

    public function dueBranchDays(User $actor, Carbon $from, Carbon $to, ?string $branchId = null, ?string $teacherId = null): Collection
    {
        $branches = $this->reviewService->visibleBranchIds($actor);
        if ($branchId !== null) {
            $branches = $branches->filter(fn ($id) => (string) $id === $branchId);
        }

        return LessonSession::query()
            ->whereIn('branch_id', $branches)
            ->where('teaching_location', 'branch')->notSuperseded()
            ->whereBetween('planned_start_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('planned_start_at', '<=', now())
            ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?)', ['cancelled', 'canceled', 'dibatalkan'])
            ->when($teacherId !== null, fn ($query) => $query->where('teacher_id', $teacherId))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('teacher_attendances')
                    ->whereColumn('teacher_attendances.teacher_id', 'lesson_sessions.teacher_id')
                    ->whereColumn('teacher_attendances.branch_id', 'lesson_sessions.branch_id')
                    ->whereIn('teacher_attendances.attendance_mode', ['branch_visit', 'branch_day', 'legacy_session'])
                    ->whereRaw('DATE(teacher_attendances.attendance_date) = DATE(lesson_sessions.planned_start_at)');
            })
            ->selectRaw('MIN(id) as id, teacher_id, branch_id, DATE(planned_start_at) as attendance_day, MIN(planned_start_at) as planned_start_at')
            ->groupBy('teacher_id', 'branch_id')->groupByRaw('DATE(planned_start_at)')
            ->with(['branch', 'teacher.user'])
            ->orderBy('planned_start_at')->get();
    }

    public function mark(
        LessonSession $session,
        User $actor,
        string $attendanceType,
        ?string $manualNote = null
    ): TeacherAttendance {
        $this->authorizeSession($session, $actor);

        if (! in_array($attendanceType, ['absent', 'leave', 'sick'], true)) {
            throw ValidationException::withMessages([
                'attendance_type' => 'Jenis ketidakhadiran tidak valid.',
            ]);
        }

        $manualNote = trim((string) $manualNote);

        if (in_array($attendanceType, ['leave', 'sick'], true) && $manualNote === '') {
            throw ValidationException::withMessages([
                'manual_note' => 'Catatan wajib diisi untuk izin atau sakit.',
            ]);
        }

        $this->assertEligibleSession($session);

        return DB::transaction(function () use (
            $session,
            $actor,
            $attendanceType,
            $manualNote
        ) {
            Teacher::query()->whereKey($session->teacher_id)->lockForUpdate()->firstOrFail();
            $lockedSession = LessonSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeSession($lockedSession, $actor);

            $this->assertEligibleSession($lockedSession);

            $isPrivate = $lockedSession->isAtStudentHome();
            $date = $lockedSession->planned_start_at->toDateString();
            $existing = TeacherAttendance::query()
                ->when($isPrivate,
                    fn ($query) => $query->where('session_id', $lockedSession->id),
                    fn ($query) => $query->where('teacher_id', $lockedSession->teacher_id)
                        ->where('branch_id', $lockedSession->branch_id)
                        ->whereDate('attendance_date', $date)
                        ->whereIn('attendance_mode', ['branch_visit', 'branch_day', 'legacy_session'])
                )
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Sesi atau hari cabang ini sudah memiliki catatan absensi.',
                ]);
            }

            $attendance = TeacherAttendance::query()->create([
                'id' => (string) Str::uuid(),
                'session_id' => $isPrivate ? $lockedSession->id : null,
                'attendance_mode' => $isPrivate ? 'private_session' : 'branch_day',
                'attendance_date' => $date,
                'exception_day_key' => $isPrivate ? null : $lockedSession->teacher_id.'|'.$lockedSession->branch_id.'|'.$date,
                'teacher_id' => $lockedSession->teacher_id,
                'branch_id' => $lockedSession->branch_id,
                'status' => 'recorded',
                'attendance_type' => $attendanceType,
                'manual_note' => $manualNote !== '' ? $manualNote : null,
                'recorded_by_user_id' => $actor->id,
                'recorded_at' => now(),
            ]);
            $this->audit->record($attendance, 'manual_exception', $actor->id, $manualNote ?: null);

            return $attendance;
        });
    }

    public function authorizeSession(
        LessonSession $session,
        User $actor
    ): void {
        if (! $actor->is_active || ! in_array($actor->role_code, ['admin', 'superadmin'], true)) {
            throw new AuthorizationException(
                'Akun ini tidak dapat mencatat pengecualian absensi guru.'
            );
        }

        $visibleBranchIds = $this->reviewService->visibleBranchIds($actor);

        if (! $visibleBranchIds->contains($session->branch_id)) {
            throw new AuthorizationException(
                'Admin tidak memiliki penugasan aktif pada cabang sesi ini.'
            );
        }
    }

    private function assertEligibleSession(LessonSession $session): void
    {
        if (app(StudentAttendanceService::class)->isSuperseded($session)) {
            throw ValidationException::withMessages(['attendance' => 'Sesi sudah memiliki jadwal pengganti. Periksa pelaksanaan pada sesi pengganti.']);
        }
        if ($session->isCancelled()) {
            throw ValidationException::withMessages([
                'attendance' => 'Sesi yang dibatalkan tidak dapat diberi status kehadiran.',
            ]);
        }

        if ($session->planned_start_at?->isFuture()) {
            throw ValidationException::withMessages([
                'attendance' => 'Status dapat dicatat setelah waktu mulai rencana sesi.',
            ]);
        }
    }
}
