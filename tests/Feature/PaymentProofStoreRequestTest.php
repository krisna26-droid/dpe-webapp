<?php

namespace Tests\Feature;

use App\Http\Requests\PaymentProofStoreRequest;
use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\MonthlyCharge;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PaymentProofStoreRequestTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(
        string $role = 'student'
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

    private function createStudent(): Student
    {
        $user = $this->createUser('student');

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $user->id,
            'full_name' => 'Test Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 6',
            'began_on' => '2026-01-01',
            'status' => 'active',
            'special_notes_internal' => null,
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

    private function createCharge(): MonthlyCharge
    {
        $student = $this->createStudent();
        $branch = $this->createBranch();

        return MonthlyCharge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'charge_month' => '2026-10-01',
            'amount_idr' => 500000,
            'due_on' => null,
        ]);
    }

    private function createFileAsset(): FileAsset
    {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'payment-proofs/' . Str::uuid() . '.jpg',
            'original_name' => 'payment-proof.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'sha256_hex' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
        ]);
    }

    private function validData(): array
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $user = $this->createUser('student');

        return [
            'charge_id' => $charge->id,
            'image_file_id' => $file->id,
            'uploaded_by_user_id' => $user->id,
            'submitted_at' => '2026-10-06 10:00:00',
            'status' => 'pending',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ];
    }

    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make(
            $data,
            (new PaymentProofStoreRequest())->rules()
        );
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

    public function test_charge_id_is_required(): void
    {
        $data = $this->validData();

        unset($data['charge_id']);

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );

        $this->assertArrayHasKey(
            'charge_id',
            $validator->errors()->toArray()
        );
    }

    public function test_image_file_id_is_required(): void
    {
        $data = $this->validData();

        unset($data['image_file_id']);

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_uploaded_by_user_id_is_required(): void
    {
        $data = $this->validData();

        unset($data['uploaded_by_user_id']);

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_status_is_required(): void
    {
        $data = $this->validData();

        unset($data['status']);

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_status_must_be_string_and_max_255_characters(): void
    {
        $data = $this->validData();

        $data['status'] = str_repeat('a', 256);

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_review_fields_are_nullable(): void
    {
        $data = $this->validData();

        $data['reviewed_by_user_id'] = null;
        $data['reviewed_at'] = null;
        $data['rejection_reason'] = null;

        $validator = $this->validate($data);

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_submitted_at_is_nullable(): void
    {
        $data = $this->validData();

        $data['submitted_at'] = null;

        $validator = $this->validate($data);

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_foreign_keys_must_exist(): void
    {
        $data = $this->validData();

        $data['charge_id'] = (string) Str::uuid();
        $data['image_file_id'] = (string) Str::uuid();
        $data['uploaded_by_user_id'] = (string) Str::uuid();

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey(
            'charge_id',
            $errors
        );

        $this->assertArrayHasKey(
            'image_file_id',
            $errors
        );

        $this->assertArrayHasKey(
            'uploaded_by_user_id',
            $errors
        );
    }

    public function test_reviewed_by_user_id_must_exist_when_provided(): void
    {
        $data = $this->validData();

        $data['reviewed_by_user_id'] = (string) Str::uuid();

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_reviewed_at_must_be_a_valid_date(): void
    {
        $data = $this->validData();

        $data['reviewed_at'] = 'not-a-date';

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_submitted_at_must_be_a_valid_date_when_provided(): void
    {
        $data = $this->validData();

        $data['submitted_at'] = 'not-a-date';

        $validator = $this->validate($data);

        $this->assertTrue(
            $validator->fails()
        );
    }
}
