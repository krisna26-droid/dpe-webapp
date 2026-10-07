<?php

namespace Tests\Feature;

use App\Http\Requests\MonthlyReportSkillStoreRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MonthlyReportSkillRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $request = new MonthlyReportSkillStoreRequest();

        return Validator::make(
            $data,
            $request->rules()
        );
    }

    public function test_request_accepts_valid_data(): void
    {
        $validator = $this->validate([
            'report_id' => '550e8400-e29b-41d4-a716-446655440000',
            'skill_id' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
            'trend' => 'improving',
            'description' => 'Student menunjukkan perkembangan yang baik.',
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_report_id_is_required(): void
    {
        $validator = $this->validate([
            'skill_id' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
            'description' => 'Description.',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'report_id',
            $validator->errors()->toArray()
        );
    }

    public function test_skill_id_is_required(): void
    {
        $validator = $this->validate([
            'report_id' => '550e8400-e29b-41d4-a716-446655440000',
            'description' => 'Description.',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'skill_id',
            $validator->errors()->toArray()
        );
    }

    public function test_description_is_required(): void
    {
        $validator = $this->validate([
            'report_id' => '550e8400-e29b-41d4-a716-446655440000',
            'skill_id' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'description',
            $validator->errors()->toArray()
        );
    }

    public function test_trend_is_optional(): void
    {
        $validator = $this->validate([
            'report_id' => '550e8400-e29b-41d4-a716-446655440000',
            'skill_id' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
            'description' => 'Description without trend.',
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_report_id_must_be_uuid(): void
    {
        $validator = $this->validate([
            'report_id' => 'invalid-id',
            'skill_id' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
            'description' => 'Description.',
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_skill_id_must_be_uuid(): void
    {
        $validator = $this->validate([
            'report_id' => '550e8400-e29b-41d4-a716-446655440000',
            'skill_id' => 'invalid-id',
            'description' => 'Description.',
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_trend_must_be_string_when_provided(): void
    {
        $validator = $this->validate([
            'report_id' => '550e8400-e29b-41d4-a716-446655440000',
            'skill_id' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
            'trend' => 123,
            'description' => 'Description.',
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_description_must_be_string(): void
    {
        $validator = $this->validate([
            'report_id' => '550e8400-e29b-41d4-a716-446655440000',
            'skill_id' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
            'description' => 123,
        ]);

        $this->assertTrue($validator->fails());
    }
}
