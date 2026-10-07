<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\MonthlyCharge;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentProofHttpTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode = 'superadmin',
        bool $isActive = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);
    }

    private function actingAsSuperadmin(): User
    {
        $user = $this->makeUser('superadmin');

        $this->actingAs($user);

        return $user;
    }

    private function createStudent(): Student
    {
        $user = $this->makeUser('student');

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

    public function test_store_payment_proof_endpoint(): void
    {
        $this->actingAsSuperadmin();

        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $user = $this->makeUser('student');

        $response = $this->postJson(
            route('superadmin.payment-proofs.store'),
            [
                'charge_id' => $charge->id,
                'image_file_id' => $file->id,
                'uploaded_by_user_id' => $user->id,
                'status' => 'pending',
            ]
        );

        $response->assertStatus(201);

        $response->assertJson([
            'charge_id' => $charge->id,
            'image_file_id' => $file->id,
            'uploaded_by_user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas(
            'payment_proofs',
            [
                'charge_id' => $charge->id,
                'image_file_id' => $file->id,
                'uploaded_by_user_id' => $user->id,
                'status' => 'pending',
            ]
        );
    }

    public function test_store_payment_proof_requires_required_fields(): void
    {
        $this->actingAsSuperadmin();

        $response = $this->postJson(
            route('superadmin.payment-proofs.store'),
            []
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'charge_id',
            'image_file_id',
            'uploaded_by_user_id',
            'status',
        ]);
    }

    public function test_show_payment_proof_endpoint(): void
    {
        $this->actingAsSuperadmin();

        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $user = $this->makeUser('student');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $user
        );

        $response = $this->getJson(
            route('superadmin.payment-proofs.show', $proof)
        );

        $response->assertOk();

        $response->assertJson([
            'id' => $proof->id,
            'charge_id' => $charge->id,
            'image_file_id' => $file->id,
            'uploaded_by_user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_show_payment_proof_returns_404_when_not_found(): void
    {
        $this->actingAsSuperadmin();

        $response = $this->getJson(
            route('superadmin.payment-proofs.show', Str::uuid())
        );

        $response->assertNotFound();
    }

    public function test_get_payment_proofs_by_charge_endpoint(): void
    {
        $this->actingAsSuperadmin();

        $charge = $this->createCharge();
        $file1 = $this->createFileAsset();
        $file2 = $this->createFileAsset();
        $user = $this->makeUser('student');

        $proof1 = $this->createPaymentProof(
            $charge,
            $file1,
            $user
        );

        $proof2 = $this->createPaymentProof(
            $charge,
            $file2,
            $user
        );

        $response = $this->getJson(
            route('superadmin.monthly-charges.payment-proofs', $charge)
        );

        $response->assertOk();

        $response->assertJsonCount(
            2
        );

        $response->assertJsonFragment([
            'id' => $proof1->id,
        ]);

        $response->assertJsonFragment([
            'id' => $proof2->id,
        ]);
    }

    public function test_get_payment_proofs_by_charge_returns_404_for_unknown_charge(): void
    {
        $this->actingAsSuperadmin();

        $response = $this->getJson(
            route(
                'superadmin.monthly-charges.payment-proofs',
                Str::uuid()
            )
        );

        $response->assertNotFound();
    }

    public function test_review_payment_proof_endpoint(): void
    {
        $reviewer = $this->actingAsSuperadmin();

        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->makeUser('student');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $response = $this->patchJson(
            route('superadmin.payment-proofs.review', $proof),
            [
                'status' => 'approved',
                'reviewed_by_user_id' => $reviewer->id,
            ]
        );

        $response->assertOk();

        $response->assertJson([
            'id' => $proof->id,
            'status' => 'approved',
            'reviewed_by_user_id' => $reviewer->id,
        ]);

        $this->assertDatabaseHas(
            'payment_proofs',
            [
                'id' => $proof->id,
                'status' => 'approved',
                'reviewed_by_user_id' => $reviewer->id,
            ]
        );
    }

    public function test_review_payment_proof_can_store_rejection_reason(): void
    {
        $reviewer = $this->actingAsSuperadmin();

        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->makeUser('student');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $response = $this->patchJson(
            route('superadmin.payment-proofs.review', $proof),
            [
                'status' => 'rejected',
                'reviewed_by_user_id' => $reviewer->id,
                'rejection_reason' => 'Bukti pembayaran tidak dapat dibaca.',
            ]
        );

        $response->assertOk();

        $response->assertJson([
            'id' => $proof->id,
            'status' => 'rejected',
            'reviewed_by_user_id' => $reviewer->id,
            'rejection_reason' => 'Bukti pembayaran tidak dapat dibaca.',
        ]);
    }

    public function test_review_payment_proof_requires_status_and_reviewer(): void
    {
        $this->actingAsSuperadmin();

        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $uploadedBy = $this->makeUser('student');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $uploadedBy
        );

        $response = $this->patchJson(
            route('superadmin.payment-proofs.review', $proof),
            []
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'status',
            'reviewed_by_user_id',
        ]);
    }
}
