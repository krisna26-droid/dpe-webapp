<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonthlyChargeListService
{
    public function __construct(
        private DashboardSettingsService $settings,
        private MonthlyChargeStudentFilterService $studentFilter,
    ) {}

    public function viewData(Request $request): array
    {
        $actor = $this->settings->actor($request->user());
        $canFilterBranch = $actor->role_code === 'superadmin';
        $filters = $this->filters($request, $canFilterBranch);
        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month']);
        $start = $month->toDateString();
        $end = $month->endOfMonth()->toDateString();
        $next = $month->addMonth()->toDateString();

        $branches = ($canFilterBranch ? Branch::withTrashed() : $this->settings->branches($actor))
            ->orderBy('name')->get();
        $selectedBranches = $filters['branch_id'] === '' ? $branches : $branches->where('id', $filters['branch_id']);
        $branchIds = $selectedBranches->pluck('id');
        $activeBranchIds = $selectedBranches->where('is_active', true)->whereNull('deleted_at')->pluck('id');

        $chargesInMonth = fn ($query) => $query->where('charge_month', '>=', $start)->where('charge_month', '<', $next);
        $scopedCharges = fn ($query) => $chargesInMonth($query)->whereIn('branch_id', $branchIds);
        $eligibleEnrollments = fn ($query) => $query->whereIn('branch_id', $activeBranchIds)
            ->whereDate('starts_on', '<=', $end)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start));
        $unbilledStudents = fn (Builder $query) => $query->whereNull('students.deleted_at')->where('students.status', 'active')
            ->whereDate('students.began_on', '<=', $end)->whereHas('enrollments', $eligibleEnrollments)
            ->whereDoesntHave('monthlyCharges', $chargesInMonth);

        $teachers = $this->studentFilter->teacherOptions(
            $this->students('all', $scopedCharges, $unbilledStudents),
            $month,
            $branchIds,
        );
        $query = $this->students($filters['charge_status'], $scopedCharges, $unbilledStudents);

        if ($filters['q'] !== '') {
            $name = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q']));
            $query->whereRaw("LOWER(students.full_name) LIKE ? ESCAPE '!'", ['%'.$name.'%']);
        }

        $teacherUnavailable = false;
        if ($filters['teacher_filter_id'] !== '') {
            $teacher = $teachers->firstWhere('id', $filters['teacher_filter_id']);
            $teacherUnavailable = $teacher === null;
            $matches = $teacher ? $this->studentFilter->studentsForTeacher(clone $query, $teacher, $month, $branchIds) : collect();
            $query->whereKey($matches->pluck('id'));
        }

        $students = $query->with([
            'monthlyCharges' => fn ($query) => $scopedCharges($query)->latest('charge_month')->latest('created_at')
                ->with(['branch', 'paymentProofs' => fn ($query) => $query->latest('submitted_at')]),
            'enrollments' => fn ($query) => $eligibleEnrollments($query)->with('branch')->latest('starts_on')->orderBy('id'),
        ])->orderBy('full_name')->orderBy('id')->paginate(15)
            ->appends(array_filter($filters, fn ($value) => $value !== ''));

        return compact('branches', 'teachers', 'filters', 'students', 'month', 'canFilterBranch', 'teacherUnavailable');
    }

    private function filters(Request $request, bool $canFilterBranch): array
    {
        $rules = [
            'month' => ['nullable', 'date_format:Y-m'],
            'q' => ['nullable', 'string', 'max:120'],
            'teacher_filter_id' => ['nullable', 'uuid'],
            'charge_status' => ['nullable', Rule::in(['all', 'created', 'missing'])],
        ];
        if ($canFilterBranch) {
            $rules['branch_id'] = ['nullable', 'uuid'];
        }
        $data = $request->validate($rules);

        return [
            'month' => $data['month'] ?? today()->format('Y-m'),
            'q' => trim($data['q'] ?? ''),
            'teacher_filter_id' => $data['teacher_filter_id'] ?? '',
            'charge_status' => $data['charge_status'] ?? 'all',
            'branch_id' => $data['branch_id'] ?? '',
        ];
    }

    private function students(string $status, Closure $scopedCharges, Closure $unbilledStudents): Builder
    {
        $query = Student::withTrashed();

        return match ($status) {
            'created' => $query->whereHas('monthlyCharges', $scopedCharges),
            'missing' => $unbilledStudents($query),
            default => $query->where(fn ($query) => $query->whereHas('monthlyCharges', $scopedCharges)->orWhere($unbilledStudents)),
        };
    }
}
