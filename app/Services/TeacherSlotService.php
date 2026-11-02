<?php

namespace App\Services;

use App\Models\ClassLearningSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherSlotService
{
    public function __construct(
        private BranchOperationsService $operations,
        private ClassLearningSessionService $learningSessions
    ) {}

    /**
     * Return one row for each learning session that already has a schedule.
     * Schedule day and time are read from lesson_sessions; only capacity is
     * maintained from this screen.
     */
    public function rows(User $actor): Collection
    {
        $groupIds = $this->operations->groups($actor)
            ->where('is_active', true)
            ->select('id');

        $sessions = ClassLearningSession::query()
            ->whereIn('class_group_id', $groupIds)
            ->whereHas('lessonSessions', fn ($query) => $query->whereNotIn('status', [
                'cancelled', 'canceled', 'dibatalkan',
            ]))
            ->with([
                'classGroup.branch',
                'classGroup.program',
                'classGroup.memberships.learningSessionEnrollments',
                'lessonSessions' => fn ($query) => $query
                    ->whereNotIn('status', ['cancelled', 'canceled', 'dibatalkan'])
                    ->with(['teacher.user', 'rescheduledFrom'])
                    ->orderBy('planned_start_at'),
            ])
            ->get();

        return $sessions
            ->map(fn (ClassLearningSession $session) => $this->present($session))
            ->sortBy(fn (array $row) => mb_strtolower(
                ($row['teacher_name'] ?: 'Guru').'|'.$row['session_name'].'|'.$row['class_group_name']
            ))
            ->values();
    }

    public function authorize(User $actor, ClassLearningSession $session): void
    {
        $group = $session->classGroup()->first();
        abort_unless($group, 404);
        $this->operations->authorize($actor, $group);
    }

    public function update(User $actor, ClassLearningSession $session, int $capacity): ClassLearningSession
    {
        return DB::transaction(function () use ($actor, $session, $capacity) {
            $current = ClassLearningSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();
            $current->load([
                'classGroup.memberships.learningSessionEnrollments',
                'lessonSessions' => fn ($query) => $query
                    ->whereNotIn('status', ['cancelled', 'canceled', 'dibatalkan'])
                    ->with('rescheduledFrom')
                    ->orderBy('planned_start_at'),
            ]);
            $this->authorize($actor, $current);

            $filled = $this->filledCount($current);
            if ($capacity < $filled) {
                throw ValidationException::withMessages([
                    'slot_capacity' => "Kapasitas tidak boleh kurang dari {$filled} peserta aktif pada sesi ini.",
                ]);
            }

            $current->update(['slot_capacity' => $capacity]);

            return $current->refresh();
        });
    }

    public function filledCount(ClassLearningSession $session, ?string $date = null): int
    {
        $date ??= today()->toDateString();
        $today = Carbon::parse($date);
        $meetings = $session->lessonSessions
            ->filter(fn ($lesson) => ! $lesson->isCancelled());
        $memberships = $session->classGroup?->memberships ?? collect();
        $studentIds = [];

        foreach ($memberships as $membership) {
            if (! $membership->isActiveOn($today)) {
                continue;
            }

            foreach ($meetings as $meeting) {
                if ($this->learningSessions->includes($membership, $meeting, $date)) {
                    $studentIds[$membership->student_id] = true;
                    break;
                }
            }
        }

        return count($studentIds);
    }

    private function present(ClassLearningSession $session): array
    {
        $meetings = $session->lessonSessions
            ->filter(fn ($lesson) => ! $lesson->isCancelled())
            ->values();
        $upcomingMeetings = $meetings->filter(fn ($lesson) => $lesson->planned_start_at?->isFuture());
        $displayMeetings = $upcomingMeetings->isNotEmpty() ? $upcomingMeetings : $meetings;
        $details = $displayMeetings
            ->groupBy(fn ($lesson) => $this->learningSessions->weekday($lesson))
            ->sortKeys()
            ->map(function (Collection $dayMeetings, $weekday): array {
                $upcoming = $dayMeetings->filter(fn ($lesson) => $lesson->planned_start_at?->isFuture());
                $lesson = ($upcoming->isNotEmpty() ? $upcoming : $dayMeetings)
                    ->sortBy('planned_start_at')
                    ->first();
                $teacherName = $lesson?->teacher?->user?->full_name
                    ?: ($lesson?->teacher?->user?->username ?: 'Guru');

                return [
                    'weekday' => (int) $weekday,
                    'day_name' => LessonSeriesService::DAYS[(int) $weekday] ?? 'Hari belum tersedia',
                    'starts_at' => $lesson?->planned_start_at?->format('H:i'),
                    'ends_at' => $lesson?->planned_end_at?->format('H:i'),
                    'teacher_name' => $teacherName,
                    'teacher_id' => $lesson?->teacher_id,
                ];
            })
            ->values();

        $teacherNames = $details->pluck('teacher_name')->filter()->unique()->values();
        $filled = $this->filledCount($session);
        $capacity = $session->slot_capacity;

        return [
            'id' => $session->id,
            'class_group_id' => $session->class_group_id,
            'session_name' => $session->name,
            'class_group_name' => $session->classGroup?->name ?: 'Kelompok belum tersedia',
            'class_group_code' => $session->classGroup?->code,
            'branch_name' => $session->classGroup?->branch?->name ?: 'Cabang belum tersedia',
            'program_name' => $session->classGroup?->program?->name,
            'teacher_name' => $teacherNames->implode(', '),
            'teacher_names' => $teacherNames,
            'details' => $details,
            'slot_capacity' => $capacity,
            'filled' => $filled,
            'remaining' => $capacity === null ? null : $capacity - $filled,
        ];
    }
}
