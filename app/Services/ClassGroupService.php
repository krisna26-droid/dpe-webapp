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
            $classGroup = ClassGroup::whereKey($classGroup->id)->lockForUpdate()->firstOrFail();
            if (($classGroup->memberships()->exists() || $classGroup->lessonSessions()->exists())
                && ($classGroup->branch_id !== $data['branch_id'] || $classGroup->program_id !== $data['program_id'])) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Cabang dan program kelas yang sudah memiliki peserta atau sesi tidak dapat dipindahkan. Buat kelompok baru untuk cabang/program lain.',
                ]);
            }
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
                $teacher,
                $classGroup
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
            $classGroup = ClassGroup::whereKey($classGroup->id)->lockForUpdate()->firstOrFail();
            $classGroup->update([
                'is_active' => ! $classGroup->is_active,
            ]);

            return $classGroup->refresh();
        });
    }

    private function validateReferences(
        Branch $branch,
        Program $program,
        ?Teacher $teacher,
        ?ClassGroup $current = null
    ): void {
        if (! $branch->is_active && $current?->branch_id !== $branch->id) {
            throw ValidationException::withMessages([
                'branch_id' =>
                    'Branch tidak aktif dan tidak dapat digunakan untuk class group.',
            ]);
        }

        if (! $program->is_active && $current?->program_id !== $program->id) {
            throw ValidationException::withMessages([
                'program_id' =>
                    'Program tidak aktif dan tidak dapat digunakan untuk class group.',
            ]);
        }

        if (
            $teacher !== null
            && $current?->default_teacher_id !== $teacher->id
            && (! $teacher->is_active || ! $teacher->user?->is_active || $teacher->user?->role_code !== 'teacher')
        ) {
            throw ValidationException::withMessages([
                'default_teacher_id' =>
                    'Teacher tidak aktif dan tidak dapat menjadi default teacher.',
            ]);
        }
    }
}
