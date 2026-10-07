<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\ReportShareAttemptStoreRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ReportShareAttemptStoreRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('quarterly_report_files', function (Blueprint $table) {
            $table->string('id', 36)->primary();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->string('id', 36)->primary();
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->string('id', 36)->primary();
        });

        DB::table('quarterly_report_files')->insert([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        DB::table('teachers')->insert([
            'id' => '550e8400-e29b-41d4-a716-446655440001',
        ]);

        DB::table('guardians')->insert([
            'id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);
    }

    private function validator(array $data)
    {
        $request = new ReportShareAttemptStoreRequest();

        return Validator::make(
            $data,
            $request->rules()
        );
    }

    private function validData(): array
    {
        return [
            'quarterly_report_file_id' =>
                '550e8400-e29b-41d4-a716-446655440000',

            'teacher_id' =>
                '550e8400-e29b-41d4-a716-446655440001',

            'guardian_id' =>
                '550e8400-e29b-41d4-a716-446655440002',

            'recipient_phone_snapshot' =>
                '081234567890',

            'status' =>
                'opened',

            'opened_at' =>
                '2026-10-06 10:00:00',

            'confirmed_at' =>
                null,

            'teacher_note' =>
                'Report telah dibagikan.',
        ];
    }

    public function test_authorize_returns_true(): void
    {
        $request = new ReportShareAttemptStoreRequest();

        $this->assertTrue(
            $request->authorize()
        );
    }

    public function test_valid_data_passes_basic_validation(): void
    {
        $validator = $this->validator(
            $this->validData()
        );

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_quarterly_report_file_id_is_required(): void
    {
        $data = $this->validData();

        unset($data['quarterly_report_file_id']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'quarterly_report_file_id',
            $validator->errors()->toArray()
        );
    }

    public function test_teacher_id_is_required(): void
    {
        $data = $this->validData();

        unset($data['teacher_id']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'teacher_id',
            $validator->errors()->toArray()
        );
    }

    public function test_guardian_id_is_required(): void
    {
        $data = $this->validData();

        unset($data['guardian_id']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'guardian_id',
            $validator->errors()->toArray()
        );
    }

    public function test_required_fields_must_be_uuid(): void
    {
        $data = $this->validData();

        $data['quarterly_report_file_id'] = 'not-a-uuid';
        $data['teacher_id'] = 'not-a-uuid';
        $data['guardian_id'] = 'not-a-uuid';

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey(
            'quarterly_report_file_id',
            $errors
        );

        $this->assertArrayHasKey(
            'teacher_id',
            $errors
        );

        $this->assertArrayHasKey(
            'guardian_id',
            $errors
        );
    }

    public function test_recipient_phone_snapshot_is_required(): void
    {
        $data = $this->validData();

        unset($data['recipient_phone_snapshot']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'recipient_phone_snapshot',
            $validator->errors()->toArray()
        );
    }

    public function test_status_is_required(): void
    {
        $data = $this->validData();

        unset($data['status']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'status',
            $validator->errors()->toArray()
        );
    }

    public function test_opened_at_is_required(): void
    {
        $data = $this->validData();

        unset($data['opened_at']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'opened_at',
            $validator->errors()->toArray()
        );
    }

    public function test_confirmed_at_and_teacher_note_are_nullable(): void
    {
        $data = $this->validData();

        $data['confirmed_at'] = null;
        $data['teacher_note'] = null;

        $validator = $this->validator($data);

        $this->assertFalse(
            $validator->fails()
        );
    }
}