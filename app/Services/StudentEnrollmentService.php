<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentEnrollmentService
{
    public function create(array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($data) {
            $student = Student::query()
                ->findOrFail($data['student_id']);

            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $program = Program::query()
                ->findOrFail($data['program_id']);

            $this->validateReferences(
                $student,
                $branch,
                $program
            );

            $this->validateDateRange(
                $data['starts_on'],
                $data['ends_on'] ?? null
            );

            $this->validateEnrollmentOverlap(
                $student,
                $data['starts_on'],
                $data['ends_on'] ?? null
            );

            return StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'branch_id' => $branch->id,
                'program_id' => $program->id,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
            ]);
        });
    }

    public function update(
        StudentEnrollment $enrollment,
        array $data
    ): StudentEnrollment {
        return DB::transaction(function () use (
            $enrollment,
            $data
        ) {
            $student = Student::query()
                ->findOrFail($data['student_id']);

            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $program = Program::query()
                ->findOrFail($data['program_id']);

            $this->validateReferences(
                $student,
                $branch,
                $program
            );

            $this->validateDateRange(
                $data['starts_on'],
                $data['ends_on'] ?? null
            );

            $this->validateEnrollmentOverlap(
                $student,
                $data['starts_on'],
                $data['ends_on'] ?? null,
                $enrollment->id
            );

            $enrollment->update([
                'student_id' => $student->id,
                'branch_id' => $branch->id,
                'program_id' => $program->id,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
            ]);

            return $enrollment->refresh();
        });
    }

    public function delete(
        StudentEnrollment $enrollment
    ): void {
        DB::transaction(function () use ($enrollment) {
            $enrollment->delete();
        });
    }

    private function validateReferences(
        Student $student,
        Branch $branch,
        Program $program
    ): void {
        if ($student->status !== 'active') {
            throw ValidationException::withMessages([
                'student_id' =>
                    'Student tidak aktif dan tidak dapat diberikan enrollment.',
            ]);
        }

        if (! $branch->is_active) {
            throw ValidationException::withMessages([
                'branch_id' =>
                    'Branch tidak aktif dan tidak dapat menerima enrollment.',
            ]);
        }

        if (! $program->is_active) {
            throw ValidationException::withMessages([
                'program_id' =>
                    'Program tidak aktif dan tidak dapat digunakan untuk enrollment.',
            ]);
        }
    }

    private function validateDateRange(
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

    private function validateEnrollmentOverlap(
        Student $student,
        string $startsOn,
        ?string $endsOn,
        ?string $ignoreId = null
    ): void {
        $hasOverlap = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->when(
                $ignoreId,
                fn ($query) =>
                    $query->where('id', '!=', $ignoreId)
            )
            ->where(function ($query) use (
                $startsOn,
                $endsOn
            ) {
                $query
                    ->whereDate(
                        'starts_on',
                        '<=',
                        $endsOn ?? '9999-12-31'
                    )
                    ->where(function ($query) use (
                        $startsOn
                    ) {
                        $query
                            ->whereNull('ends_on')
                            ->orWhereDate(
                                'ends_on',
                                '>=',
                                $startsOn
                            );
                    });
            })
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'student_id' =>
                    'Student sudah memiliki enrollment pada periode tersebut.',
            ]);
        }
    }
}