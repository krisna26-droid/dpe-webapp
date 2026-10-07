<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\MonthlyCharge;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentProofModelTest extends TestCase
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
            'sha256_hex' => hash('sha256', Str::uuid()->toString()),
        ]);
    }

    private function createPaymentProof(
        MonthlyCharge $charge,
        FileAsset $file,
        User $uploadedBy,
        ?User $reviewedBy = null
    ): PaymentProof {
        return PaymentProof::query()->create([
            'id' => (string) Str::uuid(),
            'charge_id' => $charge->id,
            'image_file_id' => $file->id,
            'uploaded_by_user_id' => $uploadedBy->id,
            'submitted_at' => '2026-10-06 10:00:00',
            'status' => 'pending',
            'reviewed_by_user_id' => $reviewedBy?->id,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function test_payment_proof_uses_expected_table(): void
    {
        $proof = new PaymentProof();

        $this->assertSame(
            'payment_proofs',
            $proof->getTable()
        );
    }

    public function test_payment_proof_does_not_use_incrementing_id(): void
    {
        $proof = new PaymentProof();

        $this->assertFalse(
            $proof->getIncrementing()
        );

        $this->assertSame(
            'string',
            $proof->getKeyType()
        );
    }

    public function test_payment_proof_has_expected_fillable_fields(): void
    {
        $proof = new PaymentProof();

        $this->assertSame(
            [
                'id',
                'charge_id',
                'image_file_id',
                'uploaded_by_user_id',
                'submitted_at',
                'status',
                'reviewed_by_user_id',
                'reviewed_at',
                'rejection_reason',
            ],
            $proof->getFillable()
        );
    }

    public function test_payment_proof_has_no_updated_at_column(): void
    {
        $proof = new PaymentProof();

        $this->assertNull(
            $proof->getUpdatedAtColumn()
        );

        $this->assertSame(
            'submitted_at',
            $proof->getCreatedAtColumn()
        );
    }

    public function test_payment_proof_casts_datetime_fields(): void
    {
        $proof = new PaymentProof();

        $this->assertSame(
            'datetime',
            $proof->getCasts()['submitted_at']
        );

        $this->assertSame(
            'datetime',
            $proof->getCasts()['reviewed_at']
        );
    }

    public function test_payment_proof_belongs_to_charge(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->createUser('student');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $this->assertTrue(
            $proof->charge->is($charge)
        );
    }

    public function test_payment_proof_belongs_to_image_file(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->createUser('student');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $this->assertTrue(
            $proof->imageFile->is($file)
        );
    }

    public function test_payment_proof_belongs_to_uploaded_by_user(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->createUser('student');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $this->assertTrue(
            $proof->uploadedBy->is($uploadedBy)
        );
    }

    public function test_payment_proof_can_have_nullable_reviewed_by_user(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->createUser('student');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $this->assertNull(
            $proof->reviewed_by_user_id
        );

        $this->assertNull(
            $proof->reviewedBy
        );
    }

    public function test_payment_proof_can_belong_to_reviewer(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->createUser('student');
        $reviewedBy = $this->createUser('superadmin');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy,
            $reviewedBy
        );

        $this->assertTrue(
            $proof->reviewedBy->is($reviewedBy)
        );
    }

    public function test_monthly_charge_has_many_payment_proofs(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->createUser('student');

        $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $this->assertCount(
            1,
            $charge->paymentProofs
        );
    }

    public function test_file_asset_has_many_payment_proofs(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->createUser('student');

        $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $this->assertCount(
            1,
            $file->paymentProofs
        );
    }

    public function test_user_has_uploaded_payment_proofs(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->createUser('student');

        $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $this->assertCount(
            1,
            $uploadedBy->uploadedPaymentProofs
        );
    }

    public function test_user_has_reviewed_payment_proofs(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->createUser('student');
        $reviewedBy = $this->createUser('superadmin');

        $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy,
            $reviewedBy
        );

        $this->assertCount(
            1,
            $reviewedBy->reviewedPaymentProofs
        );
    }
}
