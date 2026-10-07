<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuardianService
{
    public function create(array $data): Guardian
    {
        return DB::transaction(function () use ($data) {
            $student = Student::query()
                ->findOrFail($data['student_id']);

            return Guardian::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'full_name' => $data['full_name'],
                'relationship_name' => $data['relationship_name'] ?? null,
                'whatsapp_number' => $data['whatsapp_number'],
                'is_primary' => $data['is_primary'] ?? false,
            ]);
        });
    }

    public function update(
        Guardian $guardian,
        array $data
    ): Guardian {
        return DB::transaction(function () use (
            $guardian,
            $data
        ) {
            $student = Student::query()
                ->findOrFail($data['student_id']);

            $guardian->update([
                'student_id' => $student->id,
                'full_name' => $data['full_name'],
                'relationship_name' =>
                $data['relationship_name'] ?? null,
                'whatsapp_number' => $data['whatsapp_number'],
                'is_primary' => $data['is_primary'] ?? false,
            ]);

            return $guardian->refresh();
        });
    }

    public function delete(Guardian $guardian): void
    {
        DB::transaction(function () use ($guardian) {
            $guardian->delete();
        });
    }
}
