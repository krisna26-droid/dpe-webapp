<?php

namespace App\Services;

use App\Models\MonthlyCharge;
use App\Models\MonthlyReport;
use App\Models\QuarterlyReport;
use App\Models\ReportCycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuarterlyReportContentService
{
    public const SKILLS = ['speaking' => 'Speaking', 'reading' => 'Reading', 'listening' => 'Listening', 'writing' => 'Writing'];

    public const SCORES = ['A', 'B', 'C'];

    public const STATUSES = ['draft' => 'Draf', 'submitted' => 'Diajukan', 'revision' => 'Perlu revisi', 'approved' => 'Disetujui'];

    public function __construct(private ReportTemplateService $template, private ReportSignatureService $signatures) {}

    public function months(ReportCycle $cycle): array
    {
        return collect(range(0, 2))->map(fn ($offset) => CarbonImmutable::parse($cycle->start_month)->startOfMonth()->addMonths($offset)->toDateString())->all();
    }

    public function cycleErrors(ReportCycle $cycle): array
    {
        $cycle->loadMissing('student');
        $start = CarbonImmutable::parse($cycle->start_month);
        $end = CarbonImmutable::parse($cycle->end_month);
        $join = CarbonImmutable::parse($cycle->student->began_on)->startOfMonth();
        $offset = ($start->year - $join->year) * 12 + $start->month - $join->month;
        if ($start->day !== 1 || $end->day !== 1 || ! $end->equalTo($start->addMonths(2)) || $offset < 0 || $offset % 3 !== 0) {
            return ['period' => 'Siklus harus tiga bulan kalender berurutan sejak bulan siswa bergabung. Periksa periode melalui Admin.'];
        }

        return [];
    }

    public function sources(ReportCycle $cycle): array
    {
        $rows = MonthlyReport::where('student_id', $cycle->student_id)->where(function ($query) use ($cycle) {
            foreach ($this->months($cycle) as $month) {
                $query->orWhereDate('report_month', $month);
            }
        })->orderBy('report_month')->get();

        return collect($this->months($cycle))->map(fn ($month) => ['month' => $month, 'report' => $rows->first(fn ($r) => $r->report_month->toDateString() === $month),
            'matches_cycle' => $rows->first(fn ($r) => $r->report_month->toDateString() === $month)?->cycle_id === $cycle->id])->all();
    }

    public function preparation(ReportCycle $cycle): array
    {
        $sources = $this->sources($cycle);
        $errors = $this->cycleErrors($cycle);
        if (collect($sources)->contains(fn ($s) => ! $s['report'] || ! $s['matches_cycle'])) {
            $errors['monthly_periods'] = 'Admin perlu menyiapkan ketiga periode bulanan dalam siklus ini terlebih dahulu. Isian bulanan boleh masih draf.';
        }
        $branch = collect($sources)->pluck('report')->filter()->last()?->branch_id;

        return ['errors' => $errors, 'sources' => $sources, 'branch_id' => $branch];
    }

    public function preparationSignature(ReportCycle $cycle): string
    {
        $cycle->loadMissing('student');

        return hash('sha256', json_encode([$cycle->getAttributes(), $cycle->student->only(['full_name', 'began_on']),
            collect($this->sources($cycle))->map(fn ($s) => [$s['month'], $s['report']?->only(['id', 'cycle_id', 'student_id', 'branch_id', 'teacher_id'])])->all(),
            app(QuarterlyReportAccessService::class)->owners($cycle)->map(fn ($t) => [$t->id, $t->user->full_name])->all()], JSON_THROW_ON_ERROR));
    }

    public function readiness(QuarterlyReport $report): array
    {
        $report->loadMissing(['cycle.student', 'branch', 'student', 'teacher.user']);
        $errors = $this->cycleErrors($report->cycle);
        $completed = 0;
        foreach (self::SKILLS as $code => $label) {
            $row = is_array($report->skills) ? ($report->skills[$code] ?? []) : [];
            if (! is_array($row) || ! in_array($row['score'] ?? null, self::SCORES, true) || ! is_string($row['description'] ?? null) || blank($row['description']) || mb_strlen($row['description']) > 4000) {
                $errors['skills.'.$code] = $label.': pilih A/B/C dan isi deskripsi (maksimal 4.000 karakter).';
            } else {
                $completed++;
            }
        }
        if (blank($report->parent_note) || mb_strlen($report->parent_note) > 8000) {
            $errors['parent_note'] = 'Note untuk orang tua wajib diisi dan maksimal 8.000 karakter.';
        }
        if ($report->student_id !== $report->cycle->student_id) {
            $errors['period'] = 'Identitas siswa pada rapor dan siklus tidak sesuai.';
        }
        if (! $report->teacher?->is_active || ! $report->teacher?->user?->is_active || $report->teacher?->user?->role_code !== 'teacher') {
            $errors['teacher_id'] = 'Teacher pemilik perlu memiliki profil dan akun Teacher aktif. Admin dapat memperbaiki pemilik saat draf.';
        }
        $zone = $report->branch?->timezone_name;
        $today = now(in_array($zone, timezone_identifiers_list(), true) ? $zone : config('app.timezone'))->toDateString();
        if ($report->cycle->end_month->toDateString() > $today || $report->student->began_on->toDateString() > $today) {
            $errors['period_time'] = 'Bulan ketiga periode belum berlangsung. Draf dapat disiapkan, tetapi pengajuan menunggu periode tersebut.';
        }

        return ['ready' => ! $errors, 'errors' => $errors, 'completed' => $completed];
    }

    public function gate(QuarterlyReport $report): array
    {
        $report->loadMissing('cycle');
        $reasons = [];
        $months = [];
        foreach ($this->sources($report->cycle) as $source) {
            $monthlyOk = $source['matches_cycle'] && $source['report']?->status === 'approved';
            $charges = MonthlyCharge::where('student_id', $report->student_id)->whereYear('charge_month', substr($source['month'], 0, 4))->whereMonth('charge_month', substr($source['month'], 5, 2))->get();
            $paid = $charges->count() === 1 && $charges->first()->paymentProofs()->where('status', 'approved')->exists();
            $label = CarbonImmutable::parse($source['month'])->locale('id')->translatedFormat('F Y');
            if (! $monthlyOk) {
                $reasons[] = $label.': laporan bulanan belum disetujui.';
            }
            if (! $paid) {
                $reasons[] = $label.': pembayaran belum tervalidasi (oleh Super Admin).';
            }
            $months[] = $source + ['monthly_ok' => $monthlyOk, 'paid' => $paid, 'label' => $label];
        }
        if ($report->status !== 'approved') {
            $reasons[] = 'Rapor final belum disetujui.';
        }

        return ['months' => $months, 'reasons' => $reasons, 'ready' => ! $reasons,
            'internal_pdf_ready' => $report->status === 'approved' && collect($months)->every(fn ($m) => $m['monthly_ok'])];
    }

    public function signature(QuarterlyReport $report): string
    {
        $report->loadMissing(['cycle.student', 'student', 'teacher.user', 'branch']);

        return hash('sha256', json_encode([$report->getAttributes(), $report->cycle->getAttributes(),
            $report->student->only(['full_name', 'began_on']), $report->teacher?->user?->only(['full_name', 'is_active', 'role_code']), $report->teacher?->is_active,
            $report->branch->only(['name', 'timezone_name']),
            $report->status === 'approved' ? null : $this->template->snapshot(),
            $report->status === 'approved' ? null : $this->signatures->snapshot($report->teacher_id,
                (string) $report->teacher?->user?->full_name, $this->template->settings()['fields']['manager_name']),
            collect($this->sources($report->cycle))->map(fn ($s) => [$s['matches_cycle'], $s['report']?->only(['id', 'cycle_id', 'branch_id', 'teacher_id', 'status', 'approved_at', 'approved_snapshot'])])->all(),
            $report->events()->orderBy('id')->get(['id', 'event_type', 'occurred_at'])->toArray()], JSON_THROW_ON_ERROR));
    }

    public function assertSignature(string $current, array $data): void
    {
        $valid = Validator::make($data, ['expected_snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']])->validate();
        if (! hash_equals($current, $valid['expected_snapshot'])) {
            throw ValidationException::withMessages(['expected_snapshot' => 'Rapor, sumber bulanan, template, atau tanda tangan berubah. Muat data terbaru dan periksa kembali.']);
        }
    }

    public function snapshot(QuarterlyReport $report): array
    {
        $report->loadMissing(['student', 'teacher.user', 'cycle', 'branch']);

        return ['snapshot_version' => 1, 'report_id' => $report->id, 'revision_number' => $report->revision_number,
            'student_id' => $report->student_id, 'student_name' => $report->student->full_name, 'branch_id' => $report->branch_id,
            'teacher_id' => $report->teacher_id, 'teacher_name' => $report->teacher?->user?->full_name,
            'cycle_id' => $report->cycle_id, 'start_month' => $report->cycle->start_month->toDateString(), 'end_month' => $report->cycle->end_month->toDateString(),
            'skills' => $report->skills ?? [], 'parent_note' => $report->parent_note,
            'template' => $this->template->snapshot(),
            'signatures' => $this->signatures->snapshot($report->teacher_id, (string) $report->teacher?->user?->full_name,
                $this->template->settings()['fields']['manager_name']),
            'monthly_sources' => collect($this->sources($report->cycle))->map(fn ($s) => ['month' => $s['month'], 'report_id' => $s['report']?->id, 'status' => $s['report']?->status,
                'approval_hash' => $s['report']?->approved_snapshot ? hash('sha256', json_encode($s['report']->approved_snapshot, JSON_THROW_ON_ERROR)) : null])->all()];
    }
}
