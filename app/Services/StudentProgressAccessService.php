<?php

namespace App\Services;

use App\Models\BranchAdminAssignment;
use App\Models\ClassGroupMembership;
use App\Models\MonthlyReport;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentProgressAccessService
{
    public function teacherId(User $actor): ?string
    {
        return Teacher::where('user_id', $actor->id)->where('is_active', true)->value('id');
    }

    public function branchIds(User $actor): Builder
    {
        return BranchAdminAssignment::query()->select('branch_id')->where('admin_user_id', $actor->id)
            ->whereDate('starts_on', '<=', today())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
    }

    public function staff(User $actor): void
    {
        abort_unless($actor->is_active && in_array($actor->role_code, ['superadmin', 'admin', 'teacher'], true), 403);
    }

    public function reports(User $actor): Builder
    {
        $this->staff($actor);
        $query = MonthlyReport::query();
        if ($actor->role_code === 'teacher') {
            $query->where('teacher_id', $this->teacherId($actor) ?? 'no-active-teacher');
        } elseif ($actor->role_code === 'admin') {
            $query->whereIn('branch_id', $this->branchIds($actor));
        }

        return $query;
    }

    public function students(User $actor, CarbonImmutable $month): Builder
    {
        $this->staff($actor);
        $start = $month->toDateString();
        $end = $month->endOfMonth()->toDateString();
        $query = Student::query()->whereDate('began_on', '<=', $end);
        if ($actor->role_code === 'admin') {
            $query->where(function ($q) use ($actor, $start, $end) {
                $q->whereHas('enrollments', fn ($e) => $e->whereDate('starts_on', '<=', $end)
                    ->where(fn ($e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))
                    ->whereIn('branch_id', $this->branchIds($actor)))
                    ->orWhereHas('monthlyReports', fn ($r) => $r->whereDate('report_month', $start)->whereIn('branch_id', $this->branchIds($actor)));
            });
        } elseif ($actor->role_code === 'teacher') {
            $teacher = $this->teacherId($actor) ?? 'no-active-teacher';
            $rosterIds = $this->teacherRosterStudentIds($actor, $month);
            $query->where(fn ($q) => $q->whereHas('monthlyReports', fn ($r) => $r->whereDate('report_month', $start)->where('teacher_id', $teacher))
                ->orWhereHas('studentTeacherAssignments', fn ($a) => $a->where('teacher_id', $teacher)
                    ->whereDate('starts_on', '<=', $end)->where(fn ($a) => $a->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))
                    ->where(fn ($a) => $a->whereNull('ends_on')->orWhereColumn('ends_on', '>=', 'students.began_on')))
                ->orWhereIn('id', $rosterIds));
        }

        return $query;
    }

    private function teacherRosterStudentIds(User $actor, CarbonImmutable $month): Collection
    {
        $teacher = $this->teacherId($actor);
        if (! $teacher) {
            return collect();
        }
        $start = $month->toDateString();
        $end = $month->endOfMonth()->toDateString();
        $ids = collect();
        $memberships = ClassGroupMembership::query()
            ->whereHas('classGroup', fn ($q) => $q->where('default_teacher_id', $teacher)->where('is_active', true))
            ->whereDate('starts_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))
            ->with(['classGroup', 'student.enrollments'])->get();
        foreach ($memberships as $membership) {
            $student = $membership->student;
            if (! $student) {
                continue;
            }
            $group = $membership->classGroup;
            $valid = $student->enrollments->contains(function ($enrollment) use ($student, $membership, $group, $start, $end) {
                if ($enrollment->branch_id !== $group->branch_id || $enrollment->program_id !== $group->program_id) {
                    return false;
                }

                return max($start, $student->began_on->toDateString(), $membership->starts_on->toDateString(), $enrollment->starts_on->toDateString())
                    <= min($end, $membership->ends_on?->toDateString() ?? $end, $enrollment->ends_on?->toDateString() ?? $end);
            });
            if ($valid) {
                $ids->push($student->id);
            }
        }

        $attendance = app(StudentAttendanceService::class);
        $sessions = app(StudentAttendanceAccessService::class)->sessions($actor)
            ->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$start, $end])
            ->with(['branch', 'replacementSessions', 'sessionStudents.student.enrollments', 'sessionStudents.student.classGroupMemberships',
                'classGroup.memberships.student.enrollments', 'classGroup.memberships.student.classGroupMemberships'])->get();
        foreach ($sessions as $session) {
            if ($session->isCancelled() || $attendance->isSuperseded($session)) {
                continue;
            }
            foreach ($attendance->roster($session) as $student) {
                if ($attendance->eligibleToAttach($student, $session)) {
                    $ids->push($student->id);
                }
            }
        }

        return $ids->unique()->values();
    }

    public function authorize(User $actor, MonthlyReport $report): void
    {
        abort_unless($this->reports($actor)->whereKey($report->id)->exists(), 403);
    }

    public function canManage(User $actor): bool
    {
        return $actor->is_active && in_array($actor->role_code, ['superadmin', 'admin'], true);
    }
}
