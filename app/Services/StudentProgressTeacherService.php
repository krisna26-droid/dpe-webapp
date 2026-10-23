<?php

namespace App\Services;

use App\Models\LessonSession;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class StudentProgressTeacherService
{
    public function __construct(private StudentAttendanceService $attendance) {}

    public function context(Student $student, CarbonImmutable $month, string $branchId): array
    {
        $start = max($month->toDateString(), $student->began_on->toDateString());
        $end = $month->endOfMonth()->toDateString();
        $enrollments = $student->enrollments()->where('branch_id', $branchId)->whereDate('starts_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))->get();
        $sources = collect();
        $owners = collect();
        $assignments = $student->studentTeacherAssignments()->whereDate('starts_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))->get();
        foreach ($assignments as $assignment) {
            if ($this->overlaps($assignment, $enrollments, $start, $end)) {
                $this->source($sources, $assignment->teacher_id, 'Penugasan siswa');
                if ($assignment->is_report_owner) {
                    $owners->push($assignment->teacher_id);
                }
            }
        }
        $memberships = $student->classGroupMemberships()->whereHas('classGroup', fn ($q) => $q->where('branch_id', $branchId)->where('is_active', true))
            ->whereDate('starts_on', '<=', $end)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))
            ->with('classGroup')->get();
        foreach ($memberships as $membership) {
            $group = $membership->classGroup;
            if ($group->default_teacher_id && $this->overlaps($membership, $enrollments, $start, $end, $group->program_id)) {
                $this->source($sources, $group->default_teacher_id, 'Teacher kelas');
            }
        }

        $sessions = LessonSession::where('branch_id', $branchId)
            ->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$start, $end])
            ->where(fn ($q) => $q->whereHas('sessionStudents', fn ($q) => $q->where('student_id', $student->id))
                ->orWhereHas('classGroup.memberships', fn ($q) => $q->where('student_id', $student->id)))
            ->with(['branch', 'replacementSessions'])->get();
        foreach ($sessions as $session) {
            if (! $session->isCancelled() && ! $this->attendance->isSuperseded($session)
                && $this->attendance->eligibleToAttach($student, $session)) {
                $this->source($sources, $session->teacher_id, $session->class_group_id ? 'Teacher pertemuan kelas' : 'Teacher pertemuan private');
            }
        }

        $branchAssignments = fn ($q) => $q->where('branch_id', $branchId)->whereDate('starts_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start));
        $teachers = Teacher::where('is_active', true)->whereHas('user', fn ($q) => $q->where('is_active', true)->where('role_code', 'teacher'))
            ->where(fn ($q) => $q->whereIn('id', $sources->keys())->orWhereHas('branchAssignments', $branchAssignments))
            ->with(['user', 'branchAssignments' => $branchAssignments])->get()
            ->filter(fn ($teacher) => $sources->has($teacher->id)
                || $teacher->branchAssignments->contains(fn ($assignment) => $this->overlaps($assignment, $enrollments, $start, $end)))
            ->sortBy('user.full_name')->values();
        $assignedTeachers = $teachers->filter(fn ($teacher) => $sources->has($teacher->id))->values();
        $owners = $owners->unique()->intersect($teachers->pluck('id'))->values();
        $suggested = $owners->count() === 1 ? $owners->first() : ($owners->isEmpty() && $assignedTeachers->count() === 1 ? $assignedTeachers->first()->id : null);
        $unavailableAssignedTeachers = Teacher::whereIn('id', $sources->keys())->whereNotIn('id', $teachers->pluck('id'))->with('user')->get();

        return ['teachers' => $teachers, 'assignedTeachers' => $assignedTeachers, 'teacherSources' => $sources,
            'unavailableAssignedTeachers' => $unavailableAssignedTeachers, 'suggestedTeacher' => $suggested];
    }

    private function overlaps($assignment, Collection $enrollments, string $start, string $end, ?string $program = null): bool
    {
        return $enrollments->contains(fn ($enrollment) => ($program === null || $enrollment->program_id === $program)
            && max($start, $assignment->starts_on->toDateString(), $enrollment->starts_on->toDateString())
                <= min($end, $assignment->ends_on?->toDateString() ?? $end, $enrollment->ends_on?->toDateString() ?? $end));
    }

    private function source(Collection $sources, string $teacherId, string $label): void
    {
        $sources->put($teacherId, array_values(array_unique([...$sources->get($teacherId, []), $label])));
    }
}
