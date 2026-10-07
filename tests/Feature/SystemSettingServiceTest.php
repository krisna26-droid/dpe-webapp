<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\SystemSetting;
use App\Services\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemSettingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SystemSettingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SystemSettingService();
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

    public function test_get_returns_singleton_setting(): void
    {
        $setting = $this->makeSetting();

        $result = $this->service->get();

        $this->assertInstanceOf(
            SystemSetting::class,
            $result
        );

        $this->assertSame(
            $setting->id,
            $result->id
        );
    }

    public function test_get_returns_setting_with_id_one(): void
    {
        $this->makeSetting();

        $result = $this->service->get();

        $this->assertSame(
            1,
            $result->id
        );
    }

    public function test_get_throws_exception_when_setting_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->get();
    }

    public function test_update_updates_organization_information(): void
    {
        $this->makeSetting();

        $result = $this->service->update([
            'organization_name' => 'Dharma Private English Updated',
            'organization_description' => 'Updated description.',
            'contact_email' => 'updated@example.com',
            'contact_phone' => '089876543210',
            'logo_file_id' => null,
            'default_report_due_day' => 20,
            'default_monthly_video_target' => 8,
            'in_app_reminders_enabled' => false,
        ]);

        $this->assertSame(
            'Dharma Private English Updated',
            $result->organization_name
        );

        $this->assertSame(
            'Updated description.',
            $result->organization_description
        );

        $this->assertSame(
            'updated@example.com',
            $result->contact_email
        );

        $this->assertSame(
            '089876543210',
            $result->contact_phone
        );
    }

    public function test_update_updates_report_and_video_settings(): void
    {
        $this->makeSetting();

        $result = $this->service->update([
            'organization_name' => 'Dharma Private English',
            'organization_description' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'logo_file_id' => null,
            'default_report_due_day' => 15,
            'default_monthly_video_target' => 10,
            'in_app_reminders_enabled' => true,
        ]);

        $this->assertSame(
            15,
            $result->default_report_due_day
        );

        $this->assertSame(
            10,
            $result->default_monthly_video_target
        );

        $this->assertTrue(
            $result->in_app_reminders_enabled
        );
    }

    public function test_update_can_change_logo_file(): void
    {
        $this->makeSetting();

        $fileAsset = $this->makeFileAsset();

        $result = $this->service->update([
            'organization_name' => 'Dharma Private English',
            'organization_description' => 'English learning organization.',
            'contact_email' => 'test@example.com',
            'contact_phone' => '081234567890',
            'logo_file_id' => $fileAsset->id,
            'default_report_due_day' => 25,
            'default_monthly_video_target' => 4,
            'in_app_reminders_enabled' => true,
        ]);

        $this->assertSame(
            $fileAsset->id,
            $result->logo_file_id
        );
    }

    public function test_update_can_remove_logo_file(): void
    {
        $fileAsset = $this->makeFileAsset();

        $this->makeSetting($fileAsset->id);

        $result = $this->service->update([
            'organization_name' => 'Dharma Private English',
            'organization_description' => 'English learning organization.',
            'contact_email' => 'test@example.com',
            'contact_phone' => '081234567890',
            'logo_file_id' => null,
            'default_report_due_day' => 25,
            'default_monthly_video_target' => 4,
            'in_app_reminders_enabled' => true,
        ]);

        $this->assertNull(
            $result->logo_file_id
        );
    }

    public function test_update_persists_changes_to_database(): void
    {
        $this->makeSetting();

        $this->service->update([
            'organization_name' => 'Updated Organization',
            'organization_description' => 'Updated.',
            'contact_email' => 'updated@example.com',
            'contact_phone' => '080000000000',
            'logo_file_id' => null,
            'default_report_due_day' => 10,
            'default_monthly_video_target' => 6,
            'in_app_reminders_enabled' => false,
        ]);

        $this->assertDatabaseHas('system_settings', [
            'id' => 1,
            'organization_name' => 'Updated Organization',
            'organization_description' => 'Updated.',
            'contact_email' => 'updated@example.com',
            'contact_phone' => '080000000000',
            'default_report_due_day' => 10,
            'default_monthly_video_target' => 6,
            'in_app_reminders_enabled' => 0,
        ]);
    }
}
