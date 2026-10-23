<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\MonthlyCharge;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportWhatsappShare;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MonthlyReportWhatsappService
{
    public const METHODS = ReportWhatsappService::METHODS;
    public const SALUTATIONS = ReportWhatsappService::SALUTATIONS;
    public const STATUSES = ReportWhatsappService::STATUSES;

    public function __construct(private StudentProgressAccessService $access, private StudentPortalService $portal,
        private MonthlyReportPdfService $pdf, private ReportWhatsappService $shared) {}

    public function actor(User $actor, MonthlyReport $report, bool $write = false): User
    {
        $actor = $write ? User::whereKey($actor->id)->lockForUpdate()->first() : $actor->fresh();
        abort_unless($actor?->is_active, 403);
        $this->access->authorize($actor, $report);
        if ($write) {
            abort_unless(in_array($actor->role_code, ['teacher', 'superadmin'], true), 403);
        }

        return $actor;
    }

    public function options(array $input): array
    {
        return $this->shared->options($input);
    }

    public function context(User $actor, MonthlyReport $report): array
    {
        $actor = $this->actor($actor, $report);
        $report = $report->fresh(['student.portalUser', 'teacher.user', 'branch']);
        $state = $this->portal->monthlyState($report);
        $reasons = $state['reasons'];
        $snapshot = null;
        $file = null;
        if ($state['ready']) {
            try {
                $snapshot = $this->pdf->snapshot($report);
                $file = $this->pdf->current($report, $snapshot);
            } catch (ValidationException $error) {
                $reasons = [...$reasons, ...collect($error->errors())->flatten()->all()];
            }
        }
        $contacts = Guardian::where('student_id', $report->student_id)->where('is_primary', true)->orderBy('id')->get();
        $recipient = $contacts->count() === 1 ? $contacts->first() : null;
        $phone = $recipient ? $this->shared->phone($recipient->whatsapp_number) : null;
        if (! $recipient || ! filled($recipient->full_name)) {
            $reasons[] = 'Tetapkan tepat satu kontak penerima utama pada data wali/penerima rapor siswa.';
        } elseif (! $phone) {
            $reasons[] = 'Nomor WhatsApp penerima utama belum valid. Minta Admin memperbaiki data kontak.';
        }
        if (! $report->teacher?->is_active || ! $report->teacher?->user?->is_active || $report->teacher?->user?->role_code !== 'teacher') {
            $reasons[] = 'Profil dan akun Teacher pemilik rapor harus aktif.';
        }
        if (! $report->student?->portalUser?->is_active || $report->student?->portalUser?->role_code !== 'student') {
            $reasons[] = 'Akun portal anak harus aktif agar penerima dapat membuka rapor.';
        }
        $url = route('student.reports.monthly', $report);
        $public = $report->approved_snapshot['public_content'] ?? [];
        $period = $report->report_month->locale('id')->translatedFormat('F Y');
        $contact = ['recipient_name' => $recipient?->full_name, 'relationship' => $recipient?->relationship_name,
            'recipient_phone' => $phone, 'teacher_name' => $public['teacher_name'] ?? $report->teacher?->user?->full_name,
            'teacher_phone' => $report->teacher?->whatsapp_number, 'student_name' => $public['student_name'] ?? $report->student->full_name,
            'branch' => $public['branch_name'] ?? $report->branch->name, 'operator_name' => $actor->full_name,
            'pdf_version' => $file?->version_number, 'approval_label' => 'Disetujui '.($report->approved_at?->format('d M Y H:i') ?? '—'),
            'period' => $period];
        $charges = MonthlyCharge::where('student_id', $report->student_id)
            ->whereYear('charge_month', $report->report_month->year)->whereMonth('charge_month', $report->report_month->month)
            ->with('paymentProofs')->orderBy('id')->get()->map(fn ($charge) => [$charge->getAttributes(),
                $charge->paymentProofs->sortBy('id')->values()->map->getAttributes()->all()])->all();
        $signature = hash_hmac('sha256', json_encode([$actor->only(['id', 'full_name', 'role_code', 'is_active']),
            $report->getAttributes(), $snapshot, $report->teacher?->getAttributes(),
            $report->teacher?->user?->only(['id', 'full_name', 'role_code', 'is_active']),
            $report->student->only(['id', 'portal_user_id']), $report->student?->portalUser?->only(['id', 'role_code', 'is_active']),
            $contacts->map->getAttributes()->all(), $charges, $url], JSON_THROW_ON_ERROR), config('app.key'));

        return compact('report', 'snapshot', 'recipient', 'phone', 'file', 'url', 'contact', 'signature')
            + ['ready' => ! $reasons, 'reasons' => array_values(array_unique($reasons)), 'learning_period_text' => 'pada bulan '.$period];
    }

    public function message(array $context, string $method, string $salutation): string
    {
        return $this->shared->message($context, $method, $salutation);
    }

    public function previewSignature(array $context, array $options, string $key, string $message): string
    {
        return $this->shared->previewSignature($context, $options, $key, $message);
    }

    public function prepare(User $actor, MonthlyReport $report, array $input): MonthlyReportWhatsappShare
    {
        $input = Validator::make($input, ['method' => ['required', Rule::in(array_keys(ReportWhatsappService::METHODS))],
            'salutation' => ['required', Rule::in(array_keys(ReportWhatsappService::SALUTATIONS))], 'request_key' => ['required', 'uuid'],
            'expected_snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']])->validate();
        $writtenKey = null;
        try {
            return DB::transaction(function () use ($actor, $report, $input, &$writtenKey) {
                $report = MonthlyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
                $actor = $this->actor($actor, $report, true);
                $existing = MonthlyReportWhatsappShare::where('request_key', $input['request_key'])->first();
                if ($existing) {
                    abort_unless($existing->report_id === $report->id && $existing->actor_user_id === $actor->id, 403);
                    if (! hash_equals($existing->preview_signature, $input['expected_snapshot'])) {
                        $this->stale();
                    }

                    return $existing;
                }
                $this->lockInputs($report);
                $context = $this->context($actor, $report);
                $this->assertReady($context);
                $options = $this->options($input);
                $message = $this->message($context, $input['method'], $input['salutation']);
                if (! hash_equals($this->previewSignature($context, $options, $input['request_key'], $message), $input['expected_snapshot'])) {
                    $this->stale();
                }
                $file = $input['method'] === 'pdf' ? $this->pdf->store($actor, $report, $context['snapshot'], $writtenKey) : null;
                $contact = $context['contact'];
                $contact['pdf_version'] = $file?->version_number;

                return MonthlyReportWhatsappShare::create(['id' => (string) Str::uuid(), 'report_id' => $report->id, 'file_id' => $file?->id,
                    'teacher_id' => $report->teacher_id, 'actor_user_id' => $actor->id, 'guardian_id' => $context['recipient']->id,
                    'request_key' => $input['request_key'], 'method' => $input['method'], 'salutation' => $input['salutation'], 'status' => 'prepared',
                    'source_signature' => $context['signature'], 'preview_signature' => $input['expected_snapshot'], 'contact_snapshot' => $contact,
                    'portal_url' => $context['url'], 'message' => $message, 'prepared_at' => now()]);
            });
        } catch (Throwable $error) {
            if ($writtenKey) {
                Storage::disk('local')->delete($writtenKey);
            }
            throw $error;
        }
    }

    public function operation(User $actor, MonthlyReport $report, MonthlyReportWhatsappShare $share, string $action): string|MonthlyReportWhatsappShare
    {
        return DB::transaction(function () use ($actor, $report, $share, $action) {
            $report = MonthlyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $actor = $this->actor($actor, $report, true);
            $share = MonthlyReportWhatsappShare::whereKey($share->id)->lockForUpdate()->firstOrFail();
            abort_unless($share->report_id === $report->id && $share->actor_user_id === $actor->id, 403);
            $this->lockInputs($report);
            $context = $this->context($actor, $report);
            $this->assertReady($context);
            if (! hash_equals($share->source_signature, $context['signature'])) {
                $this->stale();
            }
            if ($action !== 'confirm' && $share->prepared_at->lt(now()->subMinutes(30))) {
                throw ValidationException::withMessages(['share' => 'Pesan sudah lebih dari 30 menit. Siapkan pesan baru agar penerima dan salam diperiksa kembali.']);
            }
            if ($share->method === 'pdf') {
                $file = $share->pdf;
                if (! $file || $file->report_id !== $report->id || ! $this->pdf->matchesSnapshot($file->source_snapshot_hash, $context['snapshot'])
                    || $this->pdf->verifiedBytes($file) === null) {
                    throw ValidationException::withMessages(['pdf' => 'PDF bulanan hilang atau berubah. Siapkan pesan baru sebelum melanjutkan.']);
                }
            }
            if ($action === 'open') {
                $share->opened_at ??= now();
                if (! $share->confirmed_at) {
                    $share->status = 'opened';
                }
                $share->save();

                return 'https://wa.me/'.$context['phone'].'?text='.rawurlencode($share->message);
            }
            if ($action === 'download') {
                abort_unless($share->method === 'pdf', 404);
                $bytes = $this->pdf->verifiedBytes($share->pdf);
                abort_unless($bytes !== null, 404);
                $share->downloaded_at ??= now();
                $share->save();

                return $bytes;
            }
            abort_unless($action === 'confirm', 404);
            if (! $share->opened_at || ($share->method === 'pdf' && ! $share->downloaded_at)) {
                throw ValidationException::withMessages(['share' => 'Buka WhatsApp terlebih dahulu; untuk opsi PDF, unduh juga file sebelum mengonfirmasi pengiriman.']);
            }
            $share->confirmed_at ??= now();
            $share->status = 'confirmed';
            $share->save();

            return $share;
        });
    }

    private function lockInputs(MonthlyReport $report): void
    {
        $student = $report->student()->lockForUpdate()->first();
        if ($student?->portal_user_id) {
            User::whereKey($student->portal_user_id)->lockForUpdate()->first();
        }
        $teacher = $report->teacher()->lockForUpdate()->first();
        if ($teacher) {
            User::whereKey($teacher->user_id)->lockForUpdate()->first();
        }
        $report->branch()->lockForUpdate()->first();
        Guardian::where('student_id', $report->student_id)->orderBy('id')->lockForUpdate()->get();
        $charges = MonthlyCharge::where('student_id', $report->student_id)
            ->whereYear('charge_month', $report->report_month->year)->whereMonth('charge_month', $report->report_month->month)
            ->orderBy('id')->lockForUpdate()->get();
        PaymentProof::whereIn('charge_id', $charges->pluck('id'))->orderBy('id')->lockForUpdate()->get();
    }

    private function assertReady(array $context): void
    {
        if (! $context['ready']) {
            throw ValidationException::withMessages(['share' => implode(' ', $context['reasons'])]);
        }
    }

    private function stale(): never
    {
        throw ValidationException::withMessages(['share' => 'Data laporan bulanan, pembayaran, penerima, PDF, atau pesan berubah. Kembali ke pratinjau dan siapkan pesan baru.']);
    }
}
