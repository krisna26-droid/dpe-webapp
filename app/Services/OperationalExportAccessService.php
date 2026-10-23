<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Program;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalExportAccessService
{
    public function __construct(private OperationsAccessService $operations, private StudentProgressAccessService $progress,
        private StudentAttendanceAccessService $attendance) {}

    public function actor(User $actor): User
    {
        $actor = $this->operations->actor($actor);
        $this->progress->staff($actor);
        if ($actor->role_code === 'teacher') {
            abort_unless($this->progress->teacherId($actor), 403);
        }

        return $actor;
    }

    public function enrollments(User $actor, string $from, string $to): Builder
    {
        $query = StudentEnrollment::whereDate('starts_on', '<=', $to)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
            ->whereHas('student', fn ($q) => $q->whereDate('began_on', '<=', $to))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereHas('student', fn ($s) => $s->whereColumn('students.began_on', '<=', 'student_enrollments.ends_on')));
        if ($actor->role_code === 'admin') {
            $query->whereIn('branch_id', $this->progress->branchIds($actor));
        } elseif ($actor->role_code === 'teacher') {
            $teacher = $this->progress->teacherId($actor) ?? 'no-teacher';
            $query->where(fn ($q) => $q
                ->whereHas('student.studentTeacherAssignments', fn ($a) => $a->where('teacher_id', $teacher)->whereDate('starts_on', '<=', $to)
                    ->where(fn ($a) => $a->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
                    ->where(fn ($a) => $a->whereNull('ends_on')->orWhereColumn('ends_on', '>=', 'students.began_on'))
                    ->where(fn ($a) => $a->whereNull('student_enrollments.ends_on')->orWhereColumn('starts_on', '<=', 'student_enrollments.ends_on'))
                    ->where(fn ($a) => $a->whereNull('ends_on')->orWhereColumn('ends_on', '>=', 'student_enrollments.starts_on')))
                ->orWhereHas('student.monthlyReports', fn ($r) => $r->where('teacher_id', $teacher)->whereColumn('branch_id', 'student_enrollments.branch_id')
                    ->whereRaw('SUBSTR(report_month, 1, 7) BETWEEN ? AND ?', [substr($from, 0, 7), substr($to, 0, 7)])
                    ->whereRaw('SUBSTR(report_month, 1, 7) >= SUBSTR(student_enrollments.starts_on, 1, 7)')
                    ->where(fn ($r) => $r->whereNull('student_enrollments.ends_on')->orWhereRaw('SUBSTR(report_month, 1, 7) <= SUBSTR(student_enrollments.ends_on, 1, 7)')))
                ->orWhereHas('student.classGroupMemberships', fn ($m) => $m->whereDate('starts_on', '<=', $to)
                    ->where(fn ($m) => $m->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
                    ->where(fn ($m) => $m->whereNull('ends_on')->orWhereColumn('ends_on', '>=', 'students.began_on'))
                    ->where(fn ($m) => $m->whereNull('student_enrollments.ends_on')->orWhereColumn('starts_on', '<=', 'student_enrollments.ends_on'))
                    ->where(fn ($m) => $m->whereNull('ends_on')->orWhereColumn('ends_on', '>=', 'student_enrollments.starts_on'))
                    ->whereHas('classGroup', fn ($g) => $g->where('default_teacher_id', $teacher)->where('is_active', true)
                        ->whereColumn('branch_id', 'student_enrollments.branch_id')->whereColumn('program_id', 'student_enrollments.program_id')))
                ->orWhereHas('student.lessonSessions', fn ($s) => $s->where('teacher_id', $teacher)
                    ->whereColumn('branch_id', 'student_enrollments.branch_id')->whereColumn('program_id', 'student_enrollments.program_id')
                    ->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$from, $to])
                    ->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) >= DATE(students.began_on)')
                    ->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) >= DATE(student_enrollments.starts_on)')
                    ->where(fn ($s) => $s->whereNull('student_enrollments.ends_on')->orWhereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) <= DATE(student_enrollments.ends_on)'))
                    ->where('status', '!=', 'cancelled')->notSuperseded()));
        }

        return $query;
    }

    public function availability(User $actor): Builder
    {
        $query = TeacherAvailability::query();
        if ($actor->role_code === 'teacher') {
            $query->where('teacher_id', $this->progress->teacherId($actor));
        } elseif ($actor->role_code === 'admin') {
            $query->whereIn('branch_id', $this->progress->branchIds($actor));
        }

        return $query;
    }

    public function teachers(User $actor, string $from, string $to): Builder
    {
        $query = Teacher::query();
        if ($actor->role_code === 'teacher') {
            return $query->whereKey($this->progress->teacherId($actor));
        }
        if ($actor->role_code === 'superadmin') {
            return $query;
        }
        $branches = $this->progress->branchIds($actor)->pluck('branch_id');
        $ids = $this->operations->monthly($actor)->whereRaw('SUBSTR(report_month, 1, 7) BETWEEN ? AND ?', [substr($from, 0, 7), substr($to, 0, 7)])->pluck('teacher_id')
            ->merge($this->operations->quarterly($actor)->whereHas('cycle', fn ($c) => $c->whereRaw('SUBSTR(start_month, 1, 7) <= ?', [substr($to, 0, 7)])
                ->whereRaw('SUBSTR(end_month, 1, 7) >= ?', [substr($from, 0, 7)]))->pluck('teacher_id'))
            ->merge($this->attendance->sessions($actor)->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$from, $to])->pluck('teacher_id'))
            ->merge($this->operations->attendances($actor)->whereBetween('attendance_date', [$from, $to])->pluck('teacher_id'))
            ->merge($this->availability($actor)->whereBetween('available_on', [$from, $to])->pluck('teacher_id'));

        return $query->where(fn ($q) => $q->whereIn('id', $ids->filter()->unique())
            ->orWhereHas('branchAssignments', fn ($a) => $a->whereIn('branch_id', $branches)->whereDate('starts_on', '<=', $to)
                ->where(fn ($a) => $a->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from)))
            ->orWhereHas('classGroups', fn ($g) => $g->where('is_active', true)->whereIn('branch_id', $branches))
            ->orWhereHas('studentAssignments', fn ($a) => $a->whereDate('starts_on', '<=', $to)
                ->where(fn ($a) => $a->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
                ->whereHas('student.enrollments', fn ($e) => $e->whereIn('branch_id', $branches)->whereDate('starts_on', '<=', $to)
                    ->where(fn ($e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from)))));
    }

    public function options(User $actor, string $from, string $to): array
    {
        if ($actor->role_code === 'superadmin') {
            $branches = Branch::orderBy('name')->get();
        } elseif ($actor->role_code === 'admin') {
            $branches = $this->operations->branches($actor);
        } else {
            $ids = $this->enrollments($actor, $from, $to)->pluck('branch_id')
                ->merge($this->operations->monthly($actor)->pluck('branch_id'))->merge($this->operations->quarterly($actor)->pluck('branch_id'))
                ->merge($this->attendance->sessions($actor)->pluck('branch_id'))->merge($this->operations->attendances($actor)->pluck('branch_id'))
                ->merge($this->availability($actor)->pluck('branch_id'));
            $branches = Branch::whereIn('id', $ids->filter()->unique())->orderBy('name')->get();
        }
        $teachers = $this->teachers($actor, $from, $to)->with('user')->get()->sortBy(fn ($t) => $t->user?->full_name)->values();
        // Program definitions are also published on the public website. Records remain separately scoped.
        $programs = Program::orderBy('name')->get();

        return compact('branches', 'teachers', 'programs');
    }
}
