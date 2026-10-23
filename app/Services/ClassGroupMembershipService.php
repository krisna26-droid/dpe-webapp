<?php

namespace App\Services;

use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LessonSession;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassGroupMembershipService
{
    public function create(array $data): ClassGroupMembership
    {
        return DB::transaction(function () use ($data) {
            $classGroup = ClassGroup::query()
                ->lockForUpdate()
                ->findOrFail($data['class_group_id']);

            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($data['student_id']);

            $this->validateReferences($classGroup, $student);

            $this->validateDates($data);
            $this->validateEnrollmentAndOverlap($classGroup, $student, $data);

            return ClassGroupMembership::query()->create([
                'id' => (string) Str::uuid(),
                'class_group_id' => $classGroup->id,
                'student_id' => $student->id,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
            ]);
        });
    }

    public function update(
        ClassGroupMembership $membership,
        array $data
    ): ClassGroupMembership {
        return DB::transaction(function () use ($membership, $data) {
            $membership = ClassGroupMembership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $this->validateHistory($membership, $data);
            $classGroup = ClassGroup::query()
                ->lockForUpdate()
                ->findOrFail($data['class_group_id']);

            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($data['student_id']);

            $this->validateReferences($classGroup, $student, $membership);

            $this->validateDates($data);
            $this->validateEnrollmentAndOverlap($classGroup, $student, $data, $membership->id);

            $membership->update([
                'class_group_id' => $classGroup->id,
                'student_id' => $student->id,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
            ]);

            return $membership->refresh();
        });
    }

    private function validateReferences(
        ClassGroup $classGroup,
        Student $student,
        ?ClassGroupMembership $current = null
    ): void {
        if (! $classGroup->is_active && $current?->class_group_id !== $classGroup->id) {
            throw ValidationException::withMessages([
                'class_group_id' => 'Class group tidak aktif dan tidak dapat digunakan untuk membership baru.',
            ]);
        }

        if ($student->status !== 'active' && $current?->student_id !== $student->id) {
            throw ValidationException::withMessages([
                'student_id' => 'Student tidak aktif dan tidak dapat digunakan untuk membership.',
            ]);
        }
    }

    private function validateDates(array $data): void
    {
        if (
            ! empty($data['ends_on'])
            && $data['ends_on'] < $data['starts_on']
        ) {
            throw ValidationException::withMessages([
                'ends_on' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
            ]);
        }
    }

    private function validateHistory(ClassGroupMembership $current, array $data): void
    {
        $sessions = LessonSession::where('class_group_id', $current->class_group_id)
            ->whereHas('sessionStudents', fn ($q) => $q->where('student_id', $current->student_id)
                ->where(fn ($q) => $q->whereNotNull('attendance_status')->orWhereNotNull('recorded_at')
                    ->orWhereNotNull('individual_learning_note')))->with('branch')->get();
        $attendance = app(StudentAttendanceService::class);
        $start = CarbonImmutable::parse($data['starts_on'])->toDateString();
        $end = isset($data['ends_on']) ? CarbonImmutable::parse($data['ends_on'])->toDateString() : '9999-12-31';
        foreach ($sessions as $session) {
            $day = $attendance->meetingAt($session)->toDateString();
            if (! $attendance->covers($current, $day)) { continue; }
            if ($current->class_group_id !== $data['class_group_id'] || $current->student_id !== $data['student_id']) {
                throw ValidationException::withMessages(['class_group_id' => 'Keanggotaan dengan absensi/catatan tersimpan tidak dapat dipindahkan ke kelas atau siswa lain. Akhiri periode dan buat keanggotaan baru.']);
            }
            if ($day < $start || $day > $end) {
                throw ValidationException::withMessages(['starts_on' => 'Periode keanggotaan harus tetap mencakup tanggal absensi/catatan yang sudah tersimpan.']);
            }
        }
    }

    private function validateEnrollmentAndOverlap(ClassGroup $group, Student $student, array $data, ?string $ignoreId = null): void
    {
        $start = CarbonImmutable::parse($data['starts_on'])->toDateString();
        $end = isset($data['ends_on']) ? CarbonImmutable::parse($data['ends_on'])->toDateString() : '9999-12-31';
        $effectiveStart = max($start, $student->began_on->toDateString());
        $enrollments = $student->enrollments()->where('branch_id', $group->branch_id)->where('program_id', $group->program_id)
            ->whereDate('starts_on', '<=', $end)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $effectiveStart))->get();
        if ($effectiveStart > $end || ! $enrollments->contains(fn ($enrollment) => max($effectiveStart, $enrollment->starts_on->toDateString())
            <= min($end, $enrollment->ends_on?->toDateString() ?? $end))) {
            throw ValidationException::withMessages(['student_id' => 'Daftarkan siswa pada cabang dan program kelas ini untuk periode keanggotaan yang sesuai.']);
        }
        if (ClassGroupMembership::where('class_group_id', $group->id)->where('student_id', $student->id)
            ->whereDate('starts_on', '<=', $end)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            throw ValidationException::withMessages(['student_id' => 'Siswa sudah memiliki keanggotaan kelas ini pada periode yang bertumpang tindih.']);
        }
    }
}
