<?php

namespace Tests\Feature;

use App\Models\{FileAsset, QuarterlyReport, ReportSignature, Teacher, User};
use App\Services\{FileAssetService, QuarterlyReportContentService, QuarterlyReportPdfRenderer, QuarterlyReportPdfService, QuarterlyReportWorkflowService, ReportSignatureService, ReportTemplateService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WhatsappReportFixtures;
use Tests\TestCase;

class ReportSignatureWorkflowTest extends TestCase
{
    use RefreshDatabase, WhatsappReportFixtures;

    private function signer(string $scope): User
    {
        return $scope === 'teacher' ? $this->teacher->user : $this->superadmin;
    }

    private function endpoint(string $scope, string $action = 'store'): string
    {
        return route(($scope === 'teacher' ? 'teacher.profile.signature.' : 'superadmin.report-template.signature.').$action);
    }

    private function signatureData(string $scope, ?UploadedFile $file = null): array
    {
        return ['signature_version' => app(ReportSignatureService::class)->state($this->signer($scope), $scope)['version'],
            'signature_file' => $file ?? UploadedFile::fake()->image($scope.'.png', 480, 160)];
    }

    private function uploadSignature(string $scope, ?UploadedFile $file = null): ReportSignature
    {
        $this->actingAs($this->signer($scope))->post($this->endpoint($scope), $this->signatureData($scope, $file))
            ->assertRedirect()->assertSessionHasNoErrors();

        return ReportSignature::whereNotNull('file_id')->where('owner_key', $scope === 'teacher' ? 'teacher:'.$this->teacher->id : 'manager')->latest('revision')->firstOrFail();
    }

    private function draftFinal(): QuarterlyReport
    {
        $workflow = app(QuarterlyReportWorkflowService::class);
        $content = app(QuarterlyReportContentService::class);
        $report = $workflow->prepare($this->admin, $this->cycle, ['teacher_id' => $this->teacher->id,
            'expected_snapshot' => $content->preparationSignature($this->cycle->fresh())]);

        return $workflow->save($this->teacher->user, $report, ['skills' => collect(QuarterlyReportContentService::SKILLS)
            ->map(fn ($name) => ['score' => 'B', 'description' => 'Latihan '.$name])->all(), 'parent_note' => 'Latihan di rumah.',
            'expected_snapshot' => $content->signature($report)]);
    }

    public function test_teacher_has_separate_private_upload_form_and_own_image(): void
    {
        $this->actingAs($this->teacher->user)->get(route('teacher.profile.show'))->assertOk()
            ->assertSee('Tanda tangan Teacher')->assertSee('name="signature_file"', false);
        $this->get($this->endpoint('teacher', 'image'))->assertNotFound();
        $record = $this->uploadSignature('teacher');
        $this->assertSame($this->teacher->id, $record->teacher_id);
        $this->assertSame($this->teacher->user_id, $record->uploaded_by_user_id);
        $this->assertSame(1, $record->revision);
        Storage::disk('local')->assertExists($record->file->storage_key);
        $this->assertStringStartsWith('report-signatures/', $record->file->storage_key);
        $this->get($this->endpoint('teacher', 'image'))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_manager_upload_is_in_template_page_without_nested_forms(): void
    {
        $response = $this->actingAs($this->superadmin)->get(route('superadmin.report-template.edit'))->assertOk()->assertSee('Tanda tangan Manager');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        foreach ($dom->getElementsByTagName('form') as $form) {
            $this->assertSame(0, $form->getElementsByTagName('form')->length);
        }
        $record = $this->uploadSignature('manager', UploadedFile::fake()->image('manager.jpg', 500, 150));
        $this->assertNull($record->teacher_id);
        $this->assertSame(config('report-pdf.defaults.manager_name'), $record->signer_name);
        $this->get($this->endpoint('manager', 'image'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    #[DataProvider('wrongRoles')]
    public function test_other_roles_cannot_upload_or_read_signatures(string $scope, string $role): void
    {
        $actor = match ($role) {
            'teacher' => $this->teacher->user, 'superadmin' => $this->superadmin,
            'admin' => $this->admin, 'student' => $this->parent,
        };
        $this->actingAs($actor)->post($this->endpoint($scope), ['signature_file' => UploadedFile::fake()->image('sign.png')])->assertForbidden();
        $this->get($this->endpoint($scope, 'image'))->assertForbidden();
        $this->assertSame(0, ReportSignature::whereNotNull('file_id')->count());
    }

    public static function wrongRoles(): array
    {
        return [['manager', 'teacher'], ['manager', 'admin'], ['manager', 'student'],
            ['teacher', 'superadmin'], ['teacher', 'admin'], ['teacher', 'student']];
    }

    public function test_guest_and_inactive_teacher_cannot_use_signature_routes(): void
    {
        $this->get($this->endpoint('teacher', 'image'))->assertRedirect(route('login'));
        $this->post($this->endpoint('manager'), [])->assertRedirect(route('login'));
        $this->teacher->update(['is_active' => false]);
        $this->actingAs($this->teacher->user)->post($this->endpoint('teacher'), [])->assertForbidden();
        $this->get($this->endpoint('teacher', 'image'))->assertForbidden();
    }

    public function test_another_teacher_cannot_access_or_replace_the_first_teachers_signature(): void
    {
        $record = $this->uploadSignature('teacher');
        $other = $this->user('teacher', 'Teacher Kedua');
        Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $other->id, 'whatsapp_number' => '081234567891', 'is_active' => true]);
        $this->actingAs($other)->get($this->endpoint('teacher', 'image'))->assertNotFound();
        $version = app(ReportSignatureService::class)->state($other, 'teacher')['version'];
        $this->post($this->endpoint('teacher'), ['signature_version' => $version, 'signature_file' => UploadedFile::fake()->image('sign.png'),
            'teacher_id' => $this->teacher->id])->assertSessionHasErrors('teacher_id');
        $this->assertSame(1, ReportSignature::whereNotNull('file_id')->count());
        $this->assertSame($record->file_id, $record->fresh()->file_id);
    }

    #[DataProvider('invalidImages')]
    public function test_invalid_images_are_rejected_before_any_storage_write(string $kind): void
    {
        $file = match ($kind) {
            'gif' => UploadedFile::fake()->image('sign.gif'),
            'extension' => UploadedFile::fake()->image('sign.php'),
            'large' => UploadedFile::fake()->image('sign.png')->size(513),
            'width' => UploadedFile::fake()->image('sign.png', 2001, 40),
            'height' => UploadedFile::fake()->image('sign.png', 40, 2001),
            'svg' => UploadedFile::fake()->createWithContent('sign.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'corrupt' => UploadedFile::fake()->createWithContent('sign.png', 'broken image'),
        };
        $this->actingAs($this->teacher->user)->post($this->endpoint('teacher'), $this->signatureData('teacher', $file))->assertSessionHasErrors('signature_file');
        $this->assertSame([], Storage::disk('local')->allFiles('report-signatures'));
        $this->assertSame(0, ReportSignature::whereNotNull('file_id')->count());
    }

    public static function invalidImages(): array
    {
        return array_map(fn ($kind) => [$kind], ['gif', 'extension', 'large', 'width', 'height', 'svg', 'corrupt']);
    }

    public function test_double_submit_and_stale_upload_preserve_the_first_version(): void
    {
        $data = $this->signatureData('teacher');
        $this->actingAs($this->teacher->user)->post($this->endpoint('teacher'), $data)->assertSessionHasNoErrors();
        $this->post($this->endpoint('teacher'), $data)->assertSessionHasErrors('signature_version');
        $this->assertSame(1, ReportSignature::whereNotNull('file_id')->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('report-signatures'));
    }

    public function test_multiple_files_are_rejected_as_invalid_input_without_a_server_error(): void
    {
        $data = $this->signatureData('teacher');
        $data['signature_file'] = [$data['signature_file']];
        $this->actingAs($this->teacher->user)->post($this->endpoint('teacher'), $data)->assertSessionHasErrors('signature_file');
        $this->assertSame(0, ReportSignature::whereNotNull('file_id')->count());
        $this->assertSame([], Storage::disk('local')->allFiles('report-signatures'));
    }

    public function test_replacement_retains_previous_versions_and_referenced_files(): void
    {
        $first = $this->uploadSignature('teacher');
        $second = $this->uploadSignature('teacher', UploadedFile::fake()->image('new.png', 400, 100));
        $this->assertSame(2, $second->revision);
        $this->assertNotSame($first->file_id, $second->file_id);
        Storage::disk('local')->assertExists($first->file->storage_key);
        $this->assertSame($second->id, app(ReportSignatureService::class)->state($this->teacher->user, 'teacher')['record']->id);
        try { app(FileAssetService::class)->delete($first->file); $this->fail('Referenced file must be retained.'); }
        catch (\Illuminate\Database\QueryException) { $this->assertTrue(true); }
        Storage::disk('local')->assertExists($first->file->storage_key);
    }

    #[DataProvider('signatureScopes')]
    public function test_changed_signer_name_requires_reupload_and_does_not_reuse_the_previous_signature(string $scope): void
    {
        $this->uploadSignature($scope);
        $oldVersion = $this->signatureData($scope);
        if ($scope === 'teacher') {
            $this->teacher->user->update(['full_name' => 'Teacher Nama Baru']);
        } else {
            $template = app(ReportTemplateService::class);
            $template->save($this->superadmin, ['expected_snapshot' => $template->signature(),
                ...array_replace(config('report-pdf.defaults'), ['manager_name' => 'Manager Nama Baru'])]);
        }
        $service = app(ReportSignatureService::class);
        $this->assertFalse($service->state($this->signer($scope), $scope)['name_matches']);
        $this->actingAs($this->signer($scope))->get($this->endpoint($scope, 'image'))->assertNotFound();
        $snap = $service->snapshot($this->teacher->id, $this->teacher->user()->first()->full_name,
            app(ReportTemplateService::class)->settings()['fields']['manager_name']);
        $this->assertNull($snap[$scope]);
        $this->post($this->endpoint($scope), $oldVersion)->assertSessionHasErrors('signature_version');
        $record = $this->uploadSignature($scope);
        $this->assertSame(2, $record->revision);
        $this->assertTrue($service->state($this->signer($scope), $scope)['name_matches']);
    }

    public static function signatureScopes(): array { return [['teacher'], ['manager']]; }

    #[DataProvider('signatureScopes')]
    public function test_new_upload_rejects_an_open_approval_form_until_admin_reloads(string $scope): void
    {
        $report = $this->draftFinal();
        $content = app(QuarterlyReportContentService::class);
        $workflow = app(QuarterlyReportWorkflowService::class);
        $report = $workflow->transition($this->teacher->user, $report, 'submit', ['expected_snapshot' => $content->signature($report)]);
        $old = $content->signature($report);
        $this->uploadSignature($scope);
        $this->actingAs($this->admin)->post(route('admin.quarterly-reports.approve', $report), ['expected_snapshot' => $old])
            ->assertSessionHasErrors('expected_snapshot');
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertNull($report->fresh()->approved_snapshot);
        $this->post(route('admin.quarterly-reports.approve', $report), ['expected_snapshot' => $content->signature($report->fresh())])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($report->fresh()->approved_snapshot['signatures'][$scope]);
    }

    public function test_both_signatures_are_frozen_in_approval_and_pdf_ignores_later_replacements_or_missing_source_files(): void
    {
        $teacher = $this->uploadSignature('teacher');
        $manager = $this->uploadSignature('manager');
        [$report, $file] = $this->publish();
        $snapshot = $report->approved_snapshot;
        $this->assertSame($teacher->file_id, $snapshot['signatures']['teacher']['file_id']);
        $this->assertSame($manager->file_id, $snapshot['signatures']['manager']['file_id']);
        $this->assertSame($this->teacher->id, $snapshot['signatures']['teacher']['teacher_id']);
        $this->assertSame($snapshot, $file->public_snapshot);
        $pdf = app(QuarterlyReportPdfService::class);
        $original = $pdf->bytes($this->teacher->user, $report, $file);
        if ($directory = getenv('DPE_SIGNATURE_PDF_QA_DIR')) {
            if (! is_dir($directory)) { mkdir($directory, 0700, true); }
            file_put_contents($directory.'/signed.pdf', $original);
        }
        $this->uploadSignature('teacher', UploadedFile::fake()->image('changed.png', 150, 400));
        $this->uploadSignature('manager', UploadedFile::fake()->image('changed.jpg', 200, 200));
        Storage::disk('local')->delete($teacher->file->storage_key);
        Storage::disk('local')->delete($manager->file->storage_key);
        $this->assertStringStartsWith('%PDF-', $pdf->preview($this->teacher->user, $report->fresh()));
        $again = $pdf->generate($this->teacher->user, $report->fresh(), ['expected_snapshot' => app(QuarterlyReportContentService::class)->signature($report->fresh())]);
        $this->assertSame($file->id, $again->id);
        $this->assertSame($snapshot, $report->fresh()->approved_snapshot);
        $this->assertSame($original, $pdf->bytes($this->teacher->user, $report, $file));
    }

    public function test_broken_live_image_is_private_and_can_be_replaced_without_rewriting_approved_history(): void
    {
        $record = $this->uploadSignature('teacher');
        Storage::disk('local')->put($record->file->storage_key, 'corrupt bytes');
        $this->actingAs($this->teacher->user)->get($this->endpoint('teacher', 'image'))->assertNotFound();
        $this->get(route('teacher.profile.show'))->assertOk()->assertSee('Berkas tanda tangan tidak tersedia');
        try {
            app(ReportSignatureService::class)->snapshot($this->teacher->id, $this->teacher->user->full_name, config('report-pdf.defaults.manager_name'));
            $this->fail('Corrupt intended signature must not silently disappear.');
        } catch (ValidationException $error) { $this->assertArrayHasKey('signatures', $error->errors()); }
        $this->uploadSignature('teacher');
        $this->get($this->endpoint('teacher', 'image'))->assertOk();
    }

    public function test_upload_rolls_back_file_and_metadata_when_record_insert_fails(): void
    {
        $count = FileAsset::count();
        ReportSignature::creating(fn () => throw new \RuntimeException('Injected signature insert failure'));
        try {
            $data = $this->signatureData('teacher');
            app(ReportSignatureService::class)->save($this->teacher->user, 'teacher', $data, $data['signature_file']);
            $this->fail('Expected injected failure');
        } catch (\RuntimeException $error) { $this->assertSame('Injected signature insert failure', $error->getMessage()); }
        finally { ReportSignature::flushEventListeners(); }
        $this->assertSame($count, FileAsset::count());
        $this->assertSame(0, ReportSignature::whereNotNull('file_id')->count());
        $this->assertSame([], Storage::disk('local')->allFiles('report-signatures'));
    }

    public function test_legacy_snapshots_still_render_and_remote_or_mismatched_images_are_rejected(): void
    {
        $this->uploadSignature('teacher');
        $report = $this->draftFinal();
        $snapshot = app(QuarterlyReportContentService::class)->snapshot($report);
        $renderer = app(QuarterlyReportPdfRenderer::class);
        $legacy = $snapshot;
        unset($legacy['signatures']);
        $this->assertStringStartsWith('%PDF-', $renderer->render($legacy));
        foreach (['remote', 'wrong_teacher', 'wrong_name', 'wrong_hash'] as $kind) {
            $changed = $snapshot;
            match ($kind) {
                'remote' => $changed['signatures']['teacher']['data_uri'] = 'https://example.com/sign.png',
                'wrong_teacher' => $changed['signatures']['teacher']['teacher_id'] = (string) Str::uuid(),
                'wrong_name' => $changed['signatures']['teacher']['signer_name'] = 'Orang lain',
                'wrong_hash' => $changed['signatures']['teacher']['sha256'] = str_repeat('0', 64),
            };
            try { $renderer->validateSnapshot($changed); $this->fail('Invalid signature accepted'); }
            catch (ValidationException $error) { $this->assertArrayHasKey('signatures', $error->errors()); }
        }
    }

    public function test_migration_protects_signature_history_from_rollback(): void
    {
        $this->uploadSignature('teacher');
        $migration = require database_path('migrations/2026_10_20_010000_create_report_signatures.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Riwayat tidak dihapus otomatis');
        $migration->down();
    }

    public function test_empty_signature_migration_can_rollback_and_reapply_without_changing_reports(): void
    {
        $migration = require database_path('migrations/2026_10_20_010000_create_report_signatures.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('report_signatures'));
        $this->assertDatabaseCount('monthly_reports', 3);
        $migration->up();
        $this->assertDatabaseCount('report_signatures', 1);
        $this->assertSame(0, ReportSignature::firstOrFail()->revision);
    }
}
