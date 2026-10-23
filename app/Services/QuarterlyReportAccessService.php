<?php

namespace App\Services;

use App\Models\QuarterlyReport;
use App\Models\ReportCycle;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class QuarterlyReportAccessService
{
    public function __construct(private StudentProgressAccessService $monthly) {}

    public function teacherId(User $actor): ?string
    {
        return $this->monthly->teacherId($actor);
    }

    public function canManage(User $actor): bool
    {
        return $this->monthly->canManage($actor);
    }

    public function reports(User $actor): Builder
    {
        $this->monthly->staff($actor);
        $query = QuarterlyReport::query();
        if ($actor->role_code === 'teacher') {
            $query->where('teacher_id', $this->teacherId($actor) ?? 'no-active-teacher');
        }
        if ($actor->role_code === 'admin') {
            $query->whereIn('branch_id', $this->monthly->branchIds($actor));
        }

        return $query;
    }

    public function cycles(User $actor): Builder
    {
        $this->monthly->staff($actor);
        $query = ReportCycle::query();
        if ($actor->role_code === 'teacher') {
            $id = $this->teacherId($actor) ?? 'no-active-teacher';
            $query->where(fn ($q) => $q->whereHas('quarterlyReport', fn ($r) => $r->where('teacher_id', $id))
                ->orWhereHas('monthlyReports', fn ($r) => $r->where('teacher_id', $id)));
        }
        if ($actor->role_code === 'admin') {
            $query->where(fn ($q) => $q->whereHas('quarterlyReport', fn ($r) => $r->whereIn('branch_id', $this->monthly->branchIds($actor)))
                ->orWhere(fn ($q) => $q->whereDoesntHave('quarterlyReport')->whereHas('monthlyReports', fn ($r) => $r->whereIn('branch_id', $this->monthly->branchIds($actor)))));
        }

        return $query;
    }

    public function authorize(User $actor, QuarterlyReport $report): void
    {
        abort_unless($this->reports($actor)->whereKey($report->id)->exists(), 403);
    }

    public function authorizeCycle(User $actor, ReportCycle $cycle): void
    {
        abort_unless($this->cycles($actor)->whereKey($cycle->id)->exists(), 403);
    }

    public function owners(ReportCycle $cycle): Collection
    {
        return Teacher::whereIn('id', $cycle->monthlyReports()->select('teacher_id'))
            ->where('is_active', true)->whereHas('user', fn ($q) => $q->where('is_active', true)->where('role_code', 'teacher'))
            ->with('user')->get()->sortBy(fn ($t) => $t->user->full_name)->values();
    }

    public function mayPrepareAtBranch(User $actor, string $branchId): bool
    {
        return $actor->role_code !== 'admin' || $this->monthly->branchIds($actor)->where('branch_id', $branchId)->exists();
    }

    public function mayReadMonthly(User $actor, string $id): bool
    {
        return $this->monthly->reports($actor)->whereKey($id)->exists();
    }
}
