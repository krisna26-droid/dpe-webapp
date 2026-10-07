<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemSettingModelTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_uses_correct_table(): void
    {
        $model = new SystemSetting();

        $this->assertSame(
            'system_settings',
            $model->getTable()
        );
    }

    public function test_uses_integer_primary_key_without_auto_increment(): void
    {
        $model = new SystemSetting();

        $this->assertSame(
            'int',
            $model->getKeyType()
        );

        $this->assertFalse(
            $model->incrementing
        );
    }

    public function test_does_not_use_timestamps(): void
    {
        $model = new SystemSetting();

        $this->assertFalse(
            $model->usesTimestamps()
        );
    }

    public function test_has_expected_fillable_columns(): void
    {
        $model = new SystemSetting();

        $this->assertSame([
            'id',
            'organization_name',
            'organization_description',
            'contact_email',
            'contact_phone',
            'logo_file_id',
            'default_report_due_day',
            'default_monthly_video_target',
            'in_app_reminders_enabled',
        ], $model->getFillable());
    }

    public function test_casts_expected_attributes(): void
    {
        $model = new SystemSetting();

        $casts = $model->getCasts();

        $this->assertSame(
            'integer',
            $casts['id']
        );

        $this->assertSame(
            'integer',
            $casts['default_report_due_day']
        );

        $this->assertSame(
            'integer',
            $casts['default_monthly_video_target']
        );

        $this->assertSame(
            'boolean',
            $casts['in_app_reminders_enabled']
        );

        $this->assertSame(
            'datetime',
            $casts['updated_at']
        );
    }

    public function test_can_create_singleton_setting(): void
    {
        $setting = $this->makeSetting();

        $this->assertSame(
            1,
            $setting->id
        );

        $this->assertDatabaseHas('system_settings', [
            'id' => 1,
            'organization_name' => 'Dharma Private English',
            'default_report_due_day' => 25,
            'default_monthly_video_target' => 4,
            'in_app_reminders_enabled' => 1,
        ]);
    }

    public function test_logo_file_relation_returns_file_asset(): void
    {
        $fileAsset = $this->makeFileAsset();

        $setting = $this->makeSetting(
            $fileAsset->id
        );

        $this->assertInstanceOf(
            FileAsset::class,
            $setting->logoFile
        );

        $this->assertSame(
            $fileAsset->id,
            $setting->logoFile->id
        );
    }

    public function test_logo_file_can_be_null(): void
    {
        $setting = $this->makeSetting();

        $this->assertNull(
            $setting->logoFile
        );
    }

}
