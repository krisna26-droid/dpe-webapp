<?php

namespace App\Services;

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

        return compact('month', 'branches', 'students', 'teachers');
    }

    private function filterStudents(Builder $students, Collection $teachers, CarbonImmutable $month, string $teacherId): Builder
    {
        if ($teacherId === '') {
            return $students;
        }
        $teacher = $teachers->firstWhere('id', $teacherId);
        if (! $teacher) {
            throw ValidationException::withMessages(['teacher_filter_id' => 'Guru tidak tersedia untuk bulan atau cabang ini. Pilih guru kembali.']);
        }

        // Use the same assignment, class, session and report ownership rules as Siswa Binaan.
        return $students->whereIn('students.id', $this->progress->students($teacher->user, $month)->select('students.id'));
    }

    public function options(User $user, array $filters, bool $allowUnavailable = false): array
    {
        ['month' => $month, 'branches' => $branches, 'students' => $students, 'teachers' => $teachers] = $this->context($user, $filters);
        if ($allowUnavailable && $filters['teacher_filter_id'] !== '' && ! $teachers->contains('id', $filters['teacher_filter_id'])) {
            $students->whereRaw('1 = 0');
        } else {
            $students = $this->filterStudents($students, $teachers, $month, $filters['teacher_filter_id']);
        }
        $students = $students->with('portalUser')->orderBy('full_name')->orderBy('id')->get();

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
        ['month' => $month, 'students' => $students, 'teachers' => $teachers] = $this->context($user, $data);
        if (! $this->filterStudents($students, $teachers, $month, $data['teacher_filter_id'])->whereKey($data['student_id'])->exists()) {
            throw ValidationException::withMessages(['student_id' => 'Pilih siswa yang terhubung ke guru pada bulan tagihan ini.']);
        }
    }
}
