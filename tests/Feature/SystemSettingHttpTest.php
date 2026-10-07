<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemSettingHttpTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode = 'superadmin',
        bool $isActive = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);
    }

    private function makeSetting(
        ?string $logoFileId = null
    ): SystemSetting {
        return SystemSetting::query()->create([
            'id' => 1,
            'organization_name' => 'Dharma Private English',
            'organization_description' => 'English learning organization.',
            'contact_email' => 'test@example.com',
            'contact_phone' => '081234567890',
            'logo_file_id' => $logoFileId,
            'default_report_due_day' => 25,
            'default_monthly_video_target' => 4,
            'in_app_reminders_enabled' => true,
        ]);
    }

    private function makeFileAsset(): FileAsset
    {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'system-setting-logo.png',
            'original_name' => 'system-setting-logo.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'sha256_hex' => hash('sha256', 'system-setting-logo.png'),
        ]);
    }

    private function validData(): array
    {
        return [
            'organization_name' => 'Dharma Private English',
            'organization_description' => 'English learning organization.',
            'contact_email' => 'test@example.com',
            'contact_phone' => '081234567890',
            'logo_file_id' => null,
            'default_report_due_day' => 25,
            'default_monthly_video_target' => 4,
            'in_app_reminders_enabled' => true,
        ];
    }

    public function test_get_system_settings_returns_singleton(): void
    {
        $user = $this->makeUser();

        $setting = $this->makeSetting();

        $response = $this
            ->actingAs($user)
            ->getJson('/superadmin/system-settings');

        $response->assertOk()
            ->assertJson([
                'id' => 1,
                'organization_name' => $setting->organization_name,
                'default_report_due_day' => 25,
                'default_monthly_video_target' => 4,
                'in_app_reminders_enabled' => true,
            ]);
    }

    public function test_get_system_settings_returns_not_found_when_singleton_does_not_exist(): void
    {
        $user = $this->makeUser();

        $response = $this
            ->actingAs($user)
            ->getJson('/superadmin/system-settings');

        $response->assertNotFound();
    }

    public function test_update_system_settings_returns_updated_data(): void
    {
        $user = $this->makeUser();

        $this->makeSetting();

        $response = $this
            ->actingAs($user)
            ->putJson('/superadmin/system-settings', [
                'organization_name' => 'Dharma Private English Updated',
                'organization_description' => 'Updated description.',
                'contact_email' => 'updated@example.com',
                'contact_phone' => '089876543210',
                'logo_file_id' => null,
                'default_report_due_day' => 20,
                'default_monthly_video_target' => 8,
                'in_app_reminders_enabled' => false,
            ]);

        $response->assertOk()
            ->assertJson([
                'id' => 1,
                'organization_name' => 'Dharma Private English Updated',
                'organization_description' => 'Updated description.',
                'contact_email' => 'updated@example.com',
                'contact_phone' => '089876543210',
                'default_report_due_day' => 20,
                'default_monthly_video_target' => 8,
                'in_app_reminders_enabled' => false,
            ]);
    }

    public function test_update_system_settings_persists_changes(): void
    {
        $user = $this->makeUser();

        $this->makeSetting();

        $this
            ->actingAs($user)
            ->putJson('/superadmin/system-settings', [
                'organization_name' => 'Updated Organization',
                'organization_description' => 'Updated description.',
                'contact_email' => 'updated@example.com',
                'contact_phone' => '080000000000',
                'logo_file_id' => null,
                'default_report_due_day' => 15,
                'default_monthly_video_target' => 10,
                'in_app_reminders_enabled' => false,
            ])
            ->assertOk();

        $this->assertDatabaseHas('system_settings', [
            'id' => 1,
            'organization_name' => 'Updated Organization',
            'organization_description' => 'Updated description.',
            'contact_email' => 'updated@example.com',
            'contact_phone' => '080000000000',
            'default_report_due_day' => 15,
            'default_monthly_video_target' => 10,
            'in_app_reminders_enabled' => 0,
        ]);
    }

    public function test_update_system_settings_accepts_existing_logo_file(): void
    {
        $user = $this->makeUser();

        $this->makeSetting();

        $fileAsset = $this->makeFileAsset();

        $data = $this->validData();
        $data['logo_file_id'] = $fileAsset->id;

        $response = $this
            ->actingAs($user)
            ->putJson('/superadmin/system-settings', $data);

        $response->assertOk()
            ->assertJson([
                'id' => 1,
                'logo_file_id' => $fileAsset->id,
            ]);
    }

    public function test_update_system_settings_rejects_invalid_email(): void
    {
        $user = $this->makeUser();

        $this->makeSetting();

        $data = $this->validData();
        $data['contact_email'] = 'invalid-email';

        $response = $this
            ->actingAs($user)
            ->putJson('/superadmin/system-settings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'contact_email',
            ]);
    }

    public function test_update_system_settings_rejects_invalid_report_due_day(): void
    {
        $user = $this->makeUser();

        $this->makeSetting();

        $data = $this->validData();
        $data['default_report_due_day'] = 0;

        $response = $this
            ->actingAs($user)
            ->putJson('/superadmin/system-settings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'default_report_due_day',
            ]);
    }

    public function test_update_system_settings_rejects_negative_video_target(): void
    {
        $user = $this->makeUser();

        $this->makeSetting();

        $data = $this->validData();
        $data['default_monthly_video_target'] = -1;

        $response = $this
            ->actingAs($user)
            ->putJson('/superadmin/system-settings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'default_monthly_video_target',
            ]);
    }
}
