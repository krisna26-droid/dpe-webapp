<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentService
{
    public function create(array $data): Student
    {
        return DB::transaction(fn () => Student::create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $data['portal_user_id'],
            'full_name' => $data['full_name'],
            'school_name' => $data['school_name'] ?? null,
            'grade_name' => $data['grade_name'] ?? null,
            'began_on' => $data['began_on'],
            'status' => 'active',
            'special_notes_internal' => $data['special_notes_internal'] ?? null,
        ]));
    }

    public function update(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data) {
            $student->update([
                'portal_user_id' => $data['portal_user_id'],
                'full_name' => $data['full_name'],
                'school_name' => $data['school_name'] ?? null,
                'grade_name' => $data['grade_name'] ?? null,
                'began_on' => $data['began_on'],
                'special_notes_internal' => $data['special_notes_internal'] ?? null,
            ]);

            return $student->refresh();
        });
    }
}