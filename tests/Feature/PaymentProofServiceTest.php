<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\MonthlyCharge;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Models\User;
use App\Services\PaymentProofService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentProofServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentProofService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PaymentProofService::class);
    }

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

    private function createPaymentProof(
        MonthlyCharge $charge,
        FileAsset $file,
        User $uploadedBy,
        string $status = 'pending'
    ): PaymentProof {
        return PaymentProof::query()->create([
            'id' => (string) Str::uuid(),
            'charge_id' => $charge->id,
            'image_file_id' => $file->id,
            'uploaded_by_user_id' => $uploadedBy->id,
            'submitted_at' => '2026-10-06 10:00:00',
            'status' => $status,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function test_create_creates_payment_proof(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $user = $this->createUser('student');

        $proof = $this->service->create([
            'charge_id' => $charge->id,
            'image_file_id' => $file->id,
            'uploaded_by_user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(
            PaymentProof::class,
            $proof
        );

        $this->assertNotEmpty($proof->id);

        $this->assertDatabaseHas(
            'payment_proofs',
            [
                'id' => $proof->id,
                'charge_id' => $charge->id,
                'image_file_id' => $file->id,
                'uploaded_by_user_id' => $user->id,
                'status' => 'pending',
            ]
        );
    }

    public function test_create_can_accept_explicit_id_and_submitted_at(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $user = $this->createUser('student');

        $id = (string) Str::uuid();

        $proof = $this->service->create([
            'id' => $id,
            'charge_id' => $charge->id,
            'image_file_id' => $file->id,
            'uploaded_by_user_id' => $user->id,
            'submitted_at' => '2026-10-06 11:30:00',
            'status' => 'pending',
        ]);

        $this->assertSame(
            $id,
            $proof->id
        );

        $this->assertSame(
            '2026-10-06 11:30:00',
            $proof->submitted_at->format('Y-m-d H:i:s')
        );
    }

    public function test_find_by_id_returns_payment_proof(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $user = $this->createUser('student');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $user
        );

        $result = $this->service->findById(
            $proof->id
        );

        $this->assertTrue(
            $result->is($proof)
        );
    }

    public function test_find_by_id_throws_exception_when_not_found(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->findById(
            (string) Str::uuid()
        );
    }

    public function test_get_by_charge_returns_payment_proofs_for_charge(): void
    {
        $charge = $this->createCharge();
        $file1 = $this->createFileAsset();
        $file2 = $this->createFileAsset();
        $user = $this->createUser('student');

        $first = $this->createPaymentProof(
            $charge,
            $file1,
            $user
        );

        $second = $this->createPaymentProof(
            $charge,
            $file2,
            $user
        );

        $result = $this->service->getByCharge(
            $charge
        );

        $this->assertCount(
            2,
            $result
        );

        $this->assertTrue(
            $result->contains($first)
        );

        $this->assertTrue(
            $result->contains($second)
        );
    }

    public function test_get_by_charge_does_not_return_other_charge_proofs(): void
    {
        $charge1 = $this->createCharge();

        $student2 = $this->createStudent();
        $branch2 = $this->createBranch();

        $charge2 = MonthlyCharge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student2->id,
            'branch_id' => $branch2->id,
            'charge_month' => '2026-11-01',
            'amount_idr' => 500000,
            'due_on' => null,
        ]);

        $file1 = $this->createFileAsset();
        $file2 = $this->createFileAsset();
        $user = $this->createUser('student');

        $proof1 = $this->createPaymentProof(
            $charge1,
            $file1,
            $user
        );

        $this->createPaymentProof(
            $charge2,
            $file2,
            $user
        );

        $result = $this->service->getByCharge(
            $charge1
        );

        $this->assertCount(
            1,
            $result
        );

        $this->assertTrue(
            $result->first()->is($proof1)
        );
    }

    public function test_review_updates_status_and_reviewer(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->createUser('student');
        $reviewer = $this->createUser('superadmin');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $result = $this->service->review(
            $proof,
            $reviewer,
            'approved'
        );

        $this->assertSame(
            'approved',
            $result->status
        );

        $this->assertSame(
            $reviewer->id,
            $result->reviewed_by_user_id
        );

        $this->assertNotNull(
            $result->reviewed_at
        );

        $this->assertNull(
            $result->rejection_reason
        );
    }

    public function test_review_can_store_rejection_reason(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->createUser('student');
        $reviewer = $this->createUser('superadmin');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $result = $this->service->review(
            $proof,
            $reviewer,
            'rejected',
            'Bukti pembayaran tidak dapat dibaca.'
        );

        $this->assertSame(
            'rejected',
            $result->status
        );

        $this->assertSame(
            'Bukti pembayaran tidak dapat dibaca.',
            $result->rejection_reason
        );

        $this->assertSame(
            $reviewer->id,
            $result->reviewed_by_user_id
        );

        $this->assertNotNull(
            $result->reviewed_at
        );
    }
}
