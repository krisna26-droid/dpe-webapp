<?php

namespace Tests\Feature;

use App\Http\Requests\SystemSettingUpdateRequest;
use App\Models\FileAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemSettingUpdateRequestTest extends TestCase
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

    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $request = SystemSettingUpdateRequest::create(
            '/system-settings',
            'PUT',
            $data
        );

        $request->setContainer(app());

        return Validator::make(
            $data,
            $request->rules()
        );
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

    public function test_valid_data_passes_validation(): void
    {
        $validator = $this->validate(
            $this->validData()
        );

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_organization_name_is_required(): void
    {
        $data = $this->validData();

        unset($data['organization_name']);

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'organization_name',
            $validator->errors()->toArray()
        );
    }

    public function test_optional_fields_can_be_null(): void
    {
        $data = $this->validData();

        $data['organization_description'] = null;
        $data['contact_email'] = null;
        $data['contact_phone'] = null;
        $data['logo_file_id'] = null;

        $validator = $this->validate($data);

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_contact_email_must_be_valid_email(): void
    {
        $data = $this->validData();

        $data['contact_email'] = 'invalid-email';

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'contact_email',
            $validator->errors()->toArray()
        );
    }

    public function test_logo_file_id_must_exist_when_provided(): void
    {
        $data = $this->validData();

        $data['logo_file_id'] = (string) Str::uuid();

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'logo_file_id',
            $validator->errors()->toArray()
        );
    }

    public function test_existing_logo_file_id_passes_validation(): void
    {
        $fileAsset = $this->makeFileAsset();

        $data = $this->validData();

        $data['logo_file_id'] = $fileAsset->id;

        $validator = $this->validate($data);

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_report_due_day_must_be_between_one_and_thirty_one(): void
    {
        foreach ([0, 32] as $value) {
            $data = $this->validData();

            $data['default_report_due_day'] = $value;

            $validator = $this->validate($data);

            $this->assertTrue(
                $validator->fails()
            );

            $this->assertArrayHasKey(
                'default_report_due_day',
                $validator->errors()->toArray()
            );
        }
    }

    public function test_monthly_video_target_cannot_be_negative(): void
    {
        $data = $this->validData();

        $data['default_monthly_video_target'] = -1;

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'default_monthly_video_target',
            $validator->errors()->toArray()
        );
    }

    public function test_monthly_video_target_can_be_zero(): void
    {
        $data = $this->validData();

        $data['default_monthly_video_target'] = 0;

        $validator = $this->validate($data);

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_in_app_reminders_enabled_is_required(): void
    {
        $data = $this->validData();

        unset($data['in_app_reminders_enabled']);

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'in_app_reminders_enabled',
            $validator->errors()->toArray()
        );
    }

    public function test_in_app_reminders_enabled_must_be_boolean(): void
    {
        $data = $this->validData();

        $data['in_app_reminders_enabled'] = 'not-a-boolean';

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'in_app_reminders_enabled',
            $validator->errors()->toArray()
        );
    }
}
