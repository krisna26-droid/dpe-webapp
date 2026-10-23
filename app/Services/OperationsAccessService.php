<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MonthlyCharge;
use App\Models\MonthlyReport;
use App\Models\QuarterlyReport;
use App\Models\Student;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationsAccessService
{
    public function __construct(private StudentProgressAccessService $monthly, private QuarterlyReportAccessService $quarterly) {}

    public function actor(User $actor): User
    {
        $current = User::find($actor->id);
        abort_unless($current && $current->is_active && in_array($current->role_code, ['superadmin', 'admin', 'teacher', 'student'], true), 403);

        return $current;
    }

    public function studentId(User $actor): ?string
    {
        return Student::where('portal_user_id', $actor->id)->value('id');
    }

    public function monthly(User $actor): Builder
    {
        return $actor->role_code === 'student'
            ? MonthlyReport::where('student_id', $this->studentId($actor) ?? 'no-student')
            : $this->monthly->reports($actor);
    }

    public function quarterly(User $actor): Builder
    {
        return $actor->role_code === 'student'
            ? QuarterlyReport::where('student_id', $this->studentId($actor) ?? 'no-student')
            : $this->quarterly->reports($actor);
    }

    public function charges(User $actor): Builder
    {
        $query = MonthlyCharge::query();
        if ($actor->role_code === 'student') {
            $query->where('student_id', $this->studentId($actor) ?? 'no-student');
        } elseif ($actor->role_code === 'admin') {
            $query->whereIn('branch_id', $this->monthly->branchIds($actor));
        } elseif ($actor->role_code === 'teacher') {
            $teacherId = $this->monthly->teacherId($actor) ?? 'no-teacher';
            $query->whereHas('student', fn ($student) => $student
                ->whereHas('monthlyReports', fn ($report) => $report->where('teacher_id', $teacherId)
                    ->whereRaw('SUBSTR(monthly_reports.report_month, 1, 7) = SUBSTR(monthly_charges.charge_month, 1, 7)'))
                ->orWhereHas('reportCycles', fn ($cycle) => $cycle
                    ->whereRaw('SUBSTR(monthly_charges.charge_month, 1, 7) BETWEEN SUBSTR(report_cycles.start_month, 1, 7) AND SUBSTR(report_cycles.end_month, 1, 7)')
                    ->whereHas('quarterlyReport', fn ($report) => $report->where('teacher_id', $teacherId))));
        }

        return $query;
    }

    public function attendances(User $actor): Builder
    {
        $query = TeacherAttendance::query();
        if ($actor->role_code === 'teacher') {
            $query->where('teacher_id', $this->monthly->teacherId($actor) ?? 'no-teacher');
        } elseif ($actor->role_code === 'admin') {
            $query->whereIn('branch_id', $this->monthly->branchIds($actor));
        } elseif ($actor->role_code === 'student') {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function branches(User $actor): Collection
    {
        if ($actor->role_code === 'superadmin') {
            return Branch::orderBy('name')->get();
        }
        $ids = $actor->role_code === 'admin' ? $this->monthly->branchIds($actor)->pluck('branch_id')
            : $this->monthly($actor)->pluck('branch_id')->merge($this->quarterly($actor)->pluck('branch_id'))->unique();

        return Branch::whereIn('id', $ids)->orderBy('name')->get();
    }

    public function reportPath(User $actor, MonthlyReport|QuarterlyReport $report): string
    {
        if ($actor->role_code === 'student') {
            return route($report instanceof MonthlyReport ? 'student.reports.monthly' : 'student.reports.quarterly', $report, false);
        }

        return route($actor->role_code.'.'.($report instanceof MonthlyReport ? 'monthly-reports' : 'quarterly-reports').'.show', $report, false);
    }

    public function timezone(?string $zone): string
    {
        return in_array($zone, timezone_identifiers_list(), true) ? $zone : config('app.timezone');
    }
}
