<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class AutomaticMonthlyReportService
{
    public function __construct(private StudentProgressAccessService $access, private StudentProgressService $progress) {}

    /** Prepare current-month drafts once, using existing student/branch/Teacher assignments. */
    public function ensureFor(User $actor): array
    {
        $this->access->staff($actor);
        $result = ['created' => 0, 'needs_setup' => 0];
        $zones = Branch::where('is_active', true)->distinct()->pluck('timezone_name');

        foreach ($zones as $storedZone) {
            $zone = in_array($storedZone, timezone_identifiers_list(), true) ? $storedZone : config('app.timezone');
            $today = CarbonImmutable::now($zone);
            $month = $today->startOfMonth();
            $students = $this->access->students($actor, $month)
                ->where('status', 'active')->whereDate('began_on', '<=', $today->toDateString())
                ->whereHas('enrollments', fn ($query) => $query
                    ->whereDate('starts_on', '<=', $today->toDateString())
                    ->where(fn ($period) => $period->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today->toDateString()))
                    ->whereHas('branch', fn ($branch) => $branch->where('is_active', true)->where('timezone_name', $storedZone)))
                ->whereDoesntHave('monthlyReports', fn ($report) => $report->whereDate('report_month', $month->toDateString()))
                ->orderBy('id');

            $students->chunkById(50, function ($batch) use ($actor, $month, &$result) {
                foreach ($batch as $student) {
                    try {
                        $this->progress->prepareAutomatically($actor, $student, $month);
                        $result['created']++;
                    } catch (ValidationException|AuthorizationException $exception) {
                        // Missing/ambiguous assignments remain visible for Admin setup;
                        // never assign an arbitrary Teacher or overwrite an existing report.
                        $result['needs_setup']++;
                    }
                }
            });
        }

        return $result;
    }
}
