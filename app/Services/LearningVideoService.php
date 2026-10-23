<?php

namespace App\Services;

use App\Models\LearningVideo;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LearningVideoService
{
    public function create(array $data): LearningVideo
    {
        return DB::transaction(function () use ($data) {
            $this->validateUrl($data);
            app(MonthlyProgressLockService::class)->videoPeriods([$data]);
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
            $this->validateUrl($data);
            $learningVideo = $this->lockVideo($learningVideo, $data);
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
            $learningVideo = $this->lockVideo($learningVideo);
            $learningVideo->delete();
        });
    }

    private function validateUrl(array $data): void
    {
        if (! StudentProgressService::safeUrl($data['video_url'] ?? null) || mb_strlen($data['video_url']) > 2048) {
            throw ValidationException::withMessages(['video_url' => 'Gunakan tautan video HTTP atau HTTPS yang valid, maksimal 2.048 karakter.']);
        }
    }

    private function lockVideo(LearningVideo $video, ?array $destination = null): LearningVideo
    {
        $seen = LearningVideo::whereKey($video->id)->firstOrFail();
        $periods = [['student_id' => $seen->student_id, 'video_on' => $seen->video_on->toDateString()]];
        if ($destination) {
            $periods[] = $destination;
        }
        app(MonthlyProgressLockService::class)->videoPeriods($periods);
        $locked = LearningVideo::whereKey($video->id)->lockForUpdate()->firstOrFail();
        if ($locked->student_id !== $seen->student_id || $locked->video_on->toDateString() !== $seen->video_on->toDateString()) {
            throw ValidationException::withMessages(['status' => 'Periode video berubah. Muat data terbaru sebelum mengubah atau menghapus tautan.']);
        }

        return $locked;
    }
}
