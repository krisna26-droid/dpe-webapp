<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeachingDirectoryService
{
    public function __construct(private BranchOperationsService $operations) {}

    /** Both directory directions use exactly the same current teaching pairs. */
    public function pairs(User $actor): QueryBuilder
    {
        $this->operations->actor($actor);
        $day = today()->toDateString();
        $common = function (QueryBuilder $query) use ($actor, $day) {
            return $query->join('students as pupil', 'pupil.id', '=', 'enrollment.student_id')
                ->join('teachers as tutor', 'tutor.id', '=', 'assignment.teacher_id')
                ->join('users as tutor_user', 'tutor_user.id', '=', 'tutor.user_id')
                ->where('pupil.status', 'active')->whereDate('pupil.began_on', '<=', $day)
                ->where('tutor.is_active', true)->where('tutor_user.is_active', true)->where('tutor_user.role_code', 'teacher')
                ->whereDate('enrollment.starts_on', '<=', $day)
                ->where(fn ($q) => $q->whereNull('enrollment.ends_on')->orWhereDate('enrollment.ends_on', '>=', $day))
                ->whereIn('enrollment.branch_id', $this->operations->branches($actor)->select('id'));
        };
        $direct = $common(DB::table('student_teacher_assignments as assignment')
            ->join('student_enrollments as enrollment', 'enrollment.student_id', '=', 'assignment.student_id'))
            ->whereDate('assignment.starts_on', '<=', $day)
            ->where(fn ($q) => $q->whereNull('assignment.ends_on')->orWhereDate('assignment.ends_on', '>=', $day))
            ->where(function ($query) use ($day) {
                // Direct assignments have no branch column. Prefer a current
                // shared branch; without any branch assignment, use the pupil's
                // current enrollment, as for a directly assigned private tutor.
                $branchAssignment = fn () => DB::table('teacher_branch_assignments as tutor_branch')
                    ->selectRaw('1')->whereColumn('tutor_branch.teacher_id', 'tutor.id')
                    ->whereDate('tutor_branch.starts_on', '<=', $day)
                    ->where(fn ($q) => $q->whereNull('tutor_branch.ends_on')->orWhereDate('tutor_branch.ends_on', '>=', $day));
                $query->whereExists($branchAssignment()->whereColumn('tutor_branch.branch_id', 'enrollment.branch_id'))
                    ->orWhereNotExists($branchAssignment());
            })
            ->select(['assignment.student_id', 'assignment.teacher_id', 'enrollment.branch_id']);

        $group = DB::table('class_group_memberships as membership')
            ->join('class_groups as class_group', 'class_group.id', '=', 'membership.class_group_id')
            ->join('student_enrollments as enrollment', fn ($join) => $join
                ->on('enrollment.student_id', '=', 'membership.student_id')
                ->on('enrollment.branch_id', '=', 'class_group.branch_id')
                ->on('enrollment.program_id', '=', 'class_group.program_id'))
            ->join('students as pupil', 'pupil.id', '=', 'membership.student_id')
            ->join('teachers as tutor', 'tutor.id', '=', 'class_group.default_teacher_id')
            ->join('users as tutor_user', 'tutor_user.id', '=', 'tutor.user_id')
            ->where('class_group.is_active', true)->where('pupil.status', 'active')->whereDate('pupil.began_on', '<=', $day)
            ->where('tutor.is_active', true)->where('tutor_user.is_active', true)->where('tutor_user.role_code', 'teacher')
            ->whereDate('membership.starts_on', '<=', $day)
            ->where(fn ($q) => $q->whereNull('membership.ends_on')->orWhereDate('membership.ends_on', '>=', $day))
            ->whereDate('enrollment.starts_on', '<=', $day)
            ->where(fn ($q) => $q->whereNull('enrollment.ends_on')->orWhereDate('enrollment.ends_on', '>=', $day))
            ->whereIn('enrollment.branch_id', $this->operations->branches($actor)->select('id'))
            ->select(['membership.student_id', 'class_group.default_teacher_id as teacher_id', 'class_group.branch_id']);

        return DB::query()->fromSub($direct->union($group), 'teaching_pairs');
    }

    public function teachersForStudents(User $actor, Collection $studentIds): Collection
    {
        if ($studentIds->isEmpty()) return collect();

        return $this->pairs($actor)->whereIn('teaching_pairs.student_id', $studentIds)
            ->join('teachers as directory_teacher', 'directory_teacher.id', '=', 'teaching_pairs.teacher_id')
            ->join('users as directory_user', 'directory_user.id', '=', 'directory_teacher.user_id')
            ->select(['teaching_pairs.student_id', 'teaching_pairs.teacher_id',
                'directory_user.full_name as teacher_name', 'directory_user.username as teacher_username'])
            ->distinct()->get()->sortBy(fn ($row) => mb_strtolower($row->teacher_name ?: $row->teacher_username))
            ->groupBy('student_id');
    }

    public function teacherSummaries(User $actor, Collection $teacherIds): Collection
    {
        if ($teacherIds->isEmpty()) return collect();

        // One batch for the current page; count unique pupil IDs, even when a
        // pupil has several groups or both a direct and a group assignment.
        return $this->pairs($actor)->whereIn('teaching_pairs.teacher_id', $teacherIds)
            ->join('students as directory_student', 'directory_student.id', '=', 'teaching_pairs.student_id')
            ->select(['teaching_pairs.teacher_id', 'teaching_pairs.student_id', 'directory_student.full_name'])
            ->distinct()->orderBy('directory_student.full_name')->orderBy('teaching_pairs.student_id')->get()
            ->groupBy('teacher_id')->map(fn ($rows) => ['count' => $rows->count(), 'names' => $rows->take(3)->pluck('full_name')]);
    }

    public function teachers(User $actor): Builder
    {
        $this->operations->actor($actor);

        return Teacher::query()->when($actor->role_code === 'admin', fn ($query) => $query->where(fn ($query) => $query
            ->whereHas('branchAssignments', fn ($assignment) => $this->scopeTeacherBranches($assignment, $actor, true))
            ->orWhereIn('id', $this->pairs($actor)->select('teaching_pairs.teacher_id'))));
    }

    public function hasStudents(User $actor, Teacher $teacher): bool
    {
        return $this->pairs($actor)->where('teaching_pairs.teacher_id', $teacher->id)->exists();
    }

    public function studentsForTeacher(User $actor, Teacher $teacher): Builder
    {
        return Student::query()->whereIn('id', $this->pairs($actor)->where('teaching_pairs.teacher_id', $teacher->id)
            ->select('teaching_pairs.student_id'))->with(['portalUser',
                'enrollments' => fn ($query) => $this->scopeEnrollments($query, $actor)->with(['branch', 'program'])])
            ->orderBy('full_name')->orderBy('id');
    }

    public function scopeEnrollments($query, User $actor)
    {
        return $query->whereDate('starts_on', '<=', today())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->whereIn('branch_id', $this->operations->branches($actor)->select('id'));
    }

    public function scopeTeacherBranches($query, User $actor, bool $activeOnly = false)
    {
        return $query->whereIn('branch_id', $this->operations->branches($actor)->select('id'))
            ->when($activeOnly, fn ($q) => $q->whereDate('starts_on', '<=', today())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today())));
    }
}
