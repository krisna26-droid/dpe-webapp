<?php

namespace App\Services;

use App\Models\ClassGroup;
use App\Models\LessonSession;
use App\Models\MonthlyReport;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MonthlyChargeStudentFilterService
{
    public function __construct(
        private DashboardSettingsService $settings,
        private StudentProgressAccessService $progress,
    ) {}

    public function filters(Request $request, bool $oldInput = false): array
    {
        $rules = ['charge_month' => ['nullable', 'date_format:Y-m-d'], 'teacher_filter_id' => ['nullable', 'uuid']];
        $data = $request->validate($rules);
        if ($oldInput) {
            // Invalid old input must not prevent the form from showing its errors.
            foreach (array_keys($rules) as $key) {
                $value = $request->old($key, $data[$key] ?? null);
                $data[$key] = Validator::make([$key => $value], [$key => $rules[$key]])->passes() ? $value : null;
            }
        }

        return ['charge_month' => $data['charge_month'] ?? today()->startOfMonth()->toDateString(),
            'teacher_filter_id' => $data['teacher_filter_id'] ?? ''];
    }

    private function context(User $user, array $filters): array
    {
        $actor = $this->settings->actor($user);
        $month = CarbonImmutable::parse($filters['charge_month'])->startOfMonth();
        $branches = $this->settings->branches($actor)->where('is_active', true)->orderBy('name')->get();
        $students = Student::query()->where('status', 'active');
        if ($actor->role_code === 'admin') {
            $students->whereHas('enrollments', fn ($e) => $e->whereIn('branch_id', $branches->pluck('id'))
                ->whereDate('starts_on', '<=', $month->endOfMonth()->toDateString())
                ->where(fn ($e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month->toDateString())));
        }

        $teachers = Teacher::query()->where('is_active', true)
            ->whereHas('user', fn ($u) => $u->where('is_active', true)->where('role_code', 'teacher')->whereNull('deleted_at'));
        if ($actor->role_code === 'admin') {
            $start = $month->toDateString();
            $end = $month->endOfMonth()->toDateString();
            $ids = $branches->pluck('id');
            $teachers->where(fn ($t) => $t
                ->whereHas('branchAssignments', fn ($a) => $a->whereIn('branch_id', $ids)->whereDate('starts_on', '<=', $end)
                    ->where(fn ($a) => $a->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start)))
                ->orWhereHas('studentAssignments', fn ($a) => $a->whereIn('student_id', (clone $students)->select('students.id'))
                    ->whereDate('starts_on', '<=', $end)->where(fn ($a) => $a->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start)))
                ->orWhereHas('classGroups', fn ($g) => $g->whereIn('branch_id', $ids)->where('is_active', true))
                ->orWhereIn('id', LessonSession::query()->select('teacher_id')->whereIn('branch_id', $ids)
                    ->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$start, $end]))
                ->orWhereIn('id', MonthlyReport::query()->select('teacher_id')->whereIn('branch_id', $ids)
                    ->whereDate('report_month', $start)));
        }
        $teachers = $teachers->with('user')->get()->sortBy(fn ($t) => $t->user->full_name)->values();

        $branchIds = $actor->role_code === 'admin' ? $branches->pluck('id') : null;

        return compact('month', 'branches', 'branchIds', 'students', 'teachers');
    }

    private function filterStudents(array $context, string $teacherId): Collection
    {
        ['students' => $students, 'teachers' => $teachers, 'month' => $month, 'branchIds' => $branchIds] = $context;
        if ($teacherId === '') {
            return $students->get();
        }
        $teacher = $teachers->firstWhere('id', $teacherId);
        if (! $teacher) {
            throw ValidationException::withMessages(['teacher_filter_id' => 'Guru tidak tersedia untuk bulan atau cabang ini. Pilih guru kembali.']);
        }

        $students->whereIn('students.id', $this->progress->students($teacher->user, $month, $branchIds)->select('students.id'));

        return $branchIds === null ? $students->get() : $this->filterPrimaryTeacher($students, $teacherId, $month, $branchIds);
    }

    private function filterPrimaryTeacher(Builder $students, string $teacherId, CarbonImmutable $month, Collection $branchIds): Collection
    {
        $start = $month->toDateString();
        $end = $month->endOfMonth()->toDateString();
        $inMonth = fn ($q) => $q->whereDate('starts_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start));
        $candidates = $students->with([
            'enrollments' => fn ($q) => $inMonth($q)->whereIn('branch_id', $branchIds),
            'studentTeacherAssignments' => $inMonth,
            'classGroupMemberships' => fn ($q) => $inMonth($q)->whereHas('classGroup', fn ($q) => $q->where('is_active', true)
                ->whereIn('branch_id', $branchIds)->whereNotNull('default_teacher_id'))->with('classGroup'),
        ])->get();

        return $candidates->filter(function (Student $student) use ($teacherId, $start, $end) {
            $overlapsEnrollment = function ($period, ?ClassGroup $group = null) use ($student, $start, $end) {
                return $student->enrollments->contains(fn ($enrollment) =>
                    (! $group || ($enrollment->branch_id === $group->branch_id && $enrollment->program_id === $group->program_id))
                    && max($start, $student->began_on->toDateString(), $period->starts_on->toDateString(), $enrollment->starts_on->toDateString())
                        <= min($end, $period->ends_on?->toDateString() ?? $end, $enrollment->ends_on?->toDateString() ?? $end));
            };
            $assignments = $student->studentTeacherAssignments->filter(fn ($assignment) => $overlapsEnrollment($assignment));
            $owners = $assignments->where('is_report_owner', true);
            if ($owners->isNotEmpty()) {
                return $owners->contains('teacher_id', $teacherId);
            }

            $classes = $student->classGroupMemberships
                ->filter(fn ($membership) => $membership->classGroup && $overlapsEnrollment($membership, $membership->classGroup));
            $regularTeachers = $assignments->pluck('teacher_id')->merge($classes->pluck('classGroup.default_teacher_id'))->filter();

            // A report or regular lesson can identify the teacher for older records without a dated assignment.
            return $regularTeachers->isEmpty() || $regularTeachers->contains($teacherId);
        })->values();
    }

    public function options(User $user, array $filters, bool $allowUnavailable = false): array
    {
        $context = $this->context($user, $filters);
        ['branches' => $branches, 'students' => $students, 'teachers' => $teachers] = $context;
        $students->with('portalUser')->orderBy('full_name')->orderBy('id');
        if ($allowUnavailable && $filters['teacher_filter_id'] !== '' && ! $teachers->contains('id', $filters['teacher_filter_id'])) {
            $students = collect();
        } else {
            $students = $this->filterStudents($context, $filters['teacher_filter_id']);
        }
        return compact('branches', 'students', 'teachers') + ['chargeFilters' => $filters];
    }

    public function jsonOptions(User $user, array $filters): array
    {
        $options = $this->options($user, $filters);

        return ['students' => $options['students']->map(fn ($s) => ['id' => $s->id,
            'label' => $s->full_name.($s->portalUser?->username ? ' · '.$s->portalUser->username : '')])->values(),
            'teachers' => $options['teachers']->map(fn ($t) => ['id' => $t->id, 'label' => $t->user->full_name.' · '.$t->user->username])->values()];
    }

    public function assertSelection(User $user, array $data): void
    {
        if (empty($data['teacher_filter_id'])) {
            return;
        }
        $context = $this->context($user, $data);
        $context['students']->whereKey($data['student_id']);
        if ($this->filterStudents($context, $data['teacher_filter_id'])->isEmpty()) {
            throw ValidationException::withMessages(['student_id' => 'Pilih siswa yang terhubung ke guru pada bulan tagihan ini.']);
        }
    }
}
