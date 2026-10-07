<?php

namespace App\Services;

use App\Models\LessonSession;
use App\Models\SessionStudent;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class SessionStudentService
{
    public function create(array $data): SessionStudent
    {
        return DB::transaction(function () use ($data) {
            return SessionStudent::query()->create([
                'session_id' => $data['session_id'],
                'student_id' => $data['student_id'],
                'attendance_status' => $data['attendance_status'] ?? null,
                'individual_learning_note' => $data['individual_learning_note'] ?? null,
                'recorded_at' => $data['recorded_at'] ?? null,
            ]);
        });
    }

    public function find(
        string $sessionId,
        string $studentId
    ): SessionStudent {
        return SessionStudent::query()
            ->where('session_id', $sessionId)
            ->where('student_id', $studentId)
            ->firstOrFail();
    }

    public function getBySession(LessonSession $session)
    {
        return SessionStudent::query()
            ->where('session_id', $session->id)
            ->get();
    }

    public function getByStudent(Student $student)
    {
        return SessionStudent::query()
            ->where('student_id', $student->id)
            ->get();
    }

    public function update(
        string $sessionId,
        string $studentId,
        array $data
    ): SessionStudent {
        return DB::transaction(function () use (
            $sessionId,
            $studentId,
            $data
        ) {
            $this->find($sessionId, $studentId);

            DB::table('session_students')
                ->where('session_id', $sessionId)
                ->where('student_id', $studentId)
                ->update([
                    'session_id' => $data['session_id'],
                    'student_id' => $data['student_id'],
                    'attendance_status' =>
                    $data['attendance_status'] ?? null,
                    'individual_learning_note' =>
                    $data['individual_learning_note'] ?? null,
                    'recorded_at' =>
                    $data['recorded_at'] ?? null,
                ]);

            return $this->find(
                $data['session_id'],
                $data['student_id']
            );
        });
    }

    public function delete(
        string $sessionId,
        string $studentId
    ): void {
        DB::transaction(function () use (
            $sessionId,
            $studentId
        ) {
            $this->find($sessionId, $studentId);

            DB::table('session_students')
                ->where('session_id', $sessionId)
                ->where('student_id', $studentId)
                ->delete();
        });
    }
}
