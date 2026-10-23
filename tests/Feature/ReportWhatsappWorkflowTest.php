<?php

namespace Tests\Feature;

use App\Models\{Guardian, MonthlyReport, PaymentProof, QuarterlyReport, QuarterlyReportFile, ReportCycle, ReportWhatsappShare};
use App\Services\{ReportWhatsappService, StudentPortalService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Hash, Storage};
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WhatsappReportFixtures;
use Tests\TestCase;

class ReportWhatsappWorkflowTest extends TestCase
{
    use RefreshDatabase, WhatsappReportFixtures;

    private function ready(): array
    {
        [$report, $file] = $this->publish();
        $guardian = Guardian::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id,
            'full_name' => 'Penerima Uji', 'relationship_name' => 'Orang tua', 'whatsapp_number' => '081234567899', 'is_primary' => true]);
        return [$report, $file, $guardian];
    }

    private function payload($report, string $method = 'link', string $salutation = 'neutral'): array
    {
        $service = app(ReportWhatsappService::class);
        $context = $service->context($this->teacher->user, $report);
        $options = compact('method', 'salutation');
        $key = (string) Str::uuid();
        return $options + ['request_key' => $key,
            'expected_snapshot' => $service->previewSignature($context, $options, $key, $service->message($context, $method, $salutation))];
    }

    private function url($report, string $action = 'index', $share = null, string $role = 'teacher'): string
    {
        return route($role.'.quarterly-reports.whatsapp.'.$action, array_filter([$report, $share]));
    }

    private function start($report, string $method = 'link'): ReportWhatsappShare
    {
        $this->actingAs($this->teacher->user)->post($this->url($report, 'prepare'), $this->payload($report, $method))->assertRedirect();
        return ReportWhatsappShare::latest('prepared_at')->firstOrFail();
    }

    #[DataProvider('previewRoles')]
    public function test_preview_choices_survive_leaving_and_reopening_the_report(string $role): void
    {
        [$report] = $this->ready();
        $actor = $role === 'teacher' ? $this->teacher->user : $this->{$role};
        $before = $report->getAttributes();
        $options = ['method' => 'pdf', 'salutation' => 'ibu'];
        $this->actingAs($actor)->get($this->url($report, role: $role))->assertOk()
            ->assertViewHas('options', ['method' => 'link', 'salutation' => 'neutral'])
            ->assertSee($this->url($report, 'preview', role: $role));
        $this->post($this->url($report, 'preview', role: $role), $options)
            ->assertRedirect($this->url($report, role: $role));
        $this->get(route($role.'.quarterly-reports.show', $report))->assertOk();
        $this->get($this->url($report, role: $role))->assertOk()->assertViewHas('options', $options)
            ->assertViewHas('message', fn ($message) => str_contains($message, 'dukungan Ibu')
                && str_contains($message, 'Saya sertakan file PDF')
                && ! str_contains($message, '/student/reports/quarterly/'))
            ->assertSee('name="method" value="pdf"', false)
            ->assertSee('name="salutation" value="ibu"', false);
        $this->assertSame($before, $report->fresh()->getAttributes());
        $this->assertDatabaseCount('report_whatsapp_shares', 0);
    }

    public static function previewRoles(): array
    {
        return [['admin'], ['teacher'], ['superadmin']];
    }

    #[DataProvider('previewRoles')]
    public function test_general_guardian_salutation_is_available_remembered_and_used_in_prepared_messages(string $role): void
    {
        [$report] = $this->ready();
        $actor = $role === 'teacher' ? $this->teacher->user : $this->{$role};
        $options = ['method' => 'pdf', 'salutation' => 'wali'];
        $this->actingAs($actor)->get($this->url($report, role: $role))->assertOk()
            ->assertSee('value="wali"', false)->assertSee('Bapak/Ibu atau Wali');
        $this->post($this->url($report, 'preview', role: $role), $options)->assertRedirect();
        $page = $this->get($this->url($report, role: $role))->assertOk()->assertViewHas('options', $options)
            ->assertViewHas('message', fn ($message) => str_starts_with($message, 'Selamat pagi Bapak/Ibu atau Wali,')
                && str_contains($message, 'dukungan Bapak/Ibu atau Wali selama ini')
                && str_contains($message, 'bantuan Bapak/Ibu atau Wali untuk terus mengingatkan'));
        $input = $options + ['request_key' => $page->viewData('requestKey'), 'expected_snapshot' => $page->viewData('expectedSnapshot')];
        if ($role === 'admin') {
            $this->postJson($this->url($report, 'prepare', role: $role), $input)->assertForbidden();
            $this->assertDatabaseCount('report_whatsapp_shares', 0);
        } else {
            $this->post($this->url($report, 'prepare', role: $role), $input)->assertRedirect();
            $share = ReportWhatsappShare::firstOrFail();
            $this->assertSame('wali', $share->salutation);
            $this->assertSame($page->viewData('message'), $share->message);
            $this->get($this->url($report, 'show', $share, $role))->assertOk()->assertViewHas('options', $options);
        }
        $this->get($this->url($report, role: $role))->assertOk()->assertViewHas('options', $options);
    }

    public function test_automatic_greeting_changes_at_local_time_boundaries_for_link_and_pdf(): void
    {
        [$report] = $this->ready();
        $service = app(ReportWhatsappService::class);
        $context = $service->context($this->teacher->user, $report);
        foreach (['00:00:00' => 'Selamat pagi', '10:59:59' => 'Selamat pagi',
            '11:00:00' => 'Selamat siang', '14:59:59' => 'Selamat siang',
            '15:00:00' => 'Selamat sore', '17:59:59' => 'Selamat sore',
            '18:00:00' => 'Selamat malam', '23:59:59' => 'Selamat malam'] as $time => $greeting) {
            Carbon::setTestNow(Carbon::parse('2026-10-12 '.$time, 'Asia/Makassar'));
            foreach (['link', 'pdf'] as $method) {
                $message = $service->message($context, $method, 'wali');
                $this->assertStringStartsWith($greeting.' Bapak/Ibu atau Wali,', $message);
                $this->assertSame($method === 'link', str_contains($message, '/student/reports/quarterly/'));
            }
        }
    }

    public function test_automatic_greeting_uses_branch_timezone_and_falls_back_to_app_timezone(): void
    {
        [$report] = $this->ready();
        $service = app(ReportWhatsappService::class);
        $context = $service->context($this->teacher->user, $report);
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-10-12 07:30:00', 'UTC'));
        foreach (['Asia/Jakarta' => 'Selamat siang', 'Asia/Makassar' => 'Selamat sore',
            'Asia/Jayapura' => 'Selamat sore', 'zona-tidak-valid' => 'Selamat pagi'] as $zone => $greeting) {
            $context['report']->branch->timezone_name = $zone;
            $this->assertStringStartsWith($greeting.' Bapak/Ibu atau Wali,', $service->message($context, 'link', 'wali'));
        }
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:30:00', 'UTC'));
        $context['report']->branch->timezone_name = 'Asia/Jayapura';
        $this->assertStringStartsWith('Selamat malam Bapak/Ibu atau Wali,', $service->message($context, 'pdf', 'wali'));
    }

    public function test_crossing_greeting_boundary_requires_new_preview_and_prepared_history_keeps_its_greeting(): void
    {
        [$report] = $this->ready();
        Carbon::setTestNow(Carbon::parse('2026-10-12 10:59:59', 'Asia/Makassar'));
        $input = $this->payload($report, 'pdf', 'wali');
        Carbon::setTestNow(Carbon::parse('2026-10-12 11:00:00', 'Asia/Makassar'));
        $this->actingAs($this->teacher->user)->postJson($this->url($report, 'prepare'), $input)->assertUnprocessable();
        $this->assertDatabaseCount('report_whatsapp_shares', 0);
        $this->post($this->url($report, 'prepare'), $this->payload($report, 'pdf', 'wali'))->assertRedirect();
        $share = ReportWhatsappShare::firstOrFail();
        $message = $share->message;
        $this->assertStringStartsWith('Selamat siang Bapak/Ibu atau Wali,', $message);
        Carbon::setTestNow(Carbon::parse('2026-10-12 15:00:00', 'Asia/Makassar'));
        $this->get($this->url($report, 'show', $share))->assertOk()->assertViewHas('message', $message);
        $this->get($this->url($report))->assertOk()->assertViewHas('message',
            fn ($fresh) => str_starts_with($fresh, 'Selamat sore Bapak/Ibu atau Wali,'));
        $this->assertSame($message, $share->fresh()->message);
    }

    public function test_preview_choices_are_separate_for_admin_teacher_and_superadmin_accounts(): void
    {
        [$report] = $this->ready();
        $options = ['method' => 'pdf', 'salutation' => 'ibu'];
        $this->actingAs($this->admin)->post($this->url($report, 'preview', role: 'admin'), $options)->assertRedirect();
        $this->actingAs($this->superadmin)->get($this->url($report, role: 'superadmin'))->assertOk()
            ->assertViewHas('options', ['method' => 'link', 'salutation' => 'neutral']);
        $this->post($this->url($report, 'preview', role: 'superadmin'), ['method' => 'link', 'salutation' => 'name'])->assertRedirect();
        $this->actingAs($this->teacher->user)->get($this->url($report))->assertOk()
            ->assertViewHas('options', ['method' => 'link', 'salutation' => 'neutral']);
        $this->post($this->url($report, 'preview'), ['method' => 'link', 'salutation' => 'bapak'])->assertRedirect();
        $this->actingAs($this->admin)->get($this->url($report, role: 'admin'))->assertOk()->assertViewHas('options', $options);
        $this->actingAs($this->teacher->user)->get($this->url($report))->assertOk()
            ->assertViewHas('options', ['method' => 'link', 'salutation' => 'bapak']);
        $this->actingAs($this->superadmin)->get($this->url($report, role: 'superadmin'))->assertOk()
            ->assertViewHas('options', ['method' => 'link', 'salutation' => 'name']);
    }

    public function test_preview_choices_are_separate_for_each_report_and_cycle(): void
    {
        [$report] = $this->ready();
        $cycle = ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->student->id,
            'cycle_number' => 2, 'start_month' => '2026-10-01', 'end_month' => '2026-12-01']);
        $second = QuarterlyReport::forceCreate(['id' => (string) Str::uuid(), 'cycle_id' => $cycle->id,
            'student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id,
            'status' => 'draft', 'skills' => [], 'parent_note' => '', 'revision_number' => 1]);
        $options = ['method' => 'pdf', 'salutation' => 'ibu'];
        $this->actingAs($this->admin)->post($this->url($report, 'preview', role: 'admin'), $options)->assertRedirect();
        $this->get($this->url($second, role: 'admin'))->assertOk()
            ->assertViewHas('options', ['method' => 'link', 'salutation' => 'neutral']);
        $this->post($this->url($second, 'preview', role: 'admin'), ['method' => 'link', 'salutation' => 'name'])->assertRedirect();
        $this->get($this->url($report, role: 'admin'))->assertOk()->assertViewHas('options', $options);
        $this->assertDatabaseCount('report_whatsapp_shares', 0);
    }

    public function test_query_options_are_temporary_and_do_not_overwrite_saved_preview_choices(): void
    {
        [$report] = $this->ready();
        $options = ['method' => 'pdf', 'salutation' => 'ibu'];
        $this->actingAs($this->admin)->post($this->url($report, 'preview', role: 'admin'), $options)->assertRedirect();
        $this->get($this->url($report, role: 'admin').'?salutation=bapak')->assertOk()
            ->assertViewHas('options', ['method' => 'pdf', 'salutation' => 'bapak']);
        $this->getJson($this->url($report, role: 'admin').'?method=auto')->assertUnprocessable();
        $this->get($this->url($report, role: 'admin'))->assertOk()->assertViewHas('options', $options);
    }

    public function test_invalid_preview_input_preserves_previous_choices_and_does_not_create_share_history(): void
    {
        [$report] = $this->ready();
        $options = ['method' => 'pdf', 'salutation' => 'ibu'];
        $this->actingAs($this->admin)->post($this->url($report, 'preview', role: 'admin'), $options)->assertRedirect();
        foreach ([['method' => 'auto', 'salutation' => 'bapak'], ['method' => 'link', 'salutation' => ['ibu']],
            ['method' => 'pdf'], ['salutation' => 'name'], ['method' => '', 'salutation' => 'neutral']] as $input) {
            $this->postJson($this->url($report, 'preview', role: 'admin'), $input)->assertUnprocessable();
            $this->get($this->url($report, role: 'admin'))->assertOk()->assertViewHas('options', $options);
        }
        $this->assertDatabaseCount('report_whatsapp_shares', 0);
    }

    public function test_preview_requires_report_access_and_cannot_change_another_accounts_choices(): void
    {
        [$report] = $this->ready();
        $options = ['method' => 'pdf', 'salutation' => 'ibu'];
        $this->actingAs($this->admin)->post($this->url($report, 'preview', role: 'admin'), $options)->assertRedirect();
        $denied = [[$this->user('admin', 'Admin di luar cabang'), 'admin'],
            [$this->user('teacher', 'Teacher lain'), 'teacher'], [$this->parent, 'admin']];
        foreach ($denied as [$actor, $role]) {
            $this->actingAs($actor)->postJson($this->url($report, 'preview', role: $role),
                ['method' => 'link', 'salutation' => 'bapak', 'actor_user_id' => $this->admin->id])->assertForbidden();
        }
        $this->actingAs($this->admin)->get($this->url($report, role: 'admin'))->assertOk()->assertViewHas('options', $options);
    }

    public function test_preparation_uses_remembered_preview_and_history_keeps_its_original_choices(): void
    {
        [$report] = $this->ready();
        $options = ['method' => 'pdf', 'salutation' => 'ibu'];
        $this->actingAs($this->teacher->user)->post($this->url($report, 'preview'), $options)->assertRedirect();
        $page = $this->get($this->url($report))->assertOk();
        $this->post($this->url($report, 'prepare'), $options + [
            'request_key' => $page->viewData('requestKey'), 'expected_snapshot' => $page->viewData('expectedSnapshot'),
        ])->assertRedirect();
        $share = ReportWhatsappShare::firstOrFail();
        $this->assertSame('pdf', $share->method);
        $this->assertSame('ibu', $share->salutation);
        $this->assertSame($page->viewData('message'), $share->message);
        $before = $share->getAttributes();
        $new = ['method' => 'link', 'salutation' => 'bapak'];
        $this->post($this->url($report, 'preview'), $new)->assertRedirect();
        $this->get($this->url($report, 'show', $share))->assertOk()->assertViewHas('options', $options);
        $this->get($this->url($report))->assertOk()->assertViewHas('options', $new);
        $this->assertSame($before, $share->fresh()->getAttributes());
        $this->assertDatabaseCount('report_whatsapp_shares', 1);
    }

    public function test_logout_clears_preview_choices(): void
    {
        [$report] = $this->ready();
        $this->actingAs($this->admin)->post($this->url($report, 'preview', role: 'admin'),
            ['method' => 'pdf', 'salutation' => 'ibu'])->assertRedirect();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->actingAs($this->admin)->get($this->url($report, role: 'admin'))->assertOk()
            ->assertViewHas('options', ['method' => 'link', 'salutation' => 'neutral']);
    }

    public function test_link_journey_has_real_pdf_gate_saved_recipient_template_and_manual_confirmation(): void
    {
        [$report] = $this->ready();
        $this->actingAs($this->teacher->user)->get($this->url($report))->assertOk()->assertSee('Kirim tautan rapor')->assertSee('Kirim file PDF')
            ->assertSee('Penerima Uji')->assertSee('6281234567899')->assertDontSee('RAHASIA');
        $this->assertDatabaseCount('report_whatsapp_shares', 0);
        $share = $this->start($report);
        $this->assertSame('prepared', $share->status);
        $this->assertNull($share->opened_at);
        $this->assertStringContainsString(route('student.reports.quarterly', $report), $share->message);
        $this->postJson($this->url($report, 'confirm', $share), ['sent_confirmed' => 1])->assertUnprocessable();
        $response = $this->post($this->url($report, 'open', $share));
        $response->assertRedirect('https://wa.me/6281234567899?text='.rawurlencode($share->message));
        $this->assertSame('opened', $share->fresh()->status);
        $this->assertNull($share->fresh()->confirmed_at);
        $this->postJson($this->url($report, 'confirm', $share))->assertUnprocessable();
        $this->post($this->url($report, 'confirm', $share), ['sent_confirmed' => 1])->assertRedirect();
        $when = $share->fresh()->confirmed_at;
        $this->post($this->url($report, 'confirm', $share), ['sent_confirmed' => 1])->assertRedirect();
        $this->assertTrue($when->equalTo($share->fresh()->confirmed_at));
        $this->get($this->url($report, 'show', $share))->assertOk()->assertSee('Dikonfirmasi terkirim oleh petugas')->assertDontSee('RAHASIA');
    }

    public function test_pdf_journey_requires_download_and_returns_the_approved_pdf_bytes(): void
    {
        [$report, $file] = $this->ready();
        $share = $this->start($report, 'pdf');
        $this->assertStringNotContainsString('/student/reports/quarterly/', $share->message);
        $this->post($this->url($report, 'open', $share))->assertRedirect();
        $this->postJson($this->url($report, 'confirm', $share), ['sent_confirmed' => 1])->assertUnprocessable();
        $download = $this->post($this->url($report, 'download', $share))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(Storage::disk('local')->get($file->file->storage_key), $download->getContent());
        $this->assertStringContainsString('private', $download->headers->get('Cache-Control'));
        $this->post($this->url($report, 'confirm', $share), ['sent_confirmed' => 1])->assertRedirect();
        $this->assertSame('confirmed', $share->fresh()->status);
    }

    public function test_duplicate_submit_is_one_record_and_client_cannot_replace_recipient_message_or_actor(): void
    {
        [$report] = $this->ready();
        $data = $this->payload($report) + ['message' => 'TAMPER', 'recipient_phone' => '628000000000', 'actor_user_id' => $this->other->id];
        $this->actingAs($this->teacher->user)->post($this->url($report, 'prepare'), $data)->assertRedirect();
        $this->post($this->url($report, 'prepare'), $data)->assertRedirect();
        $this->assertDatabaseCount('report_whatsapp_shares', 1);
        $share = ReportWhatsappShare::first();
        $this->assertSame($this->teacher->user_id, $share->actor_user_id);
        $this->assertSame('6281234567899', $share->contact_snapshot['recipient_phone']);
        $this->assertStringNotContainsString('TAMPER', $share->message);
    }

    public function test_contact_change_rejects_old_preview_and_old_prepared_actions_preserving_history(): void
    {
        [$report, , $guardian] = $this->ready();
        $data = $this->payload($report);
        $guardian->update(['whatsapp_number' => '081111111111']);
        $this->actingAs($this->teacher->user)->postJson($this->url($report, 'prepare'), $data)->assertUnprocessable();
        $this->assertDatabaseCount('report_whatsapp_shares', 0);
        $share = $this->start($report, 'pdf');
        $guardian->update(['full_name' => 'Nama yang baru']);
        foreach (['open', 'download', 'confirm'] as $action) {
            $this->postJson($this->url($report, $action, $share), ['sent_confirmed' => 1])->assertUnprocessable();
        }
        $this->assertSame('Penerima Uji', $share->fresh()->contact_snapshot['recipient_name']);
        $this->assertNull($share->fresh()->opened_at);
    }

    public function test_saved_whatsapp_history_prevents_contact_move_or_delete_but_allows_corrections(): void
    {
        [$report, , $guardian] = $this->ready();
        $share = $this->start($report);
        $data = ['student_id' => $this->otherStudent->id, 'full_name' => $guardian->full_name,
            'relationship_name' => $guardian->relationship_name, 'whatsapp_number' => $guardian->whatsapp_number, 'is_primary' => true];
        $this->actingAs($this->superadmin)->putJson(route('superadmin.guardians.update', $guardian), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->deleteJson(route('superadmin.guardians.destroy', $guardian))
            ->assertUnprocessable()->assertJsonValidationErrors('guardian');
        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'student_id' => $this->student->id]);
        $data['student_id'] = $this->student->id;
        $data['whatsapp_number'] = '081111111111';
        $this->putJson(route('superadmin.guardians.update', $guardian), $data)->assertRedirect();
        $this->assertSame('081111111111', $guardian->fresh()->whatsapp_number);
        $this->assertSame('6281234567899', $share->fresh()->contact_snapshot['recipient_phone']);
        $this->actingAs($this->teacher->user)->postJson($this->url($report, 'open', $share))->assertUnprocessable();
    }

    public function test_revoked_payment_blocks_open_download_confirmation_and_existing_parent_link(): void
    {
        [$report] = $this->ready();
        $share = $this->start($report, 'pdf');
        $this->post($this->url($report, 'download', $share))->assertOk();
        $this->post($this->url($report, 'open', $share))->assertRedirect();
        PaymentProof::query()->update(['status' => 'rejected']);
        foreach (['open', 'download', 'confirm'] as $action) {
            $this->postJson($this->url($report, $action, $share), ['sent_confirmed' => 1])->assertUnprocessable();
        }
        $this->actingAs($this->parent)->get(route('student.reports.quarterly', $report))->assertOk()->assertDontSee('NOTE FINAL PUBLIK');
        $file = QuarterlyReportFile::findOrFail($share->file_id);
        $this->get(route('student.reports.pdf', [$report, $file]))->assertForbidden();
        $this->assertNull($share->fresh()->confirmed_at);
    }

    public function test_changed_monthly_approval_invalidates_final_share_even_while_status_is_approved(): void
    {
        [$report] = $this->ready();
        $month = MonthlyReport::first();
        $snapshot = $month->approved_snapshot;
        $snapshot['public_content']['parent_message'] = 'Pesan baru';
        $month->update(['approved_snapshot' => $snapshot]);
        $this->assertFalse(app(ReportWhatsappService::class)->context($this->teacher->user, $report)['ready']);
    }

    public function test_missing_contact_pdf_and_nonapproved_report_each_block_preparation(): void
    {
        [$report, $file, $guardian] = $this->ready();
        $guardian->update(['is_primary' => false]);
        $this->assertFalse(app(ReportWhatsappService::class)->context($this->teacher->user, $report)['ready']);
        $guardian->update(['is_primary' => true]);
        Storage::disk('local')->put($file->file->storage_key, '%PDF-corrupt');
        $this->assertFalse(app(ReportWhatsappService::class)->context($this->teacher->user, $report)['ready']);
        $report->update(['status' => 'draft']);
        $this->actingAs($this->teacher->user)->postJson($this->url($report, 'prepare'), $this->payload($report))->assertUnprocessable();
        $this->assertDatabaseCount('report_whatsapp_shares', 0);
    }

    public function test_admin_can_monitor_but_cannot_prepare_open_download_or_confirm(): void
    {
        [$report] = $this->ready();
        $share = $this->start($report, 'pdf');
        $this->actingAs($this->admin)->get($this->url($report, role: 'admin'))->assertOk()->assertSee('Admin Cabang dapat memeriksa riwayat');
        $this->postJson($this->url($report, 'prepare', role: 'admin'), $this->payload($report))->assertForbidden();
        foreach (['open', 'download', 'confirm'] as $action) {
            $this->postJson($this->url($report, $action, $share, 'admin'), ['sent_confirmed' => 1])->assertForbidden();
        }
    }

    public function test_superadmin_assistance_records_operator_separately_from_teacher(): void
    {
        [$report] = $this->ready();
        $service = app(ReportWhatsappService::class);
        $context = $service->context($this->superadmin, $report);
        $options = ['method' => 'link', 'salutation' => 'ibu'];
        $key = (string) Str::uuid();
        $input = $options + ['request_key' => $key, 'expected_snapshot' => $service->previewSignature($context, $options, $key, $service->message($context, 'link', 'ibu'))];
        $this->actingAs($this->superadmin)->post($this->url($report, 'prepare', role: 'superadmin'), $input)->assertRedirect();
        $share = ReportWhatsappShare::first();
        $this->assertSame($this->superadmin->id, $share->actor_user_id);
        $this->assertSame($this->teacher->id, $share->teacher_id);
        $this->assertStringContainsString('Selamat pagi Ibu', $share->message);
    }

    public function test_other_teacher_parent_and_unassigned_admin_cannot_access_staff_share(): void
    {
        [$report] = $this->ready();
        $share = $this->start($report);
        foreach ([[$this->user('teacher', 'Teacher lain'), 'teacher'], [$this->user('admin', 'Admin lain'), 'admin'], [$this->parent, 'teacher']] as [$user, $role]) {
            $this->actingAs($user)->get($this->url($report, 'index', role: $role))->assertForbidden();
            $this->get($this->url($report, 'show', $share, $role))->assertForbidden();
        }
        $this->actingAs($this->other)->get(route('student.reports.quarterly', $report))->assertNotFound();
    }

    public function test_expired_preparation_and_inactive_teacher_cannot_open_whatsapp(): void
    {
        [$report] = $this->ready();
        $share = $this->start($report);
        $this->travel(31)->minutes();
        $this->postJson($this->url($report, 'open', $share))->assertUnprocessable();
        $this->travelBack();
        $this->teacher->update(['is_active' => false]);
        $this->postJson($this->url($report, 'open', $share))->assertForbidden();
    }

    #[DataProvider('phones')]
    public function test_phone_normalization_keeps_international_numbers_and_rejects_multiple_targets(string $input, ?string $expected): void
    {
        $this->assertSame($expected, app(ReportWhatsappService::class)->phone($input));
    }

    public static function phones(): array
    {
        return [['0812 3456 7890', '6281234567890'], ['+62 (812) 3456-7890', '6281234567890'], ['006281234567890', '6281234567890'],
            ['+447700900123', '447700900123'], ['08123456789,08111111111', null], ['https://wa.me/6281234567890', null],
            ['08123456789?text=x', null], ['abc', null], ['', null], ['00000000', null]];
    }

    public function test_message_escaping_salutation_and_options_cannot_inject_html_or_send_hidden_notes(): void
    {
        [$report, , $guardian] = $this->ready();
        $guardian->update(['full_name' => '<script>alert(1)</script>']);
        $this->actingAs($this->teacher->user)->get($this->url($report).'?salutation=name')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('RAHASIA');
        $this->getJson($this->url($report).'?method=auto')->assertUnprocessable();
        $this->getJson($this->url($report).'?salutation[]=ibu')->assertUnprocessable();
    }

    public function test_csrf_is_required_on_preview_prepare_open_and_manual_confirmation(): void
    {
        [$report] = $this->ready();
        $share = $this->start($report);
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
                protected function runningUnitTests() { return false; }
            };
        });
        $token = str_repeat('x', 40);
        $this->withSession(['_token' => $token]);
        $this->post($this->url($report, 'preview'), ['method' => 'pdf', 'salutation' => 'ibu'])->assertStatus(419);
        $this->post($this->url($report, 'prepare'), $this->payload($report))->assertStatus(419);
        $this->post($this->url($report, 'open', $share))->assertStatus(419);
        $this->post($this->url($report, 'confirm', $share), ['sent_confirmed' => 1])->assertStatus(419);
        $this->assertNull($share->fresh()->opened_at);
        $this->post($this->url($report, 'preview'), ['_token' => $token, 'method' => 'pdf', 'salutation' => 'ibu'])
            ->assertRedirect($this->url($report));
        $this->post($this->url($report, 'open', $share), ['_token' => $token])->assertRedirect();
        $this->post($this->url($report, 'confirm', $share), ['_token' => $token, 'sent_confirmed' => 1])->assertRedirect();
        $this->assertNotNull($share->fresh()->confirmed_at);
    }

    public function test_changed_method_or_salutation_cannot_reuse_another_preview_signature(): void
    {
        [$report] = $this->ready();
        $data = $this->payload($report);
        $this->actingAs($this->teacher->user)->postJson($this->url($report, 'prepare'), array_replace($data, ['method' => 'pdf']))->assertUnprocessable();
        $this->postJson($this->url($report, 'prepare'), array_replace($data, ['salutation' => 'ibu']))->assertUnprocessable();
        $this->postJson($this->url($report, 'prepare'), array_replace($data, ['request_key' => (string) Str::uuid()]))->assertUnprocessable();
        $this->assertDatabaseCount('report_whatsapp_shares', 0);
    }

    public function test_child_login_resumes_the_report_and_foreign_return_urls_are_ignored(): void
    {
        [$report] = $this->ready();
        $this->parent->update(['password_hash' => Hash::make('Testing123')]);
        $target = route('student.reports.quarterly', $report);
        $this->get($target)->assertRedirect(route('login'));
        $this->post(route('login.process'), ['username' => $this->parent->username, 'password' => 'Testing123'])->assertRedirect(parse_url($target, PHP_URL_PATH));
        $this->get($target)->assertOk();
        $this->post(route('logout'));
        $this->withSession(['url.intended' => 'https://evil.example/student/reports/quarterly/'.$report->id])
            ->post(route('login.process'), ['username' => $this->parent->username, 'password' => 'Testing123'])->assertRedirect(route('student.dashboard'));
    }
}
