<?php

namespace Tests\Feature;

use App\Models\{Guardian, MonthlyReport, MonthlyReportFile, MonthlyReportWhatsappShare, Teacher};
use App\Services\{MonthlyReportPdfService, MonthlyReportWhatsappService, PortalPaymentService, ReportTemplateService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WhatsappReportFixtures;
use Tests\TestCase;

class MonthlyReportDeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase, WhatsappReportFixtures;

    private function readyMonth(string $month = '2026-07-01'): MonthlyReport
    {
        $report = $this->student->monthlyReports()->whereDate('report_month', $month)->firstOrFail();
        $report = $this->approveMonth($report);
        $this->decide($this->upload($this->charge($month)));
        Guardian::firstOrCreate(['student_id' => $this->student->id, 'is_primary' => true], [
            'id' => (string) Str::uuid(), 'full_name' => 'Wali Bulanan', 'relationship_name' => 'Orang tua', 'whatsapp_number' => '081234567899']);

        return $report;
    }

    private function monthlyUrl(MonthlyReport $report, string $action = 'index', ?MonthlyReportWhatsappShare $share = null, string $role = 'teacher'): string
    {
        return route($role.'.monthly-reports.whatsapp.'.$action, array_filter([$report, $share]));
    }

    private function monthlyPayload(MonthlyReport $report, string $method = 'link', string $salutation = 'wali', $actor = null): array
    {
        $service = app(MonthlyReportWhatsappService::class);
        $context = $service->context($actor ?? $this->teacher->user, $report);
        $options = compact('method', 'salutation');
        $key = (string) Str::uuid();

        return $options + ['request_key' => $key,
            'expected_snapshot' => $service->previewSignature($context, $options, $key, $service->message($context, $method, $salutation))];
    }

    private function startMonth(MonthlyReport $report, string $method = 'pdf'): MonthlyReportWhatsappShare
    {
        $payload = $this->monthlyPayload($report, $method);
        $this->actingAs($this->teacher->user)->post($this->monthlyUrl($report, 'prepare'), $payload)->assertRedirect();

        return MonthlyReportWhatsappShare::where('request_key', $payload['request_key'])->firstOrFail();
    }

    public function test_monthly_sharing_uses_the_selected_month_and_does_not_wait_for_other_months(): void
    {
        $report = $this->readyMonth();
        $this->actingAs($this->teacher->user)->get(route('teacher.monthly-reports.show', $report))->assertOk()
            ->assertSee('Preview PDF bulanan')->assertSee('WhatsApp &amp; riwayat', false);
        $this->get($this->monthlyUrl($report))->assertOk()->assertViewHas('context', fn ($c) => $c['ready'] && $c['contact']['period'] === 'Juli 2026')
            ->assertViewHas('message', fn ($message) => str_contains($message, 'pada bulan Juli 2026')
                && str_contains($message, '/student/reports/monthly/'.$report->id)
                && ! str_contains($message, '3 bulan terakhir') && ! str_contains($message, '/quarterly/'))
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertSame(2, MonthlyReport::where('status', 'draft')->count());
        $this->assertDatabaseCount('quarterly_reports', 0);
        $this->assertDatabaseCount('monthly_report_files', 0);
        $this->assertDatabaseCount('monthly_report_whatsapp_shares', 0);
    }

    public function test_pdf_is_created_downloaded_opened_and_manually_confirmed_for_one_month(): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report);
        $file = $share->pdf;
        $snapshot = $file->public_snapshot;
        $this->assertSame('2026-07-01', $snapshot['report_month']);
        $this->assertSame($snapshot['report_month'], $snapshot['start_month']);
        $this->assertSame($snapshot['report_month'], $snapshot['end_month']);
        $this->assertCount(8, $snapshot['skills']);
        $this->assertSame('Improving', $snapshot['skills']['speaking']['score']);
        $this->assertStringNotContainsString('RAHASIA', json_encode($snapshot));
        $this->assertStringNotContainsString('/student/reports/monthly/', $share->message);
        $this->assertSame(1, substr_count($share->message, 'Selamat pagi'));
        $this->assertStringContainsString('Saya sertakan file PDF', $share->message);
        $this->assertSame(1, $file->version_number);
        $this->postJson($this->monthlyUrl($report, 'confirm', $share), ['sent_confirmed' => '1'])->assertUnprocessable();
        $download = $this->post($this->monthlyUrl($report, 'download', $share))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="DPE-Rapor-Bulanan-2026-07.pdf"');
        $this->assertStringStartsWith('%PDF-', $download->getContent());
        $this->assertSame(app(MonthlyReportPdfService::class)->verifiedBytes($file), $download->getContent());
        $this->postJson($this->monthlyUrl($report, 'confirm', $share), ['sent_confirmed' => '1'])->assertUnprocessable();
        $open = $this->post($this->monthlyUrl($report, 'open', $share))->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/6281234567899?text=', $open->headers->get('Location'));
        parse_str(parse_url($open->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame($share->message, $query['text']);
        $this->postJson($this->monthlyUrl($report, 'confirm', $share), [])->assertUnprocessable();
        $this->post($this->monthlyUrl($report, 'confirm', $share), ['sent_confirmed' => '1'])->assertRedirect();
        $confirmedAt = $share->fresh()->confirmed_at->toISOString();
        $this->post($this->monthlyUrl($report, 'confirm', $share), ['sent_confirmed' => '1'])->assertRedirect();
        $this->assertSame($confirmedAt, $share->fresh()->confirmed_at->toISOString());
        $this->assertSame('confirmed', $share->fresh()->status);
        $this->assertDatabaseCount('report_whatsapp_shares', 0);
        $this->assertDatabaseCount('quarterly_report_files', 0);
    }

    public function test_link_sharing_does_not_create_a_pdf_or_require_a_pdf_download(): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report, 'link');
        $this->assertNull($share->file_id);
        $this->assertDatabaseCount('monthly_report_files', 0);
        $this->post($this->monthlyUrl($report, 'download', $share))->assertNotFound();
        $this->post($this->monthlyUrl($report, 'open', $share))->assertRedirect();
        $this->post($this->monthlyUrl($report, 'confirm', $share), ['sent_confirmed' => '1'])->assertRedirect();
        $this->assertNotNull($share->fresh()->confirmed_at);
    }

    public function test_pdf_reads_approved_content_when_live_monthly_fields_have_been_changed(): void
    {
        $report = $this->readyMonth();
        $report->update(['development_summary' => 'ISIAN BERUBAH SETELAH PERSETUJUAN', 'parent_message' => 'DRAF BARU']);
        $snapshot = $this->startMonth($report->fresh())->pdf->public_snapshot;
        $this->assertSame('RINGKASAN PUBLIK', $snapshot['development_summary']);
        $this->assertSame('PESAN PUBLIK', $snapshot['parent_message']);
        $this->assertStringNotContainsString('ISIAN BERUBAH', json_encode($snapshot));
    }

    public function test_duplicate_submission_is_idempotent_and_new_messages_reuse_the_same_pdf(): void
    {
        $report = $this->readyMonth();
        $payload = $this->monthlyPayload($report, 'pdf');
        $this->actingAs($this->teacher->user)->post($this->monthlyUrl($report, 'prepare'), $payload)->assertRedirect();
        $this->post($this->monthlyUrl($report, 'prepare'), $payload)->assertRedirect();
        $this->assertDatabaseCount('monthly_report_files', 1);
        $this->assertDatabaseCount('monthly_report_whatsapp_shares', 1);
        $this->startMonth($report);
        $this->assertDatabaseCount('monthly_report_files', 1);
        $this->assertDatabaseCount('monthly_report_whatsapp_shares', 2);
    }

    public static function unavailableCases(): array
    {
        return [['report'], ['payment'], ['recipient'], ['phone'], ['portal']];
    }

    #[DataProvider('unavailableCases')]
    public function test_unavailable_report_payment_or_recipient_blocks_delivery(string $case): void
    {
        $report = $this->readyMonth();
        if ($case === 'report') $report->update(['status' => 'submitted']);
        if ($case === 'payment') $this->student->monthlyCharges()->first()->paymentProofs()->update(['status' => 'rejected']);
        if ($case === 'recipient') Guardian::query()->update(['is_primary' => false]);
        if ($case === 'phone') Guardian::query()->update(['whatsapp_number' => 'nomor-salah']);
        if ($case === 'portal') $this->parent->update(['is_active' => false]);
        $this->actingAs($this->teacher->user)->get($this->monthlyUrl($report))->assertOk()
            ->assertViewHas('context', fn ($context) => ! $context['ready'] && count($context['reasons']) > 0);
        $this->postJson($this->monthlyUrl($report, 'prepare'), $this->monthlyPayload($report))->assertUnprocessable();
        $this->assertDatabaseCount('monthly_report_whatsapp_shares', 0);
    }

    public function test_contact_changes_invalidate_the_preview_and_a_prepared_message(): void
    {
        $report = $this->readyMonth();
        $payload = $this->monthlyPayload($report);
        $share = $this->startMonth($report, 'link');
        Guardian::query()->update(['whatsapp_number' => '081234567888']);
        $this->postJson($this->monthlyUrl($report, 'prepare'), $payload)->assertUnprocessable();
        $this->postJson($this->monthlyUrl($report, 'open', $share))->assertUnprocessable();
        $this->postJson($this->monthlyUrl($report, 'confirm', $share), ['sent_confirmed' => '1'])->assertUnprocessable();
        $this->assertNull($share->fresh()->opened_at);
        $this->assertStringContainsString('6281234567899', $share->contact_snapshot['recipient_phone']);
    }

    public function test_admin_reviews_history_and_choices_but_cannot_prepare_or_operate_messages(): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report, 'link');
        $this->actingAs($this->admin)->get($this->monthlyUrl($report, role: 'admin'))->assertOk()->assertViewHas('canSend', false);
        $this->get($this->monthlyUrl($report, 'show', $share, 'admin'))->assertOk()->assertSee($share->message);
        $payload = $this->monthlyPayload($report, actor: $this->admin);
        $this->postJson($this->monthlyUrl($report, 'prepare', role: 'admin'), $payload)->assertForbidden();
        foreach (['open', 'download', 'confirm'] as $action) {
            $this->postJson($this->monthlyUrl($report, $action, $share, 'admin'), ['sent_confirmed' => '1'])->assertForbidden();
        }
    }

    public function test_superadmin_can_help_send_with_their_own_operator_identity(): void
    {
        $report = $this->readyMonth();
        $payload = $this->monthlyPayload($report, actor: $this->superadmin);
        $this->actingAs($this->superadmin)->post($this->monthlyUrl($report, 'prepare', role: 'superadmin'), $payload)->assertRedirect();
        $share = MonthlyReportWhatsappShare::firstOrFail();
        $this->assertSame($this->superadmin->id, $share->actor_user_id);
        $this->assertSame($this->superadmin->full_name, $share->contact_snapshot['operator_name']);
        $this->actingAs($this->teacher->user)->postJson($this->monthlyUrl($report, 'open', $share))->assertForbidden();
    }

    public function test_other_teacher_student_and_unassigned_admin_cannot_access_the_report(): void
    {
        $report = $this->readyMonth();
        $teacherUser = $this->user('teacher', 'Teacher Lain');
        Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $teacherUser->id, 'whatsapp_number' => '081234567877', 'is_active' => true]);
        foreach ([[$teacherUser, 'teacher'], [$this->parent, 'teacher'], [$this->user('admin', 'Admin Lain'), 'admin']] as [$actor, $role]) {
            $this->actingAs($actor)->get($this->monthlyUrl($report, role: $role))->assertForbidden();
            $this->get(route($role.'.monthly-reports.pdf.preview', $report))->assertForbidden();
        }
    }

    public function test_a_share_from_another_month_cannot_be_replayed_on_this_report(): void
    {
        $july = $this->readyMonth();
        $august = $this->readyMonth('2026-08-01');
        $share = $this->startMonth($july, 'link');
        $this->get($this->monthlyUrl($august, 'show', $share))->assertNotFound();
        $this->postJson($this->monthlyUrl($august, 'open', $share))->assertForbidden();
    }

    public function test_expired_outbound_actions_are_blocked_but_completed_sends_can_be_confirmed(): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report);
        $this->post($this->monthlyUrl($report, 'download', $share))->assertOk();
        $this->post($this->monthlyUrl($report, 'open', $share))->assertRedirect();
        Carbon::setTestNow(now()->addMinutes(31));
        $this->postJson($this->monthlyUrl($report, 'open', $share))->assertUnprocessable();
        $this->postJson($this->monthlyUrl($report, 'download', $share))->assertUnprocessable();
        $this->post($this->monthlyUrl($report, 'confirm', $share), ['sent_confirmed' => '1'])->assertRedirect();
    }

    public static function monthlyRoles(): array
    {
        return [['teacher'], ['admin'], ['superadmin']];
    }

    #[DataProvider('monthlyRoles')]
    public function test_preview_choices_are_remembered_per_account_and_month(string $role): void
    {
        $report = $this->readyMonth();
        $actor = $role === 'teacher' ? $this->teacher->user : $this->{$role};
        $options = ['method' => 'pdf', 'salutation' => 'wali'];
        $this->actingAs($actor)->post($this->monthlyUrl($report, 'preview', role: $role), $options)->assertRedirect();
        $this->get(route($role.'.monthly-reports.show', $report))->assertOk();
        $this->get($this->monthlyUrl($report, role: $role))->assertOk()->assertViewHas('options', $options);
        $otherMonth = $this->student->monthlyReports()->whereDate('report_month', '2026-08-01')->first();
        $this->get($this->monthlyUrl($otherMonth, role: $role))->assertOk()->assertViewHas('options', ['method' => 'link', 'salutation' => 'neutral']);
        $otherActor = $role === 'superadmin' ? $this->teacher->user : $this->superadmin;
        $otherRole = $role === 'superadmin' ? 'teacher' : 'superadmin';
        $this->actingAs($otherActor)->get($this->monthlyUrl($report, role: $otherRole))->assertOk()
            ->assertViewHas('options', ['method' => 'link', 'salutation' => 'neutral']);
        $this->assertDatabaseCount('monthly_report_whatsapp_shares', 0);
        $this->assertDatabaseCount('monthly_report_files', 0);
    }

    public function test_invalid_preview_does_not_replace_saved_choices(): void
    {
        $report = $this->readyMonth();
        $options = ['method' => 'pdf', 'salutation' => 'ibu'];
        $this->actingAs($this->teacher->user)->post($this->monthlyUrl($report, 'preview'), $options)->assertRedirect();
        $this->postJson($this->monthlyUrl($report, 'preview'), ['method' => 'invalid', 'salutation' => 'invalid'])->assertUnprocessable();
        $this->get($this->monthlyUrl($report))->assertOk()->assertViewHas('options', $options);
    }

    public function test_greeting_boundary_requires_a_fresh_preview_and_preserves_prepared_history(): void
    {
        $report = $this->readyMonth();
        Carbon::setTestNow(Carbon::parse('2026-10-12 10:59:59', 'Asia/Makassar'));
        $payload = $this->monthlyPayload($report);
        Carbon::setTestNow(Carbon::parse('2026-10-12 11:00:00', 'Asia/Makassar'));
        $this->actingAs($this->teacher->user)->postJson($this->monthlyUrl($report, 'prepare'), $payload)->assertUnprocessable();
        $share = $this->startMonth($report, 'link');
        $this->assertStringStartsWith('Selamat siang Bapak/Ibu atau Wali,', $share->message);
        Carbon::setTestNow(now()->addHours(6));
        $this->get($this->monthlyUrl($report, 'show', $share))->assertOk()->assertViewHas('message', $share->message);
    }

    public function test_corrupt_pdf_is_not_downloaded_and_a_new_preparation_creates_a_valid_version(): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report);
        Storage::disk('local')->put($share->pdf->file->storage_key, 'CORRUPT PDF');
        $this->postJson($this->monthlyUrl($report, 'download', $share))->assertUnprocessable();
        $this->postJson($this->monthlyUrl($report, 'open', $share))->assertUnprocessable();
        $new = $this->startMonth($report);
        $this->assertSame(2, $new->pdf->version_number);
        $this->assertNotNull(app(MonthlyReportPdfService::class)->verifiedBytes($new->pdf));
        $this->assertDatabaseCount('monthly_report_files', 2);
    }

    private function reorderedObjects(array $value): array
    {
        $isList = array_is_list($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) { $value[$key] = $this->reorderedObjects($item); }
        }
        if (! $isList) {
            uksort($value, fn ($a, $b) => strlen((string) $a) <=> strlen((string) $b) ?: strcmp((string) $a, (string) $b));
        }

        return $value;
    }

    public function test_pdf_download_accepts_database_json_object_reordering_without_changing_the_file(): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report);
        $file = $share->pdf;
        $originalBytes = Storage::disk('local')->get($file->file->storage_key);
        $reordered = $this->reorderedObjects($file->public_snapshot);
        $this->assertNotSame(array_keys($file->public_snapshot), array_keys($reordered));
        DB::table('monthly_report_files')->where('id', $file->id)->update(['public_snapshot' => json_encode($reordered, JSON_THROW_ON_ERROR)]);
        $this->assertSame($originalBytes, app(MonthlyReportPdfService::class)->verifiedBytes($file->fresh()));
        $download = $this->post($this->monthlyUrl($report, 'download', $share))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($originalBytes, $download->getContent());
        $this->assertNotNull($share->fresh()->downloaded_at);
        $this->post($this->monthlyUrl($report, 'open', $share))->assertRedirect();
        $this->post($this->monthlyUrl($report, 'confirm', $share), ['sent_confirmed' => '1'])->assertRedirect();
        $this->assertSame('confirmed', $share->fresh()->status);
        $this->assertDatabaseCount('monthly_report_files', 1);
    }

    public function test_snapshot_hash_ignores_object_order_but_preserves_values_types_and_list_order(): void
    {
        $service = app(MonthlyReportPdfService::class);
        $snapshot = ['teacher' => 'Teacher A', 'videos' => [['url' => 'first', 'title' => 'Satu'], ['url' => 'second', 'title' => 'Dua']],
            'attendance' => ['present' => 2, 'total' => 8], 'skills' => ['speaking' => ['score' => 'B', 'description' => 'Latihan']]];
        $this->assertSame($service->hash($snapshot), $service->hash($this->reorderedObjects($snapshot)));
        $changed = $snapshot; $changed['videos'] = array_reverse($changed['videos']);
        $this->assertNotSame($service->hash($snapshot), $service->hash($changed));
        $changed = $snapshot; $changed['skills']['speaking']['description'] = 'Diubah';
        $this->assertNotSame($service->hash($snapshot), $service->hash($changed));
        $changed = $snapshot; $changed['attendance']['present'] = '2';
        $this->assertNotSame($service->hash($snapshot), $service->hash($changed));
    }

    public function test_reordered_snapshot_with_changed_content_is_still_rejected(): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report);
        $snapshot = $this->reorderedObjects($share->pdf->public_snapshot);
        $snapshot['skills']['speaking']['description'] = 'Deskripsi diganti tanpa persetujuan';
        DB::table('monthly_report_files')->where('id', $share->file_id)->update(['public_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        $this->assertNull(app(MonthlyReportPdfService::class)->verifiedBytes($share->pdf->fresh()));
        $this->postJson($this->monthlyUrl($report, 'download', $share))->assertUnprocessable();
        $this->postJson($this->monthlyUrl($report, 'open', $share))->assertUnprocessable();
        $this->assertNull($share->fresh()->downloaded_at);
        $this->assertNull($share->fresh()->opened_at);
    }

    public function test_legacy_pdf_with_unchanged_json_remains_downloadable_and_reusable(): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report);
        $file = $share->pdf;
        $legacyHash = hash('sha256', json_encode($file->public_snapshot, JSON_THROW_ON_ERROR));
        DB::table('monthly_report_files')->where('id', $file->id)->update(['source_snapshot_hash' => $legacyHash]);
        $this->post($this->monthlyUrl($report, 'download', $share))->assertOk();
        $new = $this->startMonth($report);
        $this->assertSame($file->id, $new->file_id);
        $this->post($this->monthlyUrl($report, 'download', $new))->assertOk();
        $this->assertDatabaseCount('monthly_report_files', 1);
    }

    public function test_legacy_pdf_invalidated_by_json_reordering_is_replaced_only_on_new_preparation(): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report);
        $file = $share->pdf;
        $original = $file->public_snapshot;
        $legacyHash = hash('sha256', json_encode($original, JSON_THROW_ON_ERROR));
        $stored = json_encode($this->reorderedObjects($original), JSON_THROW_ON_ERROR);
        DB::table('monthly_report_files')->where('id', $file->id)->update(['source_snapshot_hash' => $legacyHash, 'public_snapshot' => $stored]);
        $this->postJson($this->monthlyUrl($report, 'download', $share))->assertUnprocessable();
        $this->assertNull($share->fresh()->downloaded_at);
        $new = $this->startMonth($report);
        $this->assertNotSame($file->id, $new->file_id);
        $this->assertSame(2, $new->pdf->version_number);
        DB::table('monthly_report_files')->where('id', $new->file_id)->update(['public_snapshot' => json_encode($this->reorderedObjects($new->pdf->public_snapshot), JSON_THROW_ON_ERROR)]);
        $this->post($this->monthlyUrl($report, 'download', $new))->assertOk();
        $this->post($this->monthlyUrl($report, 'open', $new))->assertRedirect();
        $this->post($this->monthlyUrl($report, 'confirm', $new), ['sent_confirmed' => '1'])->assertRedirect();
        $this->assertSame('confirmed', $new->fresh()->status);
        $this->assertSame($legacyHash, $file->fresh()->source_snapshot_hash);
        $this->assertSame($stored, $file->fresh()->getRawOriginal('public_snapshot'));
        $this->assertDatabaseCount('monthly_report_files', 2);
        $this->startMonth($report);
        $this->assertDatabaseCount('monthly_report_files', 2);
    }

    public function test_failed_share_creation_rolls_back_pdf_records_and_removes_new_file_bytes(): void
    {
        $report = $this->readyMonth();
        $payload = $this->monthlyPayload($report, 'pdf');
        MonthlyReportWhatsappShare::creating(fn () => throw new \RuntimeException('Simulated share failure'));
        try {
            app(MonthlyReportWhatsappService::class)->prepare($this->teacher->user, $report, $payload);
            $this->fail('Expected simulated failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated share failure', $error->getMessage());
        } finally {
            MonthlyReportWhatsappShare::flushEventListeners();
        }
        $this->assertDatabaseCount('monthly_report_files', 0);
        $this->assertDatabaseCount('monthly_report_whatsapp_shares', 0);
        $this->assertSame([], Storage::disk('local')->files('monthly-report-pdfs'));
    }

    public function test_pdf_preview_is_private_read_only_and_contains_valid_pdf_bytes(): void
    {
        $report = $this->readyMonth();
        $response = $this->actingAs($this->teacher->user)->get(route('teacher.monthly-reports.pdf.preview', $report))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertDatabaseCount('monthly_report_files', 0);
        $this->assertDatabaseCount('monthly_report_whatsapp_shares', 0);
    }

    public function test_absence_visibility_changes_block_old_pdf_and_new_pdf_omits_attendance(): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report);
        $this->assertIsArray($share->pdf->public_snapshot['attendance']);
        $service = app(PortalPaymentService::class);
        $service->saveVisibility($this->superadmin, $this->branch, ['visible' => '0',
            'expected_snapshot' => $service->settingSignature($this->branch)]);
        $this->postJson($this->monthlyUrl($report, 'download', $share))->assertUnprocessable();
        $new = $this->startMonth($report);
        $this->assertNull($new->pdf->public_snapshot['attendance']);
        $this->assertSame(2, $new->pdf->version_number);
        $this->assertIsArray($share->pdf->fresh()->public_snapshot['attendance']);
    }

    public function test_secondary_guardian_is_not_selected_as_the_recipient(): void
    {
        $report = $this->readyMonth();
        Guardian::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id,
            'full_name' => 'Wali Kedua', 'whatsapp_number' => '081234567888', 'is_primary' => false]);
        $share = $this->startMonth($report, 'link');
        $this->assertSame('Wali Bulanan', $share->contact_snapshot['recipient_name']);
        $this->assertSame('6281234567899', $share->contact_snapshot['recipient_phone']);
    }

    public function test_csrf_is_required_for_all_monthly_delivery_mutations(): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report);
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
                protected function runningUnitTests() { return false; }
            };
        });
        $this->withSession(['_token' => str_repeat('x', 40)]);
        $this->post($this->monthlyUrl($report, 'preview'), ['method' => 'pdf', 'salutation' => 'wali'])->assertStatus(419);
        $this->post($this->monthlyUrl($report, 'prepare'), $this->monthlyPayload($report))->assertStatus(419);
        foreach (['open', 'download', 'confirm'] as $action) {
            $this->post($this->monthlyUrl($report, $action, $share), ['sent_confirmed' => '1'])->assertStatus(419);
        }
        $this->assertNull($share->fresh()->opened_at);
        $this->assertNull($share->fresh()->downloaded_at);
        $this->assertNull($share->fresh()->confirmed_at);
    }

    public function test_client_cannot_override_the_destination_message_or_month(): void
    {
        $report = $this->readyMonth();
        $payload = $this->monthlyPayload($report) + ['recipient_phone' => '6289999999999', 'message' => 'Injected message',
            'portal_url' => 'https://example.test/fake', 'report_month' => '2026-10-01', 'student_id' => $this->otherStudent->id];
        $this->actingAs($this->teacher->user)->post($this->monthlyUrl($report, 'prepare'), $payload)->assertRedirect();
        $share = MonthlyReportWhatsappShare::firstOrFail();
        $this->assertSame('6281234567899', $share->contact_snapshot['recipient_phone']);
        $this->assertStringContainsString('Juli 2026', $share->message);
        $this->assertStringNotContainsString('Injected message', $share->message);
        $this->assertSame(route('student.reports.monthly', $report), $share->portal_url);
    }

    public static function changedSources(): array
    {
        return [['payment', 422], ['report', 422], ['teacher', 403], ['portal', 422], ['template', 422]];
    }

    public function test_same_month_charge_with_a_legacy_day_is_included_in_source_checks(): void
    {
        $report = $this->readyMonth();
        $charge = $this->student->monthlyCharges()->firstOrFail();
        $charge->update(['charge_month' => '2026-07-10']);
        $share = $this->startMonth($report, 'link');
        $charge->update(['amount_idr' => 123456]);
        $this->postJson($this->monthlyUrl($report, 'open', $share))->assertUnprocessable();
        $this->assertNull($share->fresh()->opened_at);
    }

    #[DataProvider('changedSources')]
    public function test_prepared_messages_cannot_operate_after_protected_sources_change(string $source, int $status): void
    {
        $report = $this->readyMonth();
        $share = $this->startMonth($report, 'link');
        if ($source === 'payment') $this->student->monthlyCharges()->first()->paymentProofs()->update(['status' => 'rejected']);
        if ($source === 'report') $report->update(['status' => 'revision']);
        if ($source === 'teacher') $this->teacher->update(['is_active' => false]);
        if ($source === 'portal') $this->parent->update(['is_active' => false]);
        if ($source === 'template') {
            $service = app(ReportTemplateService::class);
            $service->save($this->superadmin, $service->settings()['fields'] + ['expected_snapshot' => $service->signature()]);
        }
        $this->postJson($this->monthlyUrl($report, 'open', $share))->assertStatus($status);
        $this->assertNull($share->fresh()->opened_at);
    }
}
