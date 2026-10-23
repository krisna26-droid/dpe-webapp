<?php

namespace App\Services;

use App\Models\LessonSession;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TeacherAttendanceService
{
    public function __construct(
        private FileAssetService $fileAssetService,
        private TeacherAttendanceAuditService $audit
    ) {}

    public function checkIn(
        LessonSession $session,
        Teacher $teacher,
        array $data,
        ?string $actorId = null
    ): TeacherAttendance {
        $this->assertSessionBelongsToTeacher($session, $teacher);
        $this->assertTeacherActive($teacher);
        $this->assertSessionCanBeAttended($session);

        $photo = $this->fileAssetService->store(
            $data['photo'],
            'teacher-attendance'
        );

        try {
            return DB::transaction(function () use ($session, $teacher, $data, $photo, $actorId) {
                $session = LessonSession::query()
                    ->whereKey($session->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertSessionBelongsToTeacher($session, $teacher);
                $this->assertTeacherActive($teacher);
                $this->assertSessionCanBeAttended($session);

                $attendance = TeacherAttendance::query()
                    ->where('session_id', $session->id)
                    ->lockForUpdate()
                    ->first();

                if ($attendance?->check_in_at !== null) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Sesi ini sudah memiliki absen masuk.',
                    ]);
                }

                if ($attendance?->isManualException()) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Sesi ini sudah ditandai sebagai ketidakhadiran oleh Admin.',
                    ]);
                }

                $payload = [
                    'attendance_mode' => 'private_session',
                    'attendance_date' => $session->planned_start_at->toDateString(),
                    'session_id' => $session->id,
                    'teacher_id' => $teacher->id,
                    'branch_id' => $session->branch_id,
                    'check_in_at' => now(),
                    'check_in_photo_file_id' => $photo->id,
                    'check_in_latitude' => $data['latitude'] ?? null,
                    'check_in_longitude' => $data['longitude'] ?? null,
                    'check_in_accuracy_m' => $data['accuracy'] ?? null,
                    'check_in_location_status' => $data['location_status'],
                    'check_in_location_note' => $data['location_note'] ?? null,
                    'status' => 'in_progress',
                    'attendance_type' => 'present',
                ];

                if ($attendance) {
                    $attendance->update($payload);

                    $this->audit->record($attendance, 'started', $actorId ?? $teacher->user_id);

                    return $attendance->refresh();
                }

                $attendance = TeacherAttendance::query()->create([
                    'id' => (string) Str::uuid(),
                    ...$payload,
                ]);
                $this->audit->record($attendance, 'started', $actorId ?? $teacher->user_id);

                return $attendance;
            });
        } catch (Throwable $exception) {
            $this->cleanupPhoto($photo, $exception);

            if ($exception instanceof QueryException
                && str_contains($exception->getMessage(), 'uq_teacher_attendances_session')) {
                throw ValidationException::withMessages([
                    'attendance' => 'Sesi ini sudah memiliki catatan absensi.',
                ]);
            }

            throw $exception;
        }
    }

    public function checkOut(
        LessonSession $session,
        Teacher $teacher,
        array $data,
        ?string $actorId = null
    ): TeacherAttendance {
        $this->assertSessionBelongsToTeacher($session, $teacher);

        $attendance = TeacherAttendance::query()
            ->where('session_id', $session->id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if ($attendance?->isManualException()) {
            throw ValidationException::withMessages([
                'attendance' => 'Sesi ini sudah ditandai sebagai ketidakhadiran oleh Admin.',
            ]);
        }

        if (! $attendance || $attendance->check_in_at === null) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen masuk harus dicatat sebelum absen selesai.',
            ]);
        }

        if ($attendance->check_out_at !== null) {
            throw ValidationException::withMessages([
                'attendance' => 'Sesi ini sudah memiliki absen selesai.',
            ]);
        }

        $photo = $this->fileAssetService->store(
            $data['photo'],
            'teacher-attendance'
        );

        try {
            return DB::transaction(function () use ($session, $teacher, $attendance, $data, $photo, $actorId) {
                $lockedSession = LessonSession::query()
                    ->whereKey($session->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertSessionBelongsToTeacher($lockedSession, $teacher);

                $lockedAttendance = TeacherAttendance::query()
                    ->whereKey($attendance->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedAttendance->isManualException()) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Sesi ini sudah ditandai sebagai ketidakhadiran oleh Admin.',
                    ]);
                }

                if ($lockedAttendance->check_out_at !== null) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Sesi ini sudah memiliki absen selesai.',
                    ]);
                }

                $lockedAttendance->update([
                    'check_out_at' => now(),
                    'check_out_photo_file_id' => $photo->id,
                    'check_out_latitude' => $data['latitude'] ?? null,
                    'check_out_longitude' => $data['longitude'] ?? null,
                    'check_out_accuracy_m' => $data['accuracy'] ?? null,
                    'check_out_location_status' => $data['location_status'],
                    'check_out_location_note' => $data['location_note'] ?? null,
                    'status' => 'pending_review',
                ]);
                $this->audit->record($lockedAttendance, 'finished', $actorId ?? $teacher->user_id);

                return $lockedAttendance->refresh();
            });
        } catch (Throwable $exception) {
            $this->cleanupPhoto($photo, $exception);

            throw $exception;
        }
    }

    private function assertSessionBelongsToTeacher(
        LessonSession $session,
        Teacher $teacher
    ): void {
        if ((string) $session->teacher_id !== (string) $teacher->id) {
            throw new AuthorizationException(
                'Teacher tidak dapat mencatat absensi untuk sesi teacher lain.'
            );
        }
    }

    private function assertTeacherActive(Teacher $teacher): void
    {
        if (! Teacher::whereKey($teacher->id)->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true)->where('role_code', 'teacher'))->exists()) {
            throw new AuthorizationException('Profil dan akun Teacher harus aktif untuk memulai absensi baru.');
        }
    }

    private function assertSessionCanBeAttended(LessonSession $session): void
    {
        if (app(StudentAttendanceService::class)->isSuperseded($session)) {
            throw ValidationException::withMessages(['attendance' => 'Sesi sudah memiliki jadwal pengganti. Catat absensi pada sesi pengganti.']);
        }
        if (! $session->isAtStudentHome()) {
            throw ValidationException::withMessages([
                'attendance' => 'Mengajar di cabang memakai Absen Datang dan Pulang per kunjungan, bukan per sesi.',
            ]);
        }

        if (! $session->planned_start_at?->isToday()) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen mulai private hanya dapat dicatat pada tanggal sesi.',
            ]);
        }

        if ($session->isCancelled()) {
            throw ValidationException::withMessages([
                'attendance' => 'Sesi yang dibatalkan tidak dapat diberi absensi.',
            ]);
        }
    }

    private function cleanupPhoto($photo, Throwable $exception): void
    {
        try {
            $this->fileAssetService->delete($photo);
        } catch (Throwable $cleanupException) {
            report($cleanupException);
        }

        if (! $exception instanceof ValidationException) {
            report($exception);
        }
    }
}
