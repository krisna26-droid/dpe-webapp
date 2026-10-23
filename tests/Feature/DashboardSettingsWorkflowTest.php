<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\FileAsset;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DashboardSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardSettingsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $sa;
    private User $admin;
    private Branch $branch;
    private Branch $foreign;

    protected function setUp(): void
    {
        parent::setUp(); Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00', 'Asia/Makassar'));
        $this->sa = $this->user('superadmin'); $this->admin = $this->user('admin');
        $this->branch = $this->branch('Cabang A'); $this->foreign = $this->branch('Cabang Rahasia');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id,
            'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function user(string $role): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(12), 'full_name' => 'Pengguna '.$role,
            'role_code' => $role, 'password_hash' => Hash::make('Password123!'), 'is_active' => true]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(8), 'name' => $name, 'address' => 'Alamat lama',
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function data(array $extra = []): array
    {
        return $extra + ['organization_name' => 'DPE Uji', 'organization_description' => 'Deskripsi uji', 'contact_email' => 'dpe@example.test',
            'contact_phone' => '081234567890', 'default_report_due_day' => 31, 'default_monthly_video_target' => 4,
            'in_app_reminders_enabled' => 1, 'record_version' => app(DashboardSettingsService::class)->version(app(DashboardSettingsService::class)->setting())];
    }

    private function address(array $extra = []): array
    {
        return $extra + ['address' => 'Jalan Baru 20', 'record_version' => app(DashboardSettingsService::class)->version($this->branch->fresh())];
    }

    public function test_all_remaining_sidebar_menus_are_active(): void
    {
        $this->actingAs($this->sa)->get(route('superadmin.dashboard'))->assertOk()->assertSee('href="'.route('superadmin.settings.index').'"', false);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('href="'.route('admin.payments.index').'"', false)
            ->assertSee('href="'.route('admin.branch-settings.index').'"', false)->assertDontSee('Segera hadir');
    }

    public function test_global_page_does_not_create_configuration_on_get(): void
    {
        $this->actingAs($this->sa)->get(route('superadmin.settings.index'))->assertOk()->assertSee('Pengaturan sistem belum disiapkan')
            ->assertSee('data-confirm-form', false)->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_initial_save_then_existing_save_uses_current_version(): void
    {
        $this->actingAs($this->sa)->put(route('superadmin.settings.update'), $this->data())->assertRedirect(route('superadmin.settings.index'));
        $this->assertDatabaseHas('system_settings', ['id' => 1, 'organization_name' => 'DPE Uji', 'default_report_due_day' => 31]);
        $this->put(route('superadmin.settings.update'), $this->data(['default_report_due_day' => 20, 'default_monthly_video_target' => 0,
            'in_app_reminders_enabled' => 0]))->assertRedirect(route('superadmin.settings.index'));
        $this->assertDatabaseHas('system_settings', ['id' => 1, 'default_report_due_day' => 20, 'default_monthly_video_target' => 0, 'in_app_reminders_enabled' => 0]);
        $this->assertDatabaseCount('system_settings', 1);
    }

    public function test_stale_missing_and_existing_forms_cannot_overwrite(): void
    {
        $old = $this->data();
        $this->actingAs($this->sa)->put(route('superadmin.settings.update'), $old)->assertRedirect();
        $this->putJson(route('superadmin.settings.update'), array_replace($old, ['organization_name' => 'Stale']))->assertUnprocessable()->assertJsonValidationErrors('record_version');
        $current = $this->data();
        $this->put(route('superadmin.settings.update'), $this->data(['organization_name' => 'Terbaru']))->assertRedirect();
        $this->putJson(route('superadmin.settings.update'), $current)->assertUnprocessable()->assertJsonValidationErrors('record_version');
        $this->assertDatabaseHas('system_settings', ['id' => 1, 'organization_name' => 'Terbaru']);
    }

    public function test_legacy_api_changes_are_also_detected_by_page(): void
    {
        $this->actingAs($this->sa)->put(route('superadmin.settings.update'), $this->data())->assertRedirect();
        $old = $this->data();
        $api = $this->data(['organization_name' => 'Dari API']); unset($api['record_version']);
        $this->putJson(route('superadmin.system-settings.update'), $api)->assertOk();
        $this->putJson(route('superadmin.settings.update'), $old)->assertUnprocessable()->assertJsonValidationErrors('record_version');
    }

    public function test_global_edit_preserves_existing_logo_reference(): void
    {
        $this->actingAs($this->sa)->put(route('superadmin.settings.update'), $this->data())->assertRedirect();
        $asset = FileAsset::create(['id' => (string) Str::uuid(), 'storage_key' => 'legacy.png', 'original_name' => 'legacy.png',
            'mime_type' => 'image/png', 'size_bytes' => 10, 'sha256_hex' => str_repeat('a', 64)]);
        SystemSetting::find(1)->update(['logo_file_id' => $asset->id]);
        $this->put(route('superadmin.settings.update'), $this->data(['organization_name' => 'Ganti nama']))->assertRedirect();
        $this->assertDatabaseHas('system_settings', ['id' => 1, 'logo_file_id' => $asset->id]);
    }

    public static function invalidSettings(): array
    {
        return [['organization_name', ''], ['organization_name', str_repeat('n', 256)], ['contact_email', 'invalid'],
            ['default_report_due_day', 0], ['default_report_due_day', 32], ['default_monthly_video_target', -1],
            ['default_monthly_video_target', 32768], ['in_app_reminders_enabled', 'yes'], ['record_version', ''],
            ['organization_description', ['array']], ['logo_file_id', (string) Str::uuid()], ['branch_id', (string) Str::uuid()]];
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_do_not_write(string $field, mixed $value): void
    {
        $this->actingAs($this->sa)->putJson(route('superadmin.settings.update'), $this->data([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('system_settings', 0);
    }

    public static function deniedRoles(): array { return [['admin'], ['teacher'], ['student']]; }

    #[DataProvider('deniedRoles')]
    public function test_only_superadmin_can_view_and_save_global_settings(string $role): void
    {
        $this->actingAs($this->user($role))->get(route('superadmin.settings.index'))->assertForbidden();
        $this->putJson(route('superadmin.settings.update'), $this->data())->assertForbidden();
    }

    public function test_guest_must_login_for_settings(): void
    {
        $this->get(route('superadmin.settings.index'))->assertRedirect(route('login'));
        $this->get(route('admin.branch-settings.index'))->assertRedirect(route('login'));
    }

    public function test_admin_sees_and_edits_only_current_assigned_branch_address(): void
    {
        $this->actingAs($this->admin)->get(route('admin.branch-settings.index'))->assertOk()->assertSee('Cabang A')->assertDontSee('Cabang Rahasia');
        $this->get(route('admin.branch-settings.edit', $this->branch))->assertOk()->assertSee('Kembali ke pengaturan cabang');
        $before = $this->branch->fresh()->getAttributes();
        $this->put(route('admin.branch-settings.update', $this->branch), $this->address())->assertRedirect(route('admin.branch-settings.index'));
        $after = $this->branch->fresh()->getAttributes(); unset($before['address'], $after['address']);
        $this->assertSame($before, $after);
        $this->assertDatabaseHas('branches', ['id' => $this->branch->id, 'address' => 'Jalan Baru 20']);
        $this->get(route('admin.branch-settings.edit', $this->foreign))->assertForbidden();
        $this->putJson(route('admin.branch-settings.update', $this->foreign), $this->address())->assertForbidden();
    }

    public static function forbiddenBranchFields(): array
    {
        return [['name', 'Changed'], ['code', 'Changed'], ['is_active', 0], ['payment_recap_day', 18],
            ['timezone_name', 'Asia/Jakarta'], ['portal_attendance_visible', 1], ['branch_id', 'forged'], ['admin_user_id', 'forged']];
    }

    #[DataProvider('forbiddenBranchFields')]
    public function test_admin_cannot_change_owner_only_fields(string $field, mixed $value): void
    {
        $this->actingAs($this->admin)->putJson(route('admin.branch-settings.update', $this->branch), $this->address([$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseHas('branches', ['id' => $this->branch->id, 'address' => 'Alamat lama']);
    }

    public function test_branch_stale_form_keeps_original_fingerprint_on_validation_redirect(): void
    {
        $old = $this->address(); $this->branch->update(['address' => 'Data terbaru']);
        $url = route('admin.branch-settings.edit', $this->branch);
        $this->actingAs($this->admin)->from($url)->put(route('admin.branch-settings.update', $this->branch), $old)->assertRedirect($url)->assertSessionHasErrors('record_version');
        $this->get($url)->assertOk()->assertSee('value="'.$old['record_version'].'"', false);
        $this->assertDatabaseHas('branches', ['id' => $this->branch->id, 'address' => 'Data terbaru']);
    }

    public function test_expired_future_and_inactive_branch_assignments_are_not_editable(): void
    {
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-10-13']);
        $this->actingAs($this->admin)->get(route('admin.branch-settings.index'))->assertOk()->assertSee('Belum ada cabang yang ditugaskan')->assertViewHas('branches', fn ($branches) => $branches->isEmpty());
        $this->putJson(route('admin.branch-settings.update', $this->branch), $this->address())->assertForbidden();
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['starts_on' => '2026-10-15', 'ends_on' => null]);
        $this->get(route('admin.branch-settings.edit', $this->branch))->assertForbidden();
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['starts_on' => '2026-10-14']);
        $this->branch->update(['is_active' => false]);
        $this->get(route('admin.branch-settings.index'))->assertOk()->assertSee('Nonaktif');
        $this->putJson(route('admin.branch-settings.update', $this->branch), $this->address())->assertForbidden();
    }

    public function test_fresh_actor_role_and_activity_are_rechecked_in_service(): void
    {
        $stale = clone $this->admin; $this->admin->update(['role_code' => 'teacher']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(DashboardSettingsService::class)->saveAddress($stale, $this->branch, $this->address());
    }

    public function test_teacher_and_student_cannot_use_branch_settings(): void
    {
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs($this->user($role))->get(route('admin.branch-settings.index'))->assertForbidden();
            $this->putJson(route('admin.branch-settings.update', $this->branch), $this->address())->assertForbidden();
        }
    }

    public function test_a_value_changed_then_restored_still_invalidates_old_system_and_branch_forms(): void
    {
        $this->actingAs($this->sa)->put(route('superadmin.settings.update'), $this->data())->assertRedirect();
        $old = $this->data();
        $this->put(route('superadmin.settings.update'), $this->data(['organization_name' => 'Berubah']))->assertRedirect();
        $this->put(route('superadmin.settings.update'), $this->data(['organization_name' => 'DPE Uji']))->assertRedirect();
        $this->putJson(route('superadmin.settings.update'), $old)->assertUnprocessable()->assertJsonValidationErrors('record_version');
        $oldAddress = $this->address();
        $this->actingAs($this->admin)->put(route('admin.branch-settings.update', $this->branch), $this->address(['address' => 'Berubah']))->assertRedirect();
        $this->put(route('admin.branch-settings.update', $this->branch), $this->address(['address' => 'Alamat lama']))->assertRedirect();
        $this->putJson(route('admin.branch-settings.update', $this->branch), $oldAddress)->assertUnprocessable()->assertJsonValidationErrors('record_version');
        $this->assertDatabaseHas('student_portal_events', ['event_type' => 'branch_address_changed', 'branch_id' => $this->branch->id, 'actor_user_id' => $this->admin->id]);
    }

    public function test_source_changes_from_superadmin_also_invalidate_old_branch_address_form(): void
    {
        $old = $this->address();
        $values = $this->branch->fresh()->only(['code', 'name', 'address', 'timezone_name', 'payment_recap_day', 'is_active']);
        $this->actingAs($this->sa);
        app(\App\Services\BranchService::class)->update($this->branch, ['address' => 'Berubah'] + $values);
        app(\App\Services\BranchService::class)->update($this->branch, $values);
        $this->actingAs($this->admin)->putJson(route('admin.branch-settings.update', $this->branch), $old)->assertUnprocessable()->assertJsonValidationErrors('record_version');
    }

    public function test_settings_and_address_roll_back_if_history_write_fails(): void
    {
        $this->actingAs($this->sa)->put(route('superadmin.settings.update'), $this->data())->assertRedirect();
        $history = \App\Models\StudentPortalEvent::count();
        \App\Models\StudentPortalEvent::creating(function () { throw new \RuntimeException('Fixture history failure'); });
        try {
            try { app(DashboardSettingsService::class)->saveSystem($this->sa, $this->data(['organization_name' => 'Tidak tersimpan'])); }
            catch (\RuntimeException $e) { $this->assertSame('Fixture history failure', $e->getMessage()); }
            try { app(DashboardSettingsService::class)->saveAddress($this->admin, $this->branch, $this->address()); }
            catch (\RuntimeException $e) { $this->assertSame('Fixture history failure', $e->getMessage()); }
            $this->assertDatabaseHas('system_settings', ['id' => 1, 'organization_name' => 'DPE Uji']);
            $this->assertDatabaseHas('branches', ['id' => $this->branch->id, 'address' => 'Alamat lama']);
            $this->assertSame($history, \App\Models\StudentPortalEvent::count());
        } finally { \App\Models\StudentPortalEvent::flushEventListeners(); }
    }
}
