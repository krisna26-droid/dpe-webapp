<?php

namespace Tests\Feature;

use App\Models\ReportSignature;
use App\Services\ReportSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WhatsappReportFixtures;
use Tests\TestCase;

class ManagerSignatureRecoveryTest extends TestCase
{
    use RefreshDatabase, WhatsappReportFixtures;

    private function uploadSignature(string $scope): ReportSignature
    {
        $actor = $scope === 'manager' ? $this->superadmin : $this->teacher->user;
        $base = $scope === 'manager' ? 'superadmin.report-template.signature.' : 'teacher.profile.signature.';
        $version = app(ReportSignatureService::class)->state($actor, $scope)['version'];
        $this->actingAs($actor)->post(route($base.'store'), [
            'signature_version' => $version,
            'signature_file' => UploadedFile::fake()->image($scope.'.png', 480, 160),
        ])->assertRedirect()->assertSessionHasNoErrors();

        return ReportSignature::where('owner_key', $scope === 'manager' ? 'manager' : 'teacher:'.$this->teacher->id)
            ->whereNotNull('file_id')->latest('revision')->firstOrFail();
    }

    private function removeAnchor(): void
    {
        DB::table('report_signatures')->where('owner_key', 'manager')->where('revision', 0)->delete();
    }

    public function test_manager_can_upload_when_production_has_only_teacher_signatures(): void
    {
        $teacher = $this->uploadSignature('teacher');
        $before = $teacher->getRawOriginal();
        $this->removeAnchor();

        $manager = $this->uploadSignature('manager');

        $this->assertSame(1, $manager->revision);
        $this->assertDatabaseHas('report_signatures', ['owner_key' => 'manager', 'revision' => 0, 'file_id' => null]);
        $this->assertSame($before, $teacher->fresh()->getRawOriginal());
        Storage::disk('local')->assertExists($teacher->file->storage_key);
        Storage::disk('local')->assertExists($manager->file->storage_key);
    }

    public function test_recovery_keeps_existing_manager_versions_and_continues_revision_numbering(): void
    {
        $first = $this->uploadSignature('manager');
        $before = $first->getRawOriginal();
        $this->removeAnchor();

        $second = $this->uploadSignature('manager');

        $this->assertSame(2, $second->revision);
        $this->assertNotSame($first->file_id, $second->file_id);
        $this->assertSame($before, $first->fresh()->getRawOriginal());
        $this->assertSame(1, ReportSignature::where('owner_key', 'manager')->where('revision', 0)->count());
        Storage::disk('local')->assertExists($first->file->storage_key);
    }

    #[DataProvider('databaseStates')]
    public function test_repair_migration_can_be_repeated_without_changing_signature_history(bool $withManager): void
    {
        $this->uploadSignature('teacher');
        if ($withManager) {
            $this->uploadSignature('manager');
        }
        $this->removeAnchor();
        $before = ReportSignature::orderBy('id')->get()->map->getRawOriginal()->all();
        $migration = require database_path('migrations/2026_11_15_090000_restore_manager_signature_anchor.php');

        $migration->up();
        $anchor = ReportSignature::where('owner_key', 'manager')->where('revision', 0)->firstOrFail()->getRawOriginal();
        $migration->up();
        $migration->down();

        $this->assertSame($before, ReportSignature::whereNotNull('file_id')->orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame($anchor, ReportSignature::where('owner_key', 'manager')->where('revision', 0)->firstOrFail()->getRawOriginal());
        $this->assertSame(1, ReportSignature::where('owner_key', 'manager')->where('revision', 0)->count());
    }

    public static function databaseStates(): array
    {
        return [[false], [true]];
    }

    public function test_missing_anchor_does_not_block_report_workflow_or_change_an_approved_snapshot(): void
    {
        $teacher = $this->uploadSignature('teacher');
        $this->removeAnchor();
        [$report, $file] = $this->publish();
        $snapshot = $report->approved_snapshot;

        $this->assertSame('approved', $report->status);
        $this->assertSame($teacher->file_id, $snapshot['signatures']['teacher']['file_id']);
        $this->assertNull($snapshot['signatures']['manager']);
        $this->assertSame($snapshot, $file->public_snapshot);
        $this->uploadSignature('manager');
        $this->assertSame($snapshot, $report->fresh()->approved_snapshot);
        $this->assertSame($snapshot, $file->fresh()->public_snapshot);
    }

    public function test_stale_manager_upload_still_rejects_changes_after_recovery(): void
    {
        $this->uploadSignature('manager');
        $oldVersion = app(ReportSignatureService::class)->state($this->superadmin, 'manager')['version'];
        $current = $this->uploadSignature('manager');
        $this->removeAnchor();
        $beforeFiles = Storage::disk('local')->allFiles('report-signatures');

        $this->actingAs($this->superadmin)->post(route('superadmin.report-template.signature.store'), [
            'signature_version' => $oldVersion,
            'signature_file' => UploadedFile::fake()->image('stale.png', 480, 160),
        ])->assertRedirect()->assertSessionHasErrors('signature_version');

        $this->assertSame(2, ReportSignature::where('owner_key', 'manager')->whereNotNull('file_id')->count());
        $this->assertSame($current->id, app(ReportSignatureService::class)->state($this->superadmin, 'manager')['record']->id);
        $this->assertSame($beforeFiles, Storage::disk('local')->allFiles('report-signatures'));
    }

    public function test_branch_admin_cannot_upload_or_create_a_manager_anchor(): void
    {
        $this->removeAnchor();
        $this->actingAs($this->admin)->post(route('superadmin.report-template.signature.store'), [
            'signature_version' => str_repeat('0', 64),
            'signature_file' => UploadedFile::fake()->image('forbidden.png', 480, 160),
        ])->assertForbidden();

        $this->assertDatabaseMissing('report_signatures', ['owner_key' => 'manager', 'revision' => 0]);
        $this->assertSame([], Storage::disk('local')->allFiles('report-signatures'));
    }
}
