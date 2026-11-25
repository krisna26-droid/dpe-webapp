<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Shared placement and primary-teacher rules for the user directory and its exports. */
class StudentAccountDirectoryService
{
    public function __construct(private StudentAccountProvisioningService $accounts) {}

    public function actor(User $user): User
    {
        $actor = User::query()->select(['id', 'full_name', 'role_code', 'is_active'])->find($user->id);
        abort_unless($actor?->is_active && in_array($actor->role_code, ['superadmin', 'admin'], true), 403);

        return $actor;
    }

    public function baseQuery(User $actor): Builder
    {
        abort_unless(in_array($actor->role_code, ['superadmin', 'admin'], true), 403);
        return $actor->role_code === 'admin' ? $this->accounts->users($actor) : User::query();
    }

    public function scopeEnrollments($query, User $actor, array $filters = [])
    {
        if ($actor->role_code === 'admin') {
            $query = $this->accounts->scopeEnrollments($query, $actor);
        } else {
            // The directory describes current/upcoming placements. Unlinked or
            // historical-only student accounts still appear to Super Admin.
            $this->notEnded($query);
        }

        return $query
            ->when(filled($filters['branch_id'] ?? null), fn ($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(filled($filters['program_id'] ?? null), fn ($q) => $q->where('program_id', $filters['program_id']));
    }

    private function notEnded($query)
    {
        return $query->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
    }

    private function teacherRelation($query)
    {
        return $query->whereNull('teachers.deleted_at')->where('teachers.is_active', true)
            ->whereHas('user', fn ($u) => $u->whereNull('users.deleted_at')->where('role_code', 'teacher')->where('is_active', true))
            ->select(['teachers.id', 'teachers.user_id', 'teachers.is_active', 'teachers.deleted_at'])
            ->with(['user:id,full_name,role_code,is_active,deleted_at',
                'branchAssignments' => fn ($q) => $this->notEnded($q)]);
    }

    public function withDetails(Builder $query, User $actor, array $filters = []): Builder
    {
        return $query->with([
            'studentProfile:id,portal_user_id,began_on',
            'studentProfile.enrollments' => fn ($q) => $this->scopeEnrollments($q, $actor, $filters)
                ->with(['branch:id,name', 'program:id,name'])->orderBy('starts_on')->orderBy('id'),
            'studentProfile.studentTeacherAssignments' => fn ($q) => $this->notEnded($q)
                ->with(['teacher' => fn ($t) => $this->teacherRelation($t)]),
            'studentProfile.classGroupMemberships' => fn ($q) => $this->notEnded($q)
                ->with(['classGroup' => fn ($g) => $g->whereNull('class_groups.deleted_at')->where('is_active', true)
                    ->select(['id', 'branch_id', 'program_id', 'default_teacher_id'])
                    ->with(['defaultTeacher' => fn ($t) => $this->teacherRelation($t)])]),
        ]);
    }

    private function covers($period, string $day): bool
    {
        return $period->starts_on->toDateString() <= $day
            && ($period->ends_on === null || $period->ends_on->toDateString() >= $day);
    }

    private function teacherAt(?Teacher $teacher, string $branchId, string $day): bool
    {
        if (! $teacher?->is_active || $teacher->trashed() || ! $teacher->user?->is_active
            || $teacher->user->trashed() || $teacher->user->role_code !== 'teacher') {
            return false;
        }
        // Direct assignments have no branch column. As in the teaching
        // directory, permit an unassigned tutor, but not a tutor assigned
        // exclusively to a different branch on this date.
        $branches = $teacher->branchAssignments->filter(fn ($assignment) => $this->covers($assignment, $day));

        return $branches->isEmpty() || $branches->contains('branch_id', $branchId);
    }

    private function primaryTeachers(Student $student, StudentEnrollment $enrollment, string $day): array
    {
        $direct = $student->studentTeacherAssignments->filter(fn ($assignment) => $this->covers($assignment, $day)
            && $this->teacherAt($assignment->teacher, $enrollment->branch_id, $day));
        $owners = $direct->where('is_report_owner', true)->pluck('teacher');
        $groups = $student->classGroupMemberships->filter(fn ($membership) => $membership->classGroup
            && $membership->classGroup->branch_id === $enrollment->branch_id
            && $membership->classGroup->program_id === $enrollment->program_id
            && $this->covers($membership, $day)
            && $this->teacherAt($membership->classGroup->defaultTeacher, $enrollment->branch_id, $day))
            ->map(fn ($membership) => $membership->classGroup->defaultTeacher);

        // Report ownership takes priority. Otherwise use the regular group
        // teacher, or the direct assignment for a private student. Incidental
        // makeup sessions and historical monthly reports never confer ownership.
        $teachers = $owners->isNotEmpty() ? $owners : ($groups->isNotEmpty() ? $groups : $direct->pluck('teacher'));

        return $teachers->unique('id')->mapWithKeys(fn (Teacher $teacher) => [$teacher->id => $teacher->user->full_name])->all();
    }

    /** Resolve the same one-account row for filtering, option labels and export. */
    public function details(User $account, string $teacherFilter = ''): array
    {
        $branches = $programs = $teachers = [];
        $student = $account->studentProfile;
        if ($student) {
            foreach ($student->enrollments as $enrollment) {
                $day = max(today()->toDateString(), $student->began_on->toDateString(), $enrollment->starts_on->toDateString());
                if (! $this->covers($enrollment, $day)) {
                    continue;
                }
                $primary = $this->primaryTeachers($student, $enrollment, $day);
                if ($teacherFilter !== '' && ! array_key_exists($teacherFilter, $primary)) {
                    continue;
                }
                if ($enrollment->branch) $branches[$enrollment->branch_id] = $enrollment->branch->name;
                if ($enrollment->program) $programs[$enrollment->program_id] = $enrollment->program->name;
                $teachers += $primary;
            }
        }

        return compact('branches', 'programs', 'teachers');
    }

    public function apply(Builder $query, User $actor, array $filters): Builder
    {
        $branch = $filters['branch_id'] ?? '';
        $program = $filters['program_id'] ?? '';
        $teacher = $filters['teacher_id'] ?? '';
        if ($branch === '' && $program === '' && $teacher === '') return $query;

        $query->where('role_code', 'student')->whereHas('studentProfile.enrollments',
            fn ($q) => $this->scopeEnrollments($q, $actor, $filters));
        if ($teacher !== '') {
            $ids = [];
            $scan = $this->withDetails((clone $query)->select('users.id'), $actor, $filters);
            $scan->chunkById(200, function ($batch) use ($teacher, &$ids) {
                foreach ($batch as $account) {
                    if (array_key_exists($teacher, $this->details($account, $teacher)['teachers'])) {
                        $ids[] = $account->id;
                    }
                }
            }, 'users.id', 'id');
            $query->whereKey($ids);
        }

        return $query;
    }

    public function options(User $user): array
    {
        $actor = $this->actor($user);
        $branches = $actor->role_code === 'superadmin' ? Branch::orderBy('name')->pluck('name', 'id')->all() : [];
        $programs = $teachers = [];
        // Option names come only from the accounts and placements this actor
        // is allowed to see, including newly scheduled enrolments. Batch the
        // relationships instead of issuing a query for each pupil.
        $scan = $this->withDetails($this->baseQuery($actor)->where('role_code', 'student')->select('users.id'), $actor);
        $scan->chunkById(200, function ($batch) use (&$programs, &$teachers) {
            foreach ($batch as $account) {
                $detail = $this->details($account);
                $programs += $detail['programs'];
                $teachers += $detail['teachers'];
            }
        }, 'users.id', 'id');
        asort($programs, SORT_NATURAL | SORT_FLAG_CASE);
        asort($teachers, SORT_NATURAL | SORT_FLAG_CASE);

        return ['accountBranches' => $branches, 'accountPrograms' => $programs, 'accountTeachers' => $teachers];
    }
}
