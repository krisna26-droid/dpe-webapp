<?php

namespace Tests\Feature;

use App\Http\Controllers\SystemSettingController;
use App\Http\Requests\SystemSettingUpdateRequest;
use App\Models\FileAsset;
use App\Models\SystemSetting;
use App\Services\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemSettingControllerTest extends TestCase
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

    private function makeRequest(
        array $data,
        string $method = 'PUT'
    ): SystemSettingUpdateRequest {
        $request = SystemSettingUpdateRequest::create(
            '/system-settings',
            $method,
            $data
        );

        $request->setContainer(app());
        $request->merge($data);

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $request->setValidator($validator);

        return $request;
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

    public function test_show_returns_singleton_setting(): void
    {
        $setting = $this->makeSetting();

        $controller = new SystemSettingController(
            $this->service
        );

        $response = $controller->show();

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $data = $response->getData();

        $this->assertSame(
            $setting->id,
            $data->id
        );

        $this->assertSame(
            'Dharma Private English',
            $data->organization_name
        );
    }

    public function test_show_throws_when_setting_does_not_exist(): void
    {
        $controller = new SystemSettingController(
            $this->service
        );

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $controller->show();
    }

    public function test_update_returns_updated_setting(): void
    {
        $this->makeSetting();

        $controller = new SystemSettingController(
            $this->service
        );

        $request = $this->makeRequest([
            'organization_name' => 'Dharma Private English Updated',
            'organization_description' => 'Updated description.',
            'contact_email' => 'updated@example.com',
            'contact_phone' => '089876543210',
            'logo_file_id' => null,
            'default_report_due_day' => 20,
            'default_monthly_video_target' => 8,
            'in_app_reminders_enabled' => false,
        ]);

        $response = $controller->update($request);

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $data = $response->getData();

        $this->assertSame(
            'Dharma Private English Updated',
            $data->organization_name
        );

        $this->assertSame(
            20,
            $data->default_report_due_day
        );

        $this->assertSame(
            8,
            $data->default_monthly_video_target
        );

        $this->assertFalse(
            $data->in_app_reminders_enabled
        );
    }

    public function test_update_can_set_logo_file(): void
    {
        $this->makeSetting();

        $fileAsset = $this->makeFileAsset();

        $controller = new SystemSettingController(
            $this->service
        );

        $data = $this->validData();

        $data['logo_file_id'] = $fileAsset->id;

        $request = $this->makeRequest($data);

        $response = $controller->update($request);

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $result = $response->getData();

        $this->assertSame(
            $fileAsset->id,
            $result->logo_file_id
        );
    }

    public function test_update_persists_changes(): void
    {
        $this->makeSetting();

        $controller = new SystemSettingController(
            $this->service
        );

        $request = $this->makeRequest([
            'organization_name' => 'Updated Organization',
            'organization_description' => 'Updated description.',
            'contact_email' => 'updated@example.com',
            'contact_phone' => '080000000000',
            'logo_file_id' => null,
            'default_report_due_day' => 15,
            'default_monthly_video_target' => 10,
            'in_app_reminders_enabled' => false,
        ]);

        $controller->update($request);

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
}
