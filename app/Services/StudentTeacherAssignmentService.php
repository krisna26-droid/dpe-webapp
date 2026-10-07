<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentTeacherAssignmentService
{
    public function create(array $data): StudentTeacherAssignment
    {
        return DB::transaction(function () use ($data) {
            $student = Student::query()
                ->findOrFail($data['student_id']);

            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

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
            $student = Student::query()
                ->findOrFail($data['student_id']);

            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

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
                'ends_on' =>
                    'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            ]);
        }
    }

}