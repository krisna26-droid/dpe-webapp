<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentTeacherAssignmentService
{
    public function create(array $data): StudentTeacherAssignment
    {
        return DB::transaction(function () use ($data) {
            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($data['student_id']);

            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

            $this->validateReferences($student, $teacher);
            $this->validateOpenReportOwner($data);

            $this->validateDates(
                $data['starts_on'],
                $data['ends_on'] ?? null
            );

            return StudentTeacherAssignment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'is_report_owner' => $data['is_report_owner'] ?? false,
            ]);
        });
    }

    public function update(
        StudentTeacherAssignment $assignment,
        array $data
    ): StudentTeacherAssignment {
        return DB::transaction(function () use (
            $assignment,
            $data
        ) {
            $assignment = StudentTeacherAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($data['student_id']);

            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

            $this->validateReferences($student, $teacher, $assignment);
            $this->validateOpenReportOwner($data, $assignment->id);

            $this->validateDates(
                $data['starts_on'],
                $data['ends_on'] ?? null
            );

            $assignment->update([
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'is_report_owner' => $data['is_report_owner'] ?? false,
            ]);

            return $assignment->refresh();
        });
    }

    private function validateDates(
        string $startsOn,
        ?string $endsOn
    ): void {
        if (
            $endsOn !== null
            && $endsOn < $startsOn
        ) {
            throw ValidationException::withMessages([
                'ends_on' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            ]);
        }
    }

    private function validateReferences(Student $student, Teacher $teacher, ?StudentTeacherAssignment $current = null): void
    {
        if ($current?->student_id !== $student->id && $student->status !== 'active') {
            throw ValidationException::withMessages(['student_id' => 'Pilih siswa aktif untuk penugasan baru.']);
        }
        if ($current?->teacher_id !== $teacher->id && (! $teacher->is_active || ! $teacher->user?->is_active || $teacher->user?->role_code !== 'teacher')) {
            throw ValidationException::withMessages(['teacher_id' => 'Pilih Teacher dengan profil dan akun aktif untuk penugasan baru.']);
        }
    }

    private function validateOpenReportOwner(array $data, ?string $ignoreId = null): void
    {
        if (($data['is_report_owner'] ?? false) && empty($data['ends_on'])
            && StudentTeacherAssignment::where('student_id', $data['student_id'])->where('is_report_owner', true)->whereNull('ends_on')
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            throw ValidationException::withMessages(['is_report_owner' => 'Akhiri periode pemilik laporan sebelumnya sebelum menambahkan pemilik baru tanpa tanggal berakhir.']);
        }
    }
}
