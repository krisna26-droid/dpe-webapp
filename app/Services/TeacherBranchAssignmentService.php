<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeacherBranchAssignmentService
{
    public function create(array $data): TeacherBranchAssignment
    {
        return DB::transaction(function () use ($data) {
            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $this->validateTeacher($teacher);

            $this->validateBranch($branch);

            $this->validateDateRange(
                $data['starts_on'],
                $data['ends_on'] ?? null
            );

            $this->validateOverlap(
                $teacher->id,
                $data['starts_on'],
                $data['ends_on'] ?? null
            );

            return TeacherBranchAssignment::query()->create([
                'id' => (string) Str::uuid(),
                'teacher_id' => $teacher->id,
                'branch_id' => $branch->id,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
            ]);
        });
    }

    public function update(
        TeacherBranchAssignment $assignment,
        array $data
    ): TeacherBranchAssignment {
        return DB::transaction(function () use ($assignment, $data) {
            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $this->validateTeacher($teacher);

            $this->validateBranch($branch);

            $this->validateDateRange(
                $data['starts_on'],
                $data['ends_on'] ?? null
            );

            $this->validateOverlap(
                $teacher->id,
                $data['starts_on'],
                $data['ends_on'] ?? null,
                $assignment->id
            );

            $assignment->update([
                'teacher_id' => $teacher->id,
                'branch_id' => $branch->id,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
            ]);

            return $assignment->refresh();
        });
    }

    public function delete(TeacherBranchAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment) {
            $assignment->delete();
        });
    }

    private function validateTeacher(Teacher $teacher): void
    {
        if (! $teacher->is_active) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Guru yang dipilih tidak aktif.',
            ]);
        }
    }

    private function validateBranch(Branch $branch): void
    {
        if (! $branch->is_active) {
            throw ValidationException::withMessages([
                'branch_id' => 'Branch yang dipilih tidak aktif.',
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
                'ends_on' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            ]);
        }
    }

    private function validateOverlap(
        string $teacherId,
        string $startsOn,
        ?string $endsOn,
        ?string $ignoreAssignmentId = null
    ): void {
        $query = TeacherBranchAssignment::query()
            ->where('teacher_id', $teacherId)
            ->when(
                $ignoreAssignmentId !== null,
                fn ($query) => $query->where(
                    'id',
                    '!=',
                    $ignoreAssignmentId
                )
            )
            ->where(function ($query) use ($startsOn, $endsOn) {
                $query
                    ->where(function ($query) use ($startsOn, $endsOn) {
                        $query
                            ->whereDate('starts_on', '<=', $startsOn)
                            ->where(function ($query) use ($startsOn) {
                                $query
                                    ->whereNull('ends_on')
                                    ->orWhereDate(
                                        'ends_on',
                                        '>=',
                                        $startsOn
                                    );
                            });
                    })
                    ->orWhere(function ($query) use ($endsOn) {
                        if ($endsOn !== null) {
                            $query
                                ->whereDate('starts_on', '<=', $endsOn)
                                ->where(function ($query) use ($endsOn) {
                                    $query
                                        ->whereNull('ends_on')
                                        ->orWhereDate(
                                            'ends_on',
                                            '>=',
                                            $endsOn
                                        );
                                });
                        }
                    })
                    ->orWhere(function ($query) use ($startsOn, $endsOn) {
                        $query
                            ->whereDate('starts_on', '>=', $startsOn)
                            ->when(
                                $endsOn !== null,
                                fn ($query) => $query->whereDate(
                                    'starts_on',
                                    '<=',
                                    $endsOn
                                )
                            );
                    });
            });

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Guru sudah memiliki assignment branch pada rentang tanggal tersebut.',
            ]);
        }
    }
}