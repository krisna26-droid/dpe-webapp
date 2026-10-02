<?php

namespace App\Services;

use App\Models\MonthlyReport;
use App\Models\ReportCycle;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\SystemSetting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MonthlyReportService
{
    /**
     * Membuat laporan bulanan untuk seluruh bulan dalam siklus.
     *
     * Laporan yang sudah ada untuk siklus dan bulan yang sama
     * tidak dibuat ulang.
     *
     * @return Collection<int, MonthlyReport>
     */
    public function generateForCycle(
        ReportCycle $cycle
    ): Collection {
        $cycle->refresh();

        $settings = SystemSetting::query()->first();

        if (! $settings) {
            throw new RuntimeException(
                'Pengaturan sistem DPE belum tersedia.'
            );
        }

        if (
            $settings->default_report_due_day < 1
            || $settings->default_report_due_day > 31
        ) {
            throw new RuntimeException(
                'Tanggal tenggat laporan pada pengaturan sistem tidak valid.'
            );
        }

        if ($settings->default_monthly_video_target < 0) {
            throw new RuntimeException(
                'Target video bulanan tidak boleh negatif.'
            );
        }

        $start = CarbonImmutable::parse(
            $cycle->start_month
        )->startOfMonth();

        $end = CarbonImmutable::parse(
            $cycle->end_month
        )->startOfMonth();

        if ($end->lt($start)) {
            throw new RuntimeException(
                'Periode akhir siklus tidak boleh sebelum periode awal.'
            );
        }

        return DB::transaction(function () use (
            $cycle,
            $settings,
            $start,
            $end
        ) {
            $reports = new Collection();

            for (
                $month = $start;
                $month->lte($end);
                $month = $month->addMonth()
            ) {
                $reportMonth = $month->toDateString();

                $existing = MonthlyReport::query()
                    ->where('student_id', $cycle->student_id)
                    ->whereDate('report_month', $reportMonth)
                    ->first();

                if ($existing) {
                    if ($existing->cycle_id !== $cycle->id) {
                        throw new RuntimeException(
                            "Laporan {$reportMonth} sudah terdaftar "
                            . 'pada siklus siswa yang berbeda.'
                        );
                    }

                    $reports->push($existing);

                    continue;
                }

                // Semua enrollment yang berlaku pada awal bulan laporan.
                $enrollments = StudentEnrollment::query()
                    ->where('student_id', $cycle->student_id)
                    ->whereDate('starts_on', '<=', $reportMonth)
                    ->where(function ($query) use ($reportMonth) {
                        $query->whereNull('ends_on')
                            ->orWhereDate('ends_on', '>=', $reportMonth);
                    })
                    ->orderByDesc('starts_on')
                    ->get();

                if ($enrollments->isEmpty()) {
                    throw new RuntimeException(
                        "Enrollment cabang tidak ditemukan untuk "
                        . "siswa {$cycle->student_id} pada {$reportMonth}."
                    );
                }

                // Beberapa enrollment dalam cabang yang sama diperbolehkan.
                // Namun, cabang berbeda pada bulan yang sama menimbulkan konflik.
                $branchIds = $enrollments
                    ->pluck('branch_id')
                    ->unique()
                    ->values();

                if ($branchIds->count() > 1) {
                    throw new RuntimeException(
                        "Enrollment cabang ambigu untuk "
                        . "siswa {$cycle->student_id} pada {$reportMonth}."
                    );
                }

                // Gunakan enrollment terbaru dari cabang yang sama.
                $enrollment = $enrollments->first();

                // Semua penugasan guru pemilik yang berlaku
                // pada awal bulan laporan.
                $assignments = StudentTeacherAssignment::query()
                    ->where('student_id', $cycle->student_id)
                    ->where('is_report_owner', true)
                    ->whereDate('starts_on', '<=', $reportMonth)
                    ->where(function ($query) use ($reportMonth) {
                        $query->whereNull('ends_on')
                            ->orWhereDate('ends_on', '>=', $reportMonth);
                    })
                    ->orderByDesc('starts_on')
                    ->get();

                if ($assignments->isEmpty()) {
                    throw new RuntimeException(
                        "Guru pemilik laporan tidak ditemukan untuk "
                        . "siswa {$cycle->student_id} pada {$reportMonth}."
                    );
                }

                if ($assignments->count() > 1) {
                    throw new RuntimeException(
                        "Guru pemilik laporan ambigu untuk "
                        . "siswa {$cycle->student_id} pada {$reportMonth}."
                    );
                }

                $assignment = $assignments->first();

                // Asumsi sementara: tenggat dihitung dalam bulan
                // laporan yang sama. Kebijakan ini perlu dikonfirmasi.
                $dueDay = min(
                    $settings->default_report_due_day,
                    $month->daysInMonth
                );

                $dueOn = $month
                    ->setDay($dueDay)
                    ->toDateString();

                $report = MonthlyReport::query()->forceCreate([
                    'id' => (string) Str::uuid(),
                    'cycle_id' => $cycle->id,
                    'student_id' => $cycle->student_id,
                    'branch_id' => $enrollment->branch_id,
                    'teacher_id' => $assignment->teacher_id,
                    'report_month' => $reportMonth,
                    'due_on' => $dueOn,
                    'video_target' =>
                        $settings->default_monthly_video_target,
                    'status' => 'draft',
                ]);

                $reports->push($report);
            }

            return $reports;
        });
    }

    /**
     * Memproses seluruh siklus milik seorang siswa.
     *
     * @return Collection<int, MonthlyReport>
     */
    public function generateForStudent(
        \App\Models\Student $student
    ): Collection {
        $reports = new Collection();

        $cycles = ReportCycle::query()
            ->where('student_id', $student->id)
            ->orderBy('start_month')
            ->get();

        foreach ($cycles as $cycle) {
            $cycleReports = $this->generateForCycle($cycle);

            foreach ($cycleReports as $report) {
                $reports->push($report);
            }
        }

        return $reports;
    }
}