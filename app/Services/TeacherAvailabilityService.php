<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeacherAvailabilityService
{
    public function create(array $data): TeacherAvailability
    {
        return DB::transaction(function () use ($data) {
            $teacher = Teacher::query()
                ->lockForUpdate()
                ->findOrFail($data['teacher_id']);

            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $this->validateTimeRange(
                $data['starts_at'],
                $data['ends_at']
            );

            return TeacherAvailability::query()->create([
                'id' => (string) Str::uuid(),
                'teacher_id' => $teacher->id,
                'branch_id' => $branch->id,
                'available_on' => $data['available_on'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'class_type' => $data['class_type'],
                'availability_status' => $data['availability_status'],
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function update(
        TeacherAvailability $availability,
        array $data
    ): TeacherAvailability {
        return DB::transaction(function () use (
            $availability,
            $data
        ) {
            // Serialize availability writes with the scheduling guard for the involved Teachers.
            $teacherIds = array_unique([$availability->teacher_id, $data['teacher_id']]);
            $teachers = Teacher::query()->whereIn('id', $teacherIds)->orderBy('id')->lockForUpdate()->get();
            $teacher = $teachers->firstWhere('id', $data['teacher_id']);
            if (! $teacher) { throw (new ModelNotFoundException)->setModel(Teacher::class, [$data['teacher_id']]); }
            $availability = TeacherAvailability::query()->whereKey($availability->id)->lockForUpdate()->firstOrFail();

            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $this->validateTimeRange(
                $data['starts_at'],
                $data['ends_at']
            );

            $availability->update([
                'teacher_id' => $teacher->id,
                'branch_id' => $branch->id,
                'available_on' => $data['available_on'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'class_type' => $data['class_type'],
                'availability_status' => $data['availability_status'],
                'notes' => $data['notes'] ?? null,
            ]);

            return $availability->refresh();
        });
    }

    private function validateTimeRange(
        string $startsAt,
        string $endsAt
    ): void {
        if ($endsAt <= $startsAt) {
            throw ValidationException::withMessages([
                'ends_at' =>
                    'Waktu selesai harus setelah waktu mulai.',
            ]);
        }
    }
}
