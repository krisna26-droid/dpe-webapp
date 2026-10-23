<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\LessonLearningAudit;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\SessionStudent;
use App\Models\Teacher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LessonSessionService
{
    public function create(array $data): LessonSession
    {
        return DB::transaction(function () use ($data) {
            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

            $program = Program::query()
                ->findOrFail($data['program_id']);

            $classGroup = null;

            if (
                array_key_exists('class_group_id', $data)
                && $data['class_group_id'] !== null
            ) {
                $classGroup = ClassGroup::query()
                    ->findOrFail($data['class_group_id']);
            }

            $rescheduledFrom = null;

            if (
                array_key_exists('rescheduled_from_session_id', $data)
                && $data['rescheduled_from_session_id'] !== null
            ) {
                $rescheduledFrom = LessonSession::query()
                    ->lockForUpdate()
                    ->findOrFail($data['rescheduled_from_session_id']);
                $this->validateReplacement($rescheduledFrom, $data);
            }

            $this->validateReferences($branch, $teacher, $program, $classGroup);

            $this->validateTimeRange(
                $data['planned_start_at'],
                $data['planned_end_at'],
                $data['actual_start_at'] ?? null,
                $data['actual_end_at'] ?? null
            );

            app(LessonSchedulingService::class)->validate($data);

            $session = LessonSession::query()->create([
                'id' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'teaching_location' => $data['teaching_location'] ?? 'branch',
                'teacher_id' => $teacher->id,
                'program_id' => $program->id,
                'class_group_id' => $classGroup?->id,
                'planned_start_at' => $data['planned_start_at'],
                'planned_end_at' => $data['planned_end_at'],
                'actual_start_at' => $data['actual_start_at'] ?? null,
                'actual_end_at' => $data['actual_end_at'] ?? null,
                'status' => $data['status'],
                'rescheduled_from_session_id' => $rescheduledFrom?->id,
                'topic' => $data['topic'] ?? null,
                'material' => $data['material'] ?? null,
                'activity' => $data['activity'] ?? null,
            ]);
            if ($rescheduledFrom && $session->class_group_id === null) {
                $attendance = app(StudentAttendanceService::class);
                $students = $rescheduledFrom->sessionStudents()->with('student.enrollments')->get()->pluck('student')->filter();
                $ids = $students->filter(fn ($student) => $attendance->eligibleToAttach($student, $session))->pluck('id')->all();
                if ($actor = auth()->user()) {
                    $attendance->setRoster($actor, $session, $ids, $attendance->rosterSignature($session));
                } else {
                    foreach ($ids as $id) {
                        SessionStudent::query()->create(['session_id' => $session->id, 'student_id' => $id]);
                    }
                }
            }

            return $session;
        });
    }

    public function update(
        LessonSession $lessonSession,
        array $data,
        array $ignoreSessionIds = []
    ): LessonSession {
        return DB::transaction(function () use (
            $lessonSession,
            $data,
            $ignoreSessionIds
        ) {
            $lessonSession = LessonSession::query()->whereKey($lessonSession->id)->lockForUpdate()->firstOrFail();
            if ($lessonSession->schedule_series_id) {
                foreach (['branch_id', 'program_id', 'class_group_id'] as $field) {
                    if ((string) ($data[$field] ?? null) !== (string) $lessonSession->getRawOriginal($field)) {
                        throw ValidationException::withMessages([$field => 'Satu seri memakai cabang, program dan kelompok yang sama. Buat seri baru untuk kelompok berbeda.']);
                    }
                }
            }
            $hasStudentAttendance = $lessonSession->sessionStudents()->where(fn ($q) => $q->whereNotNull('attendance_status')->orWhereNotNull('recorded_at'))->exists();
            $hasReplacement = $lessonSession->replacementSessions()->whereNotIn('status', ['cancelled', 'canceled', 'dibatalkan'])->exists();
            $hasLearningHistory = LessonLearningAudit::query()->where('session_id', $lessonSession->id)->exists();
            if ($hasStudentAttendance || $hasReplacement || $hasLearningHistory) {
                foreach (['teacher_id', 'branch_id', 'program_id', 'class_group_id', 'teaching_location', 'planned_start_at', 'planned_end_at', 'rescheduled_from_session_id'] as $field) {
                    $current = $lessonSession->getRawOriginal($field);
                    $proposed = array_key_exists($field, $data) ? $data[$field] : $current;
                    if (in_array($field, ['planned_start_at', 'planned_end_at'], true)) {
                        $current = Carbon::parse($current)->format('Y-m-d H:i:s');
                        $proposed = Carbon::parse($proposed)->format('Y-m-d H:i:s');
                    }
                    if ((string) $current !== (string) $proposed) {
                        throw ValidationException::withMessages([$field => 'Identitas dan jadwal yang sudah memiliki kehadiran atau catatan belajar tidak dapat dipindahkan.']);
                    }
                }
                $oldDate = Carbon::parse($lessonSession->getRawOriginal('actual_start_at') ?: $lessonSession->getRawOriginal('planned_start_at'))->toDateString();
                $newDate = Carbon::parse($data['actual_start_at'] ?? $data['planned_start_at'])->toDateString();
                if ($oldDate !== $newDate) {
                    throw ValidationException::withMessages(['actual_start_at' => 'Tanggal pertemuan dengan kehadiran atau catatan belajar tersimpan tidak dapat diganti.']);
                }
                if (in_array(strtolower((string) $data['status']), ['cancelled', 'canceled', 'dibatalkan'], true)
                    && $data['status'] !== $lessonSession->status
                    && $lessonSession->sessionStudents()->whereNotNull('attendance_status')->where('attendance_status', '!=', 'rescheduled')->exists()) {
                    throw ValidationException::withMessages(['status' => 'Koreksi kehadiran tersimpan sebelum membatalkan sesi.']);
                }
            }
            if ($lessonSession->teacherAttendance()->exists()) {
                foreach (['teacher_id', 'branch_id', 'planned_start_at', 'planned_end_at', 'teaching_location'] as $field) {
                    $current = $lessonSession->getRawOriginal($field);
                    $proposed = $data[$field] ?? $current;
                    if (in_array($field, ['planned_start_at', 'planned_end_at'], true)) {
                        $current = Carbon::parse($current)->format('Y-m-d H:i:s');
                        $proposed = Carbon::parse($proposed)->format('Y-m-d H:i:s');
                    }
                    if ((string) $current !== (string) $proposed) {
                        throw ValidationException::withMessages([$field => 'Identitas, waktu, dan lokasi sesi yang sudah memiliki absensi tidak dapat diganti. Buat sesi baru untuk penjadwalan ulang.']);
                    }
                }
            }
            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

            $program = Program::query()
                ->findOrFail($data['program_id']);

            $classGroup = null;

            if (
                array_key_exists('class_group_id', $data)
                && $data['class_group_id'] !== null
            ) {
                $classGroup = ClassGroup::query()
                    ->findOrFail($data['class_group_id']);
            }

            $rescheduledFrom = null;

            if (
                array_key_exists('rescheduled_from_session_id', $data)
                && $data['rescheduled_from_session_id'] !== null
            ) {
                $rescheduledFrom = LessonSession::query()
                    ->lockForUpdate()
                    ->findOrFail($data['rescheduled_from_session_id']);
                $this->validateReplacement($rescheduledFrom, $data, $lessonSession->id);
            }

            $this->validateReferences($branch, $teacher, $program, $classGroup, $lessonSession);

            $this->validateTimeRange(
                $data['planned_start_at'],
                $data['planned_end_at'],
                $data['actual_start_at'] ?? null,
                $data['actual_end_at'] ?? null
            );

            app(LessonSchedulingService::class)->validate($data, $lessonSession, $ignoreSessionIds);

            $lessonSession->update([
                'branch_id' => $branch->id,
                'teaching_location' => $data['teaching_location'] ?? $lessonSession->teaching_location,
                'teacher_id' => $teacher->id,
                'program_id' => $program->id,
                'class_group_id' => $classGroup?->id,
                'planned_start_at' => $data['planned_start_at'],
                'planned_end_at' => $data['planned_end_at'],
                'actual_start_at' => $data['actual_start_at'] ?? null,
                'actual_end_at' => $data['actual_end_at'] ?? null,
                'status' => $data['status'],
                'rescheduled_from_session_id' => $rescheduledFrom?->id,
                'topic' => $data['topic'] ?? null,
                'material' => $data['material'] ?? null,
                'activity' => $data['activity'] ?? null,
            ]);

            return $lessonSession->refresh();
        });
    }

    private function validateReferences(Branch $branch, Teacher $teacher, Program $program, ?ClassGroup $group, ?LessonSession $current = null): void
    {
        if ($current?->branch_id !== $branch->id && ! $branch->is_active) {
            throw ValidationException::withMessages(['branch_id' => 'Pilih cabang aktif untuk jadwal baru.']);
        }
        if ($current?->program_id !== $program->id && ! $program->is_active) {
            throw ValidationException::withMessages(['program_id' => 'Pilih program aktif untuk jadwal baru.']);
        }
        if ($current?->teacher_id !== $teacher->id && (! $teacher->is_active || ! $teacher->user?->is_active || $teacher->user?->role_code !== 'teacher')) {
            throw ValidationException::withMessages(['teacher_id' => 'Pilih Teacher dengan profil dan akun aktif.']);
        }
        if ($group && ($group->branch_id !== $branch->id || $group->program_id !== $program->id)) {
            throw ValidationException::withMessages(['class_group_id' => 'Kelas harus memakai cabang dan program yang sama dengan sesi.']);
        }
        if ($group && $current?->class_group_id !== $group->id && ! $group->is_active) {
            throw ValidationException::withMessages(['class_group_id' => 'Pilih kelas aktif untuk jadwal baru.']);
        }
    }

    private function validateTimeRange(
        string $plannedStartAt,
        string $plannedEndAt,
        ?string $actualStartAt,
        ?string $actualEndAt
    ): void {
        if (Carbon::parse($plannedEndAt)->lte(Carbon::parse($plannedStartAt))) {
            throw ValidationException::withMessages([
                'planned_end_at' => 'Waktu selesai rencana harus setelah waktu mulai rencana.',
            ]);
        }

        if (
            $actualStartAt !== null
            && $actualEndAt !== null
            && Carbon::parse($actualEndAt)->lt(Carbon::parse($actualStartAt))
        ) {
            throw ValidationException::withMessages([
                'actual_end_at' => 'Waktu selesai aktual tidak boleh sebelum waktu mulai aktual.',
            ]);
        }
    }

    private function validateReplacement(LessonSession $source, array $data, ?string $currentId = null): void
    {
        if ($source->id === $currentId || $source->branch_id !== $data['branch_id']
            || $source->program_id !== $data['program_id']
            || $source->class_group_id !== ($data['class_group_id'] ?? null)) {
            throw ValidationException::withMessages(['rescheduled_from_session_id' => 'Jadwal pengganti harus mengacu ke sesi berbeda pada cabang, program, dan kelas yang sama.']);
        }
        if ($source->sessionStudents()->whereNotNull('attendance_status')->where('attendance_status', '!=', 'rescheduled')->exists()) {
            throw ValidationException::withMessages(['rescheduled_from_session_id' => 'Koreksi kehadiran sesi asal menjadi Dijadwalkan Ulang sebelum membuat pengganti.']);
        }
        if ($source->replacementSessions()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
            ->whereNotIn('status', ['cancelled', 'canceled', 'dibatalkan'])->exists()) {
            throw ValidationException::withMessages(['rescheduled_from_session_id' => 'Sesi asal sudah memiliki jadwal pengganti.']);
        }
        $seen = [];
        $cursor = $source;
        while ($cursor) {
            if ($cursor->id === $currentId || in_array($cursor->id, $seen, true)) {
                throw ValidationException::withMessages(['rescheduled_from_session_id' => 'Referensi jadwal ulang tidak boleh membentuk putaran.']);
            }
            $seen[] = $cursor->id;
            $cursor = $cursor->rescheduled_from_session_id ? LessonSession::query()->find($cursor->rescheduled_from_session_id) : null;
        }
    }
}
