<?php

namespace App\Services;

use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassGroupMembershipService
{
    public function create(array $data): ClassGroupMembership
    {
        return DB::transaction(function () use ($data) {
            $classGroup = ClassGroup::query()
                ->findOrFail($data['class_group_id']);

            $student = Student::query()
                ->findOrFail($data['student_id']);

            $this->validateReferences($classGroup, $student);

            $this->validateDates($data);

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
            $classGroup = ClassGroup::query()
                ->findOrFail($data['class_group_id']);

            $student = Student::query()
                ->findOrFail($data['student_id']);

            $this->validateReferences($classGroup, $student);

            $this->validateDates($data);

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
        Student $student
    ): void {
        if (! $classGroup->is_active) {
            throw ValidationException::withMessages([
                'class_group_id' =>
                    'Class group tidak aktif dan tidak dapat digunakan untuk membership baru.',
            ]);
        }

        if ($student->status !== 'active') {
            throw ValidationException::withMessages([
                'student_id' =>
                    'Student tidak aktif dan tidak dapat digunakan untuk membership.',
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
                'ends_on' =>
                    'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
            ]);
        }
    }
}