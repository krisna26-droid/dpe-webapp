<?php

namespace Tests\Feature;

use App\Http\Requests\PaymentRecapRunStoreRequest;
use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentRecapRunStoreRequestTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(
        string $role = 'superadmin'
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $role . '_' . Str::random(8),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => password_hash(
                'password',
                PASSWORD_BCRYPT
            ),
            'full_name' => 'Test ' . ucfirst($role),
            'role_code' => $role,
            'is_active' => true,
        ]);
    }

    private function createBranch(): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => 'Test Address',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ]);
    }

    private function createFileAsset(): FileAsset
    {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'payment-recaps/' . Str::uuid() . '.pdf',
            'original_name' => 'payment-recap.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'sha256_hex' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
        ]);
    }

    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $request = new PaymentRecapRunStoreRequest();

        return Validator::make(
            $data,
            $request->rules()
        );
    }

    public function test_valid_payload_passes(): void
    {
        $branch = $this->createBranch();

        $validator = $this->validate([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
        ]);

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_branch_id_is_required(): void
    {
        $validator = $this->validate([
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
        ]);

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'branch_id',
            $validator->errors()->toArray()
        );
    }

    public function test_recap_month_is_required(): void
    {
        $branch = $this->createBranch();

        $validator = $this->validate([
            'branch_id' => $branch->id,
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
        ]);

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'recap_month',
            $validator->errors()->toArray()
        );
    }

    public function test_scheduled_on_is_required(): void
    {
        $branch = $this->createBranch();

        $validator = $this->validate([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'status' => 'scheduled',
        ]);

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'scheduled_on',
            $validator->errors()->toArray()
        );
    }

    public function test_status_is_required(): void
    {
        $branch = $this->createBranch();

        $validator = $this->validate([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
        ]);

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'status',
            $validator->errors()->toArray()
        );
    }

    public function test_branch_id_must_be_uuid(): void
    {
        $validator = $this->validate([
            'branch_id' => 'invalid-uuid',
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
        ]);

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_branch_id_must_exist(): void
    {
        $validator = $this->validate([
            'branch_id' => (string) Str::uuid(),
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
        ]);

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_generated_by_user_id_is_nullable(): void
    {
        $branch = $this->createBranch();

        $validator = $this->validate([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
            'generated_by_user_id' => null,
        ]);

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_generated_by_user_id_must_exist_when_provided(): void
    {
        $branch = $this->createBranch();

        $validator = $this->validate([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
            'generated_by_user_id' => (string) Str::uuid(),
        ]);

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_export_file_id_is_nullable(): void
    {
        $branch = $this->createBranch();

        $validator = $this->validate([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
            'export_file_id' => null,
        ]);

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_export_file_id_must_exist_when_provided(): void
    {
        $branch = $this->createBranch();

        $validator = $this->validate([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'generated',
            'export_file_id' => (string) Str::uuid(),
        ]);

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_optional_generation_fields_accept_valid_values(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();
        $file = $this->createFileAsset();

        $validator = $this->validate([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'generated',
            'generated_at' => '2026-10-25 10:00:00',
            'generated_by_user_id' => $user->id,
            'export_file_id' => $file->id,
        ]);

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_status_is_not_restricted_to_assumed_values(): void
    {
        $branch = $this->createBranch();

        $validator = $this->validate([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'custom-status-from-schema',
        ]);

        $this->assertFalse(
            $validator->fails()
        );
    }
}
