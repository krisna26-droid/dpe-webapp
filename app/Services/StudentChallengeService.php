<?php

namespace App\Services;

use App\Models\LessonSession;
use App\Models\Student;
use App\Models\StudentChallenge;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentChallengeService
{
    public function create(array $data): StudentChallenge
    {
        return DB::transaction(function () use ($data) {
            $student = Student::query()
                ->findOrFail($data['student_id']);

            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

            $session = null;

            if (
                array_key_exists('session_id', $data)
                && $data['session_id'] !== null
            ) {
                $session = LessonSession::query()
                    ->findOrFail($data['session_id']);
            }

            return StudentChallenge::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session?->id,
                'category_name' => $data['category_name'],
                'internal_note' => $data['internal_note'] ?? null,
                'logged_on' => $data['logged_on'],
            ]);
        });
    }

    public function update(
        StudentChallenge $studentChallenge,
        array $data
    ): StudentChallenge {
        return DB::transaction(function () use (
            $studentChallenge,
            $data
        ) {
            $student = Student::query()
                ->findOrFail($data['student_id']);

            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

            $session = null;

            if (
                array_key_exists('session_id', $data)
                && $data['session_id'] !== null
            ) {
                $session = LessonSession::query()
                    ->findOrFail($data['session_id']);
            }

            $studentChallenge->update([
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session?->id,
                'category_name' => $data['category_name'],
                'internal_note' => $data['internal_note'] ?? null,
                'logged_on' => $data['logged_on'],
            ]);

            return $studentChallenge->refresh();
        });
    }

    public function delete(
        StudentChallenge $studentChallenge
    ): void {
        DB::transaction(function () use ($studentChallenge) {
            $studentChallenge->delete();
        });
    }
}
