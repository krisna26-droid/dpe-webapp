<?php

namespace Tests\Feature;

use App\Http\Controllers\PaymentProofController;
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
use App\Http\Requests\PaymentProofStoreRequest;

class PaymentProofControllerTest extends TestCase
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

    public function test_controller_store_creates_payment_proof(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $user = $this->createUser('student');

        $controller = app(PaymentProofController::class);

        $request = PaymentProofStoreRequest::create(
            '/payment-proofs',
            'POST',
            [
                'charge_id' => $charge->id,
                'image_file_id' => $file->id,
                'uploaded_by_user_id' => $user->id,
                'status' => 'pending',
            ]
        );

        $request->setContainer(
            app()
        );

        $request->setRedirector(
            app('redirect')
        );

        $request->validateResolved();

        $response = $controller->store(
            $request
        );

        $this->assertSame(
            201,
            $response->getStatusCode()
        );

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

    public function test_controller_show_returns_payment_proof(): void
    {
        $charge = $this->createCharge();
        $file = $this->createFileAsset();
        $user = $this->createUser('student');

        $proof = $this->createPaymentProof(
            $charge,
            $file,
            $user
        );

        $controller = app(PaymentProofController::class);

        $response = $controller->show(
            $proof->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            $proof->id,
            $response->getData()->id
        );
    }

    public function test_controller_by_charge_returns_charge_proofs(): void
    {
        $charge = $this->createCharge();
        $file1 = $this->createFileAsset();
        $file2 = $this->createFileAsset();
        $user = $this->createUser('student');

        $this->createPaymentProof(
            $charge,
            $file1,
            $user
        );

        $this->createPaymentProof(
            $charge,
            $file2,
            $user
        );

        $controller = app(PaymentProofController::class);

        $response = $controller->byCharge(
            $charge->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertCount(
            2,
            $response->getData()
        );
    }

    public function test_controller_review_updates_payment_proof(): void
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

        $controller = app(PaymentProofController::class);

        $request = \Illuminate\Http\Request::create(
            '/payment-proofs/' . $proof->id . '/review',
            'PATCH',
            [
                'status' => 'approved',
                'reviewed_by_user_id' => $reviewer->id,
            ]
        );

        $response = $controller->review(
            $request,
            $proof->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertDatabaseHas(
            'payment_proofs',
            [
                'id' => $proof->id,
                'status' => 'approved',
                'reviewed_by_user_id' => $reviewer->id,
            ]
        );
    }

    public function test_controller_review_can_store_rejection_reason(): void
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

        $controller = app(PaymentProofController::class);

        $request = \Illuminate\Http\Request::create(
            '/payment-proofs/' . $proof->id . '/review',
            'PATCH',
            [
                'status' => 'rejected',
                'reviewed_by_user_id' => $reviewer->id,
                'rejection_reason' => 'Bukti tidak dapat dibaca.',
            ]
        );

        $response = $controller->review(
            $request,
            $proof->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertDatabaseHas(
            'payment_proofs',
            [
                'id' => $proof->id,
                'status' => 'rejected',
                'reviewed_by_user_id' => $reviewer->id,
                'rejection_reason' => 'Bukti tidak dapat dibaca.',
            ]
        );
    }

    public function test_controller_uses_payment_proof_service(): void
    {
        $service = $this->mock(
            PaymentProofService::class
        );

        $service->shouldReceive('findById')
            ->once()
            ->with('proof-id')
            ->andReturn(
                new PaymentProof([
                    'id' => 'proof-id',
                    'status' => 'pending',
                ])
            );

        $controller = new PaymentProofController(
            $service
        );

        $response = $controller->show(
            'proof-id'
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            'proof-id',
            $response->getData()->id
        );
    }
}
