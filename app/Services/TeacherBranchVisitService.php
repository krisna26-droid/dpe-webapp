<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TeacherBranchVisitService
{
    public function __construct(private FileAssetService $files, private TeacherAttendanceAuditService $audit) {}

    public function arrive(Teacher $teacher, Branch $branch, array $data): TeacherAttendance
    {
        $this->assertBranchAssignment($teacher, $branch);
        $photo = $this->files->store($data['photo'], 'teacher-attendance');

        try {
            return DB::transaction(function () use ($teacher, $branch, $data, $photo) {
                // Lock the teacher even when no attendance row exists yet.
                $teacher = Teacher::query()->whereKey($teacher->id)->lockForUpdate()->firstOrFail();
                $this->assertBranchAssignment($teacher, $branch);

                if (TeacherAttendance::query()->where('teacher_id', $teacher->id)
                    ->whereIn('attendance_mode', ['branch_visit', 'legacy_session'])
                    ->whereNotNull('check_in_at')->whereNull('check_out_at')->exists()) {
                    throw ValidationException::withMessages(['attendance' => 'Tutup kunjungan yang sedang berlangsung sebelum mencatat kedatangan baru.']);
                }

                $manual = TeacherAttendance::query()
                    ->where('teacher_id', $teacher->id)->where('branch_id', $branch->id)
                    ->whereDate('attendance_date', now()->toDateString())
                    ->whereIn('attendance_type', ['absent', 'leave', 'sick'])
                    ->whereIn('attendance_mode', ['branch_day', 'legacy_session'])->exists();
                if ($manual) {
                    throw ValidationException::withMessages(['attendance' => 'Hari ini sudah ditandai tidak hadir, izin, atau sakit oleh Admin. Hubungi Admin untuk penyesuaian.']);
                }

                $attendance = TeacherAttendance::query()->create([
                    'id' => (string) Str::uuid(),
                    'session_id' => null,
                    'teacher_id' => $teacher->id,
                    'branch_id' => $branch->id,
                    'attendance_mode' => 'branch_visit',
                    'attendance_date' => now()->toDateString(),
                    'active_visit_teacher_id' => $teacher->id,
                    'check_in_at' => now(),
                    'check_in_photo_file_id' => $photo->id,
                    ...$this->location($data, 'check_in'),
                    'status' => 'in_progress',
                    'attendance_type' => 'present',
                ]);
                $this->audit->record($attendance, 'arrived', $teacher->user_id);

                return $attendance;
            });
        } catch (Throwable $exception) {
            $this->cleanup($photo, $exception);
            throw $exception;
        }
    }

    public function depart(Teacher $teacher, TeacherAttendance $attendance, array $data, ?string $actorId = null): TeacherAttendance
    {
        $this->assertOwnVisit($teacher, $attendance);
        $photo = $this->files->store($data['photo'], 'teacher-attendance');

        try {
            return DB::transaction(function () use ($teacher, $attendance, $data, $photo, $actorId) {
                Teacher::query()->whereKey($teacher->id)->lockForUpdate()->firstOrFail();
                $attendance = TeacherAttendance::query()->whereKey($attendance->id)->lockForUpdate()->firstOrFail();
                $this->assertOwnVisit($teacher, $attendance);

                if (! $attendance->check_in_at || $attendance->check_out_at) {
                    throw ValidationException::withMessages(['attendance' => 'Kunjungan belum dimulai atau sudah memiliki absen pulang.']);
                }

                // Finishing an existing visit remains possible after midnight,
                // assignment expiry, or while other records await review.
                $attendance->update([
                    'check_out_at' => now(),
                    'check_out_photo_file_id' => $photo->id,
                    ...$this->location($data, 'check_out'),
                    'active_visit_teacher_id' => null,
                    'status' => 'pending_review',
                ]);
                $this->audit->record($attendance, 'departed', $actorId ?? $teacher->user_id);

                return $attendance->refresh();
            });
        } catch (Throwable $exception) {
            $this->cleanup($photo, $exception);
            throw $exception;
        }
    }

    private function assertBranchAssignment(Teacher $teacher, Branch $branch): void
    {
        $today = now()->toDateString();
        $assigned = $teacher->branchAssignments()->where('branch_id', $branch->id)
            ->whereDate('starts_on', '<=', $today)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))->exists();

        if (! $teacher->is_active || ! $branch->is_active || ! $assigned) {
            throw new AuthorizationException('Anda tidak memiliki penugasan aktif di cabang ini.');
        }
    }

    private function assertOwnVisit(Teacher $teacher, TeacherAttendance $attendance): void
    {
        if ($attendance->attendance_mode !== 'branch_visit' || (string) $attendance->teacher_id !== (string) $teacher->id) {
            throw new AuthorizationException('Anda tidak dapat menutup kunjungan ini.');
        }
    }

    private function location(array $data, string $side): array
    {
        $captured = $data['location_status'] === 'captured';

        return [
            $side.'_latitude' => $captured ? ($data['latitude'] ?? null) : null,
            $side.'_longitude' => $captured ? ($data['longitude'] ?? null) : null,
            $side.'_accuracy_m' => $captured ? ($data['accuracy'] ?? null) : null,
            $side.'_location_status' => $data['location_status'],
            $side.'_location_note' => $data['location_note'] ?? null,
        ];
    }

    private function cleanup($photo, Throwable $original): void
    {
        try {
            $this->files->delete($photo);
        } catch (Throwable $error) {
            report($error);
        }
    }
}
