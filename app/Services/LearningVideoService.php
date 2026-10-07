<?php

namespace App\Services;

use App\Models\LearningVideo;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningVideoService
{
    public function create(array $data): LearningVideo
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

            return LearningVideo::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session?->id,
                'video_on' => $data['video_on'],
                'topic' => $data['topic'],
                'description' => $data['description'] ?? null,
                'video_url' => $data['video_url'],
            ]);
        });
    }

    public function update(
        LearningVideo $learningVideo,
        array $data
    ): LearningVideo {
        return DB::transaction(function () use (
            $learningVideo,
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

            $learningVideo->update([
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session?->id,
                'video_on' => $data['video_on'],
                'topic' => $data['topic'],
                'description' => $data['description'] ?? null,
                'video_url' => $data['video_url'],
            ]);

            return $learningVideo->refresh();
        });
    }

    public function delete(
        LearningVideo $learningVideo
    ): void {
        DB::transaction(function () use ($learningVideo) {
            $learningVideo->delete();
        });
    }
}
