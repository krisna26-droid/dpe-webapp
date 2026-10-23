<?php

namespace App\Services;

use App\Models\MonthlyReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MonthlyProgressLockService
{
    public function report(string $reportId): void
    {
        $report = MonthlyReport::whereKey($reportId)->lockForUpdate()->first();
        if ($report && ! in_array($report->status, ['draft', 'revision'], true)) {
            throw ValidationException::withMessages(['status' => 'Isi laporan sudah dikunci. Tunggu permintaan revisi sebelum mengubah data.']);
        }
    }

    public function videoPeriods(array $periods): void
    {
        if (! Schema::hasTable('monthly_reports')) {
            return;
        }
        $ids = collect($periods)->flatMap(fn ($p) => MonthlyReport::where('student_id', $p['student_id'])
            ->whereDate('report_month', CarbonImmutable::parse($p['video_on'])->startOfMonth()->toDateString())->pluck('id'))
            ->unique()->sort()->values();
        foreach ($ids as $id) {
            $this->report($id);
        }
    }
}
