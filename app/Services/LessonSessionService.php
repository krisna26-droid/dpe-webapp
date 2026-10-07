<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LessonSessionService
{
    public function create(array $data): LessonSession
    {
        return DB::transaction(function () use ($data) {
            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

            $program = Program::query()
                ->findOrFail($data['program_id']);

            $classGroup = null;

            if (
                array_key_exists('class_group_id', $data)
                && $data['class_group_id'] !== null
            ) {
                $classGroup = ClassGroup::query()
                    ->findOrFail($data['class_group_id']);
            }

            $rescheduledFrom = null;

            if (
                array_key_exists('rescheduled_from_session_id', $data)
                && $data['rescheduled_from_session_id'] !== null
            ) {
                $rescheduledFrom = LessonSession::query()
                    ->findOrFail($data['rescheduled_from_session_id']);
            }

            $this->validateTimeRange(
                $data['planned_start_at'],
                $data['planned_end_at'],
                $data['actual_start_at'] ?? null,
                $data['actual_end_at'] ?? null
            );

            return LessonSession::query()->create([
                'id' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'teacher_id' => $teacher->id,
                'program_id' => $program->id,
                'class_group_id' => $classGroup?->id,
                'planned_start_at' => $data['planned_start_at'],
                'planned_end_at' => $data['planned_end_at'],
                'actual_start_at' => $data['actual_start_at'] ?? null,
                'actual_end_at' => $data['actual_end_at'] ?? null,
                'status' => $data['status'],
                'rescheduled_from_session_id' => $rescheduledFrom?->id,
                'topic' => $data['topic'] ?? null,
                'material' => $data['material'] ?? null,
                'activity' => $data['activity'] ?? null,
            ]);
        });
    }

    public function update(
        LessonSession $lessonSession,
        array $data
    ): LessonSession {
        return DB::transaction(function () use (
            $lessonSession,
            $data
        ) {
            $branch = Branch::query()
                ->findOrFail($data['branch_id']);

            $teacher = Teacher::query()
                ->findOrFail($data['teacher_id']);

            $program = Program::query()
                ->findOrFail($data['program_id']);

            $classGroup = null;

            if (
                array_key_exists('class_group_id', $data)
                && $data['class_group_id'] !== null
            ) {
                $classGroup = ClassGroup::query()
                    ->findOrFail($data['class_group_id']);
            }

            $rescheduledFrom = null;

            if (
                array_key_exists('rescheduled_from_session_id', $data)
                && $data['rescheduled_from_session_id'] !== null
            ) {
                $rescheduledFrom = LessonSession::query()
                    ->findOrFail($data['rescheduled_from_session_id']);
            }

            $this->validateTimeRange(
                $data['planned_start_at'],
                $data['planned_end_at'],
                $data['actual_start_at'] ?? null,
                $data['actual_end_at'] ?? null
            );

            $lessonSession->update([
                'branch_id' => $branch->id,
                'teacher_id' => $teacher->id,
                'program_id' => $program->id,
                'class_group_id' => $classGroup?->id,
                'planned_start_at' => $data['planned_start_at'],
                'planned_end_at' => $data['planned_end_at'],
                'actual_start_at' => $data['actual_start_at'] ?? null,
                'actual_end_at' => $data['actual_end_at'] ?? null,
                'status' => $data['status'],
                'rescheduled_from_session_id' => $rescheduledFrom?->id,
                'topic' => $data['topic'] ?? null,
                'material' => $data['material'] ?? null,
                'activity' => $data['activity'] ?? null,
            ]);

            return $lessonSession->refresh();
        });
    }

    private function validateTimeRange(
        string $plannedStartAt,
        string $plannedEndAt,
        ?string $actualStartAt,
        ?string $actualEndAt
    ): void {
        if ($plannedEndAt <= $plannedStartAt) {
            throw ValidationException::withMessages([
                'planned_end_at' =>
                    'Waktu selesai rencana harus setelah waktu mulai rencana.',
            ]);
        }

        if (
            $actualStartAt !== null
            && $actualEndAt !== null
            && $actualEndAt < $actualStartAt
        ) {
            throw ValidationException::withMessages([
                'actual_end_at' =>
                    'Waktu selesai aktual tidak boleh sebelum waktu mulai aktual.',
            ]);
        }
    }
}