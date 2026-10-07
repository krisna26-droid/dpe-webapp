<?php

namespace App\Services;

use App\Models\FileAsset;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeacherMessageService
{
    public function create(array $data): TeacherMessage
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

            $attachment = null;

            if (
                array_key_exists('attachment_file_id', $data)
                && $data['attachment_file_id'] !== null
            ) {
                $attachment = FileAsset::query()
                    ->findOrFail($data['attachment_file_id']);
            }

            return TeacherMessage::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session?->id,
                'message_body' => $data['message_body'],
                'attachment_file_id' => $attachment?->id,
            ]);
        });
    }

    public function update(
        TeacherMessage $teacherMessage,
        array $data
    ): TeacherMessage {
        return DB::transaction(function () use (
            $teacherMessage,
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

            $attachment = null;

            if (
                array_key_exists('attachment_file_id', $data)
                && $data['attachment_file_id'] !== null
            ) {
                $attachment = FileAsset::query()
                    ->findOrFail($data['attachment_file_id']);
            }

            $teacherMessage->update([
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session?->id,
                'message_body' => $data['message_body'],
                'attachment_file_id' => $attachment?->id,
            ]);

            return $teacherMessage->refresh();
        });
    }

    public function delete(
        TeacherMessage $teacherMessage
    ): void {
        DB::transaction(function () use ($teacherMessage) {
            $teacherMessage->delete();
        });
    }
}
