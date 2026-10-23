<?php

namespace App\Services;

use App\Models\MonthlyReport;
use App\Models\ReportCycle;
use App\Models\Student;
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

        return DB::transaction(function () use (
            $cycle,
            $settings
        ) {
            $student = Student::whereKey($cycle->student_id)->lockForUpdate()->firstOrFail();
            $cycle = ReportCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $start = CarbonImmutable::parse($cycle->start_month)->startOfMonth();
            $end = CarbonImmutable::parse($cycle->end_month)->startOfMonth();
            if ($end->lt($start)) {
                throw new RuntimeException('Periode akhir siklus tidak boleh sebelum periode awal.');
            }
            $reports = new Collection;

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
                            .'pada siklus siswa yang berbeda.'
                        );
                    }

                    $reports->push($existing);

                    continue;
                }

                // Siswa baru dapat mulai setelah tanggal satu. Gunakan hari pertama
                // enrollment dalam bulan jika belum ada enrollment pada awal bulan.
                $referenceDate = max($reportMonth, $student->began_on->toDateString());
                if ($referenceDate > $month->endOfMonth()->toDateString()) {
                    throw new RuntimeException('Periode laporan tidak boleh sebelum bulan mulai les siswa.');
                }
                $hasEnrollmentAtStart = StudentEnrollment::query()
                    ->where('student_id', $cycle->student_id)
                    ->whereDate('starts_on', '<=', $referenceDate)
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $referenceDate))
                    ->exists();
                if (! $hasEnrollmentAtStart) {
                    $referenceDate = StudentEnrollment::query()->where('student_id', $cycle->student_id)
                        ->whereDate('starts_on', '>=', $referenceDate)->whereDate('starts_on', '<=', $month->endOfMonth()->toDateString())
                        ->min('starts_on') ?? $referenceDate;
                    $referenceDate = CarbonImmutable::parse($referenceDate)->toDateString();
                }

                $enrollments = StudentEnrollment::query()
                    ->where('student_id', $cycle->student_id)
                    ->whereDate('starts_on', '<=', $referenceDate)
                    ->where(function ($query) use ($referenceDate) {
                        $query->whereNull('ends_on')
                            ->orWhereDate('ends_on', '>=', $referenceDate);
                    })
                    ->orderByDesc('starts_on')
                    ->get();

                if ($enrollments->isEmpty()) {
                    throw new RuntimeException(
                        'Enrollment cabang tidak ditemukan untuk '
                        ."siswa {$cycle->student_id} pada {$reportMonth}."
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
                        'Enrollment cabang ambigu untuk '
                        ."siswa {$cycle->student_id} pada {$reportMonth}."
                    );
                }

                // Gunakan enrollment terbaru dari cabang yang sama.
                $enrollment = $enrollments->first();

                // Automatic generation needs one explicit, active report owner during
                // the student's enrollment in this month, even if the owner began later.
                $assignments = StudentTeacherAssignment::query()
                    ->where('student_id', $cycle->student_id)
                    ->where('is_report_owner', true)
                    ->whereHas('teacher', fn ($teacher) => $teacher->where('is_active', true)
                        ->whereHas('user', fn ($user) => $user->where('is_active', true)->where('role_code', 'teacher')))
                    ->whereDate('starts_on', '<=', $month->endOfMonth()->toDateString())
                    ->where(function ($query) use ($referenceDate) {
                        $query->whereNull('ends_on')
                            ->orWhereDate('ends_on', '>=', $referenceDate);
                    })
                    ->orderByDesc('starts_on')
                    ->get();
                $periodEnrollments = StudentEnrollment::where('student_id', $student->id)->where('branch_id', $enrollment->branch_id)
                    ->whereDate('starts_on', '<=', $month->endOfMonth()->toDateString())
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $referenceDate))->get();
                $assignments = $assignments->filter(fn ($assignment) => $periodEnrollments->contains(fn ($period) => max($referenceDate, $assignment->starts_on->toDateString(), $period->starts_on->toDateString())
                    <= min($month->endOfMonth()->toDateString(), $assignment->ends_on?->toDateString() ?? '9999-12-31', $period->ends_on?->toDateString() ?? '9999-12-31')))
                    ->unique('teacher_id')->values();

                if ($assignments->isEmpty()) {
                    throw new RuntimeException(
                        'Guru pemilik laporan tidak ditemukan untuk '
                        ."siswa {$cycle->student_id} pada {$reportMonth}."
                    );
                }

                if ($assignments->count() > 1) {
                    throw new RuntimeException(
                        'Guru pemilik laporan ambigu untuk '
                        ."siswa {$cycle->student_id} pada {$reportMonth}."
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
                    'video_target' => $settings->default_monthly_video_target,
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
        Student $student
    ): Collection {
        $reports = new Collection;

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
