<?php

namespace Tests\Feature;

use App\Http\Requests\QuarterlyReportFileStoreRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class QuarterlyReportFileStoreRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('report_cycles', function (Blueprint $table) {
            $table->string('id', 36)->primary();
        });

        Schema::create('file_assets', function (Blueprint $table) {
            $table->string('id', 36)->primary();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 36)->primary();
        });

        DB::table('report_cycles')->insert([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        DB::table('file_assets')->insert([
            'id' => '550e8400-e29b-41d4-a716-446655440001',
        ]);

        DB::table('users')->insert([
            'id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);
    }

    private function validate(array $data): array
    {
        $request = new QuarterlyReportFileStoreRequest();

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        return [
            'passes' => $validator->passes(),
            'errors' => $validator->errors()->toArray(),
        ];
    }

    public function test_it_accepts_valid_data(): void
    {
        $result = $this->validate([
            'cycle_id' => '550e8400-e29b-41d4-a716-446655440000',
            'file_id' => '550e8400-e29b-41d4-a716-446655440001',
            'version_number' => 1,
            'generated_by_user_id' => '550e8400-e29b-41d4-a716-446655440002',
            'generated_at' => '2026-10-01 10:00:00',
            'source_snapshot_hash' => 'snapshot-hash',
        ]);

        $this->assertTrue($result['passes']);
    }

    public function test_it_accepts_nullable_optional_fields(): void
    {
        $result = $this->validate([
            'cycle_id' => '550e8400-e29b-41d4-a716-446655440000',
            'file_id' => '550e8400-e29b-41d4-a716-446655440001',
            'version_number' => 1,
            'generated_by_user_id' => '550e8400-e29b-41d4-a716-446655440002',
            'generated_at' => null,
            'source_snapshot_hash' => null,
        ]);

        $this->assertTrue($result['passes']);
    }

    public function test_cycle_id_is_required(): void
    {
        $result = $this->validate([
            'file_id' => '550e8400-e29b-41d4-a716-446655440001',
            'version_number' => 1,
            'generated_by_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey('cycle_id', $result['errors']);
    }

    public function test_file_id_is_required(): void
    {
        $result = $this->validate([
            'cycle_id' => '550e8400-e29b-41d4-a716-446655440000',
            'version_number' => 1,
            'generated_by_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey('file_id', $result['errors']);
    }

    public function test_version_number_must_be_positive(): void
    {
        $result = $this->validate([
            'cycle_id' => '550e8400-e29b-41d4-a716-446655440000',
            'file_id' => '550e8400-e29b-41d4-a716-446655440001',
            'version_number' => 0,
            'generated_by_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'version_number',
            $result['errors']
        );
    }

    public function test_version_number_must_be_integer(): void
    {
        $result = $this->validate([
            'cycle_id' => '550e8400-e29b-41d4-a716-446655440000',
            'file_id' => '550e8400-e29b-41d4-a716-446655440001',
            'version_number' => 'one',
            'generated_by_user_id' => '550e8400-e29b-41d4-a716-446655440002',
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'version_number',
            $result['errors']
        );
    }

    public function test_generated_by_user_id_is_required(): void
    {
        $result = $this->validate([
            'cycle_id' => '550e8400-e29b-41d4-a716-446655440000',
            'file_id' => '550e8400-e29b-41d4-a716-446655440001',
            'version_number' => 1,
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'generated_by_user_id',
            $result['errors']
        );
    }

    public function test_generated_at_must_be_date_when_provided(): void
    {
        $result = $this->validate([
            'cycle_id' => '550e8400-e29b-41d4-a716-446655440000',
            'file_id' => '550e8400-e29b-41d4-a716-446655440001',
            'version_number' => 1,
            'generated_by_user_id' => '550e8400-e29b-41d4-a716-446655440002',
            'generated_at' => 'not-a-date',
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'generated_at',
            $result['errors']
        );
    }

    public function test_source_snapshot_hash_must_be_string(): void
    {
        $result = $this->validate([
            'cycle_id' => '550e8400-e29b-41d4-a716-446655440000',
            'file_id' => '550e8400-e29b-41d4-a716-446655440001',
            'version_number' => 1,
            'generated_by_user_id' => '550e8400-e29b-41d4-a716-446655440002',
            'source_snapshot_hash' => ['invalid'],
        ]);

        $this->assertFalse($result['passes']);
        $this->assertArrayHasKey(
            'source_snapshot_hash',
            $result['errors']
        );
    }
}
