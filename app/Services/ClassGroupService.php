<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\Program;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassGroupService
{
    public function create(array $data): ClassGroup
    {
        return DB::transaction(function () use ($data) {
            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $program = Program::query()
                ->findOrFail($data['program_id']);

            $teacher = null;

            if (
                array_key_exists('default_teacher_id', $data)
                && $data['default_teacher_id'] !== null
            ) {
                $teacher = Teacher::query()
                    ->findOrFail($data['default_teacher_id']);
            }

            $this->validateReferences(
                $branch,
                $program,
                $teacher
            );

            return ClassGroup::query()->create([
                'id' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'program_id' => $program->id,
                'default_teacher_id' => $teacher?->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : true,
            ]);
        });
    }

    public function update(
        ClassGroup $classGroup,
        array $data
    ): ClassGroup {
        return DB::transaction(function () use (
            $classGroup,
            $data
        ) {
            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $program = Program::query()
                ->findOrFail($data['program_id']);

            $teacher = null;

            if (
                array_key_exists('default_teacher_id', $data)
                && $data['default_teacher_id'] !== null
            ) {
                $teacher = Teacher::query()
                    ->findOrFail($data['default_teacher_id']);
            }

            $this->validateReferences(
                $branch,
                $program,
                $teacher
            );

            $classGroup->update([
                'branch_id' => $branch->id,
                'program_id' => $program->id,
                'default_teacher_id' => $teacher?->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $classGroup->is_active,
            ]);

            return $classGroup->refresh();
        });
    }

    public function toggleStatus(ClassGroup $classGroup): ClassGroup
    {
        return DB::transaction(function () use ($classGroup) {
            $classGroup->update([
                'is_active' => ! $classGroup->is_active,
            ]);

            return $classGroup->refresh();
        });
    }

    private function validateReferences(
        Branch $branch,
        Program $program,
        ?Teacher $teacher
    ): void {
        if (! $branch->is_active) {
            throw ValidationException::withMessages([
                'branch_id' =>
                    'Branch tidak aktif dan tidak dapat digunakan untuk class group.',
            ]);
        }

        if (! $program->is_active) {
            throw ValidationException::withMessages([
                'program_id' =>
                    'Program tidak aktif dan tidak dapat digunakan untuk class group.',
            ]);
        }

        if (
            $teacher !== null
            && ! $teacher->is_active
        ) {
            throw ValidationException::withMessages([
                'default_teacher_id' =>
                    'Teacher tidak aktif dan tidak dapat menjadi default teacher.',
            ]);
        }
    }
}