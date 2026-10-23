<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\QuarterlyReport;
use App\Models\ReportWhatsappShare;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportWhatsappService
{
    public const METHODS = ['link' => 'Kirim tautan rapor', 'pdf' => 'Kirim file PDF'];
    public const SALUTATIONS = ['neutral' => 'Netral (Anda)', 'ibu' => 'Ibu', 'bapak' => 'Bapak',
        'wali' => 'Bapak/Ibu atau Wali', 'name' => 'Nama penerima'];
    public const STATUSES = ['prepared' => 'Pesan disiapkan', 'opened' => 'Permintaan buka WhatsApp dicatat',
        'confirmed' => 'Dikonfirmasi terkirim oleh petugas'];

    public function __construct(private QuarterlyReportAccessService $access, private QuarterlyReportContentService $content,
        private StudentPortalService $portal, private QuarterlyReportPdfService $pdf) {}

    public function actor(User $actor, QuarterlyReport $report, bool $write = false): User
    {
        $actor = $actor->fresh();
        abort_unless($actor && $actor->is_active, 403);
        $this->access->authorize($actor, $report);
        if ($write) {
            abort_unless(in_array($actor->role_code, ['teacher', 'superadmin'], true), 403);
        }

        return $actor;
    }

    public function options(array $input): array
    {
        return Validator::make($input, ['method' => ['sometimes', Rule::in(array_keys(self::METHODS))],
            'salutation' => ['sometimes', Rule::in(array_keys(self::SALUTATIONS))]])->validate()
            + ['method' => 'link', 'salutation' => 'neutral'];
    }

    public function phone(?string $value): ?string
    {
        $value = trim($value ?? '');
        if (! preg_match('/^\+?[0-9 ()-]+$/D', $value)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return preg_match('/^[1-9][0-9]{7,14}$/D', $digits) ? $digits : null;
    }

    public function context(User $actor, QuarterlyReport $report): array
    {
        $actor = $this->actor($actor, $report);
        $report = $report->fresh(['cycle', 'student.portalUser', 'teacher.user', 'branch']);
        $state = $this->portal->quarterlyState($report);
        $reasons = $state['reasons'];
        $contacts = Guardian::where('student_id', $report->student_id)->where('is_primary', true)->orderBy('id')->get();
        $recipient = $contacts->count() === 1 ? $contacts->first() : null;
        $phone = $recipient ? $this->phone($recipient->whatsapp_number) : null;
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
        $sources = $this->content->sources($report->cycle);
        foreach ($sources as $source) {
            $monthly = $source['report'];
            if ($monthly && ! $this->portal->monthlyState($monthly)['ready']) {
                $reasons[] = 'Salinan persetujuan atau pembayaran laporan bulanan perlu diperiksa Admin.';
            }
            $saved = collect($report->approved_snapshot['monthly_sources'] ?? [])->firstWhere('month', $source['month']);
            $hash = $monthly?->approved_snapshot ? hash('sha256', json_encode($monthly->approved_snapshot, JSON_THROW_ON_ERROR)) : null;
            if (! $monthly || ! $saved || ($saved['report_id'] ?? null) !== $monthly->id || ! $hash || ($saved['approval_hash'] ?? null) !== $hash) {
                $reasons[] = 'Sumber bulanan berubah atau salinan final belum sesuai. Tinjau ulang rapor final.';
            }
        }
        $url = route('student.reports.quarterly', $report);
        $file = $state['file'];
        $contact = ['recipient_name' => $recipient?->full_name, 'relationship' => $recipient?->relationship_name,
            'recipient_phone' => $phone, 'teacher_name' => $report->teacher?->user?->full_name,
            'teacher_phone' => $report->teacher?->whatsapp_number, 'student_name' => $report->approved_snapshot['student_name'] ?? $report->student->full_name,
            'branch_name' => $report->approved_snapshot['template']['institution_name'] ?? 'Dharma Private English',
            'branch' => $report->branch->name, 'operator_name' => $actor->full_name,
            'pdf_version' => $file?->version_number, 'approval_revision' => $report->revision_number,
            'period' => $report->cycle->start_month->locale('id')->translatedFormat('F Y').' – '.$report->cycle->end_month->locale('id')->translatedFormat('F Y')];
        $payments = $report->student->monthlyCharges()->whereBetween('charge_month', [$report->cycle->start_month->toDateString(), $report->cycle->end_month->copy()->endOfMonth()->toDateString()])
            ->with('paymentProofs')->orderBy('id')->get()->map(fn ($r) => [$r->getAttributes(), $r->paymentProofs->sortBy('id')->values()->map->getAttributes()->all()])->all();
        $signature = hash_hmac('sha256', json_encode([$actor->only(['id', 'is_active', 'role_code']), $report->getAttributes(),
            $report->cycle->getAttributes(), $report->student->only(['portal_user_id']), $report->student->portalUser?->only(['id', 'role_code', 'is_active']),
            $report->teacher?->getAttributes(), $report->teacher?->user?->only(['id', 'full_name', 'role_code', 'is_active']),
            $contacts->map->getAttributes()->all(), $contact, $file?->getAttributes(), $payments, $url,
            collect($sources)->map(fn ($s) => $s['report']?->getAttributes())->all()], JSON_THROW_ON_ERROR), config('app.key'));

        return compact('report', 'recipient', 'phone', 'file', 'url', 'contact', 'signature')
            + ['ready' => ! $reasons, 'reasons' => array_values(array_unique($reasons))];
    }

    public function message(array $context, string $method, string $salutation): string
    {
        $zone = $context['report']->branch->timezone_name;
        $hour = now(in_array($zone, timezone_identifiers_list(), true) ? $zone : config('app.timezone'))->hour;
        $greeting = match (true) { $hour < 11 => 'Selamat pagi', $hour < 15 => 'Selamat siang', $hour < 18 => 'Selamat sore', default => 'Selamat malam' };
        $contact = $context['contact'];
        $address = match ($salutation) { 'ibu' => 'Ibu', 'bapak' => 'Bapak', 'wali' => self::SALUTATIONS['wali'],
            'name' => $contact['recipient_name'] ?: 'Anda', default => 'Anda' };
        $opening = $salutation === 'neutral' ? $greeting : $greeting.' '.$address;
        $learningPeriod = $context['learning_period_text'] ?? 'selama 3 bulan terakhir';
        $text = $opening.', mohon maaf mengganggu waktunya. Saya '.$contact['teacher_name'].', tutor les Bahasa Inggris '.$contact['student_name'].' di Dharma Private English '.$contact['branch'].'.'
            ."\n\nSaya ingin menyampaikan raport hasil pembelajaran siswa ".$learningPeriod.'. Terima kasih atas dukungan '.$address.' selama ini dalam mendampingi proses belajar anak.'
            ."\n\nSaya sangat menghargai usaha dan perkembangan yang telah ditunjukkan oleh siswa selama periode ini, dan saya berharap ".$address.' juga dapat terus memberikan apresiasi serta dukungan di rumah agar motivasi belajar siswa tetap terjaga.'
            ."\n\nPerlu saya sampaikan bahwa penilaian ini dilakukan berdasarkan performa siswa ".$learningPeriod.', baik dari segi pemahaman materi, keaktifan, maupun konsistensi dalam belajar.'
            ."\n\nSebagai penutup, saya mohon bantuan ".$address.' untuk terus mengingatkan siswa agar lebih rutin dan disiplin dalam mengikuti les, karena kehadiran dan keterlibatan selama proses belajar sangat berpengaruh terhadap perkembangan kemampuan dan hasil belajar siswa.'
            ."\n\nApabila terdapat kendala atau pertanyaan terkait raport maupun perkembangan siswa, ".$address.' dapat menghubungi saya kapan saja.'
            ."\n\nTerima kasih atas perhatian dan kerja samanya.\n\nSalam hangat,\n\n".$contact['teacher_name'];

        return $text."\n\nPeriode: ".$contact['period'].($method === 'link'
            ? "\nRapor dapat dibuka melalui akun siswa:\n".$context['url']
            : "\nSaya sertakan file PDF rapor pada pesan ini.");
    }

    public function previewSignature(array $context, array $options, string $key, string $message): string
    {
        return hash_hmac('sha256', json_encode([$context['signature'], $options, $key, $message], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function prepare(User $actor, QuarterlyReport $report, array $input): ReportWhatsappShare
    {
        $input = Validator::make($input, ['method' => ['required', Rule::in(array_keys(self::METHODS))],
            'salutation' => ['required', Rule::in(array_keys(self::SALUTATIONS))], 'request_key' => ['required', 'uuid'],
            'expected_snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']])->validate();

        return DB::transaction(function () use ($actor, $report, $input) {
            $report = QuarterlyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $actor = $this->actor($actor, $report, true);
            $existing = ReportWhatsappShare::where('request_key', $input['request_key'])->first();
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

            return ReportWhatsappShare::create(['id' => (string) Str::uuid(), 'report_id' => $report->id, 'file_id' => $context['file']->id,
                'teacher_id' => $report->teacher_id, 'actor_user_id' => $actor->id, 'guardian_id' => $context['recipient']->id,
                'request_key' => $input['request_key'], 'method' => $input['method'], 'salutation' => $input['salutation'], 'status' => 'prepared',
                'source_signature' => $context['signature'], 'preview_signature' => $input['expected_snapshot'], 'contact_snapshot' => $context['contact'],
                'portal_url' => $context['url'], 'message' => $message, 'prepared_at' => now()]);
        }, 3);
    }

    public function operation(User $actor, QuarterlyReport $report, ReportWhatsappShare $share, string $action): string|ReportWhatsappShare
    {
        return DB::transaction(function () use ($actor, $report, $share, $action) {
            $report = QuarterlyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $actor = $this->actor($actor, $report, true);
            $share = ReportWhatsappShare::whereKey($share->id)->lockForUpdate()->firstOrFail();
            abort_unless($share->report_id === $report->id && $share->actor_user_id === $actor->id, 403);
            $this->lockInputs($report);
            $context = $this->context($actor, $report);
            $this->assertReady($context);
            if (! hash_equals($share->source_signature, $context['signature']) || $share->file_id !== $context['file']->id) {
                $this->stale();
            }
            if ($action !== 'confirm' && $share->prepared_at->lt(now()->subMinutes(30))) {
                throw ValidationException::withMessages(['share' => 'Pesan sudah lebih dari 30 menit. Siapkan pesan baru agar penerima dan salam diperiksa kembali.']);
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
                $bytes = $this->pdf->verifiedBytes($context['file']);
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
        }, 3);
    }

    private function lockInputs(QuarterlyReport $report): void
    {
        // Recheck the protected sources inside the same transaction as the journal change.
        $report->student()->lockForUpdate()->first();
        $report->teacher()->lockForUpdate()->first();
        Guardian::where('student_id', $report->student_id)->orderBy('id')->lockForUpdate()->get();
        $report->loadMissing('cycle');
        $start = $report->cycle->start_month->toDateString();
        $end = $report->cycle->end_month->copy()->endOfMonth()->toDateString();
        \App\Models\MonthlyReport::where('student_id', $report->student_id)->whereBetween('report_month', [$start, $end])
            ->orderBy('id')->lockForUpdate()->get();
        $charges = \App\Models\MonthlyCharge::where('student_id', $report->student_id)->whereBetween('charge_month', [$start, $end])
            ->orderBy('id')->lockForUpdate()->get();
        \App\Models\PaymentProof::whereIn('charge_id', $charges->pluck('id'))->orderBy('id')->lockForUpdate()->get();
    }

    private function assertReady(array $context): void
    {
        if (! $context['ready']) {
            throw ValidationException::withMessages(['share' => implode(' ', $context['reasons'])]);
        }
    }

    private function stale(): never
    {
        throw ValidationException::withMessages(['share' => 'Data rapor, pembayaran, kontak, atau pesan berubah. Kembali ke pratinjau dan siapkan pesan baru.']);
    }
}
