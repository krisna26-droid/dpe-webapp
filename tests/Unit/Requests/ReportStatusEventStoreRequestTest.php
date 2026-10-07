<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\ReportStatusEventStoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportStatusEventStoreRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new ReportStatusEventStoreRequest())->rules();
    }

    private function validData(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'report_id' => (string) Str::uuid(),
            'actor_user_id' => (string) Str::uuid(),
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'comment' => 'Report submitted.',
            'occurred_at' => now()->toDateTimeString(),
        ];
    }

    public function test_rules_are_defined(): void
    {
        $rules = $this->rules();

        $this->assertArrayHasKey('id', $rules);
        $this->assertArrayHasKey('report_id', $rules);
        $this->assertArrayHasKey('actor_user_id', $rules);
        $this->assertArrayHasKey('from_status', $rules);
        $this->assertArrayHasKey('to_status', $rules);
        $this->assertArrayHasKey('comment', $rules);
        $this->assertArrayHasKey('occurred_at', $rules);
    }

    public function test_id_is_required(): void
    {
        $data = $this->validData();
        unset($data['id']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'id',
            $validator->errors()->toArray()
        );
    }

    public function test_id_must_be_uuid(): void
    {
        $data = $this->validData();
        $data['id'] = 'invalid-id';

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_report_id_is_required(): void
    {
        $data = $this->validData();
        unset($data['report_id']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_actor_user_id_is_required(): void
    {
        $data = $this->validData();
        unset($data['actor_user_id']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_to_status_is_required(): void
    {
        $data = $this->validData();
        unset($data['to_status']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_from_status_is_nullable(): void
    {
        $rules = $this->rules();

        $this->assertContains(
            'nullable',
            $rules['from_status']
        );
    }

    public function test_comment_is_nullable(): void
    {
        $rules = $this->rules();

        $this->assertContains(
            'nullable',
            $rules['comment']
        );
    }

    public function test_occurred_at_is_nullable(): void
    {
        $rules = $this->rules();

        $this->assertContains(
            'nullable',
            $rules['occurred_at']
        );
    }

    public function test_to_status_must_be_string(): void
    {
        $data = $this->validData();
        $data['to_status'] = ['submitted'];

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_occurred_at_must_be_date_when_provided(): void
    {
        $data = $this->validData();
        $data['occurred_at'] = 'not-a-date';

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue(
            $validator->fails()
        );
    }
}
