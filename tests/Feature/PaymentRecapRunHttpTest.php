<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\PaymentRecapRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentRecapRunHttpTest extends TestCase
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

    private function createRecapRun(
        Branch $branch,
        string $month = '2026-10-01'
    ): PaymentRecapRun {
        return PaymentRecapRun::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'recap_month' => $month,
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
            'generated_at' => null,
            'generated_by_user_id' => null,
            'export_file_id' => null,
        ]);
    }

    public function test_store_creates_payment_recap_run(): void
    {
        $this->actingAsSuperadmin();

        $branch = $this->createBranch();

        $response = $this->postJson(
            route('superadmin.payment-recap-runs.store'),
            [
                'branch_id' => $branch->id,
                'recap_month' => '2026-10-01',
                'scheduled_on' => '2026-10-25',
                'status' => 'scheduled',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('branch_id', $branch->id)
            ->assertJsonPath('status', 'scheduled');

        $this->assertDatabaseHas('payment_recap_runs', [
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01 00:00:00',
            'scheduled_on' => '2026-10-25 00:00:00',
            'status' => 'scheduled',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsSuperadmin();

        $response = $this->postJson(
            route('superadmin.payment-recap-runs.store'),
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'branch_id',
                'recap_month',
                'scheduled_on',
                'status',
            ]);
    }

    public function test_store_rejects_non_existing_branch(): void
    {
        $this->actingAsSuperadmin();

        $response = $this->postJson(
            route('superadmin.payment-recap-runs.store'),
            [
                'branch_id' => (string) Str::uuid(),
                'recap_month' => '2026-10-01',
                'scheduled_on' => '2026-10-25',
                'status' => 'scheduled',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'branch_id',
            ]);
    }

    public function test_show_returns_payment_recap_run(): void
    {
        $this->actingAsSuperadmin();

        $branch = $this->createBranch();
        $paymentRecapRun = $this->createRecapRun($branch);

        $response = $this->getJson(
            route(
                'superadmin.payment-recap-runs.show',
                $paymentRecapRun
            )
        );

        $response
            ->assertOk()
            ->assertJsonPath('id', $paymentRecapRun->id)
            ->assertJsonPath('branch_id', $branch->id)
            ->assertJsonPath('status', 'scheduled');
    }

    public function test_show_returns_404_for_non_existing_payment_recap_run(): void
    {
        $this->actingAsSuperadmin();

        $response = $this->getJson(
            route(
                'superadmin.payment-recap-runs.show',
                (string) Str::uuid()
            )
        );

        $response->assertNotFound();
    }

    public function test_by_branch_returns_payment_recap_runs_for_branch(): void
    {
        $this->actingAsSuperadmin();

        $branch = $this->createBranch();

        $older = $this->createRecapRun(
            $branch,
            '2026-09-01'
        );

        $newer = $this->createRecapRun(
            $branch,
            '2026-10-01'
        );

        $response = $this->getJson(
            route(
                'superadmin.branches.payment-recap-runs',
                $branch
            )
        );

        $response
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $newer->id)
            ->assertJsonPath('1.id', $older->id);
    }

    public function test_by_branch_does_not_return_runs_from_other_branch(): void
    {
        $this->actingAsSuperadmin();

        $branchA = $this->createBranch();
        $branchB = $this->createBranch();

        $runA = $this->createRecapRun($branchA);

        $runB = $this->createRecapRun(
            $branchB,
            '2026-09-01'
        );

        $response = $this->getJson(
            route(
                'superadmin.branches.payment-recap-runs',
                $branchA
            )
        );

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $runA->id)
            ->assertJsonMissing([
                'id' => $runB->id,
            ]);
    }

    public function test_by_branch_returns_404_for_non_existing_branch(): void
    {
        $this->actingAsSuperadmin();

        $response = $this->getJson(
            route(
                'superadmin.branches.payment-recap-runs',
                (string) Str::uuid()
            )
        );

        $response->assertNotFound();
    }

    public function test_generate_marks_payment_recap_run_as_generated(): void
    {
        $superadmin = $this->actingAsSuperadmin();

        $branch = $this->createBranch();
        $paymentRecapRun = $this->createRecapRun($branch);

        $response = $this->patchJson(
            route(
                'superadmin.payment-recap-runs.generate',
                $paymentRecapRun
            ),
            [
                'generated_by_user_id' => $superadmin->id,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('id', $paymentRecapRun->id)
            ->assertJsonPath('status', 'generated')
            ->assertJsonPath(
                'generated_by_user_id',
                $superadmin->id
            );

        $this->assertDatabaseHas('payment_recap_runs', [
            'id' => $paymentRecapRun->id,
            'status' => 'generated',
            'generated_by_user_id' => $superadmin->id,
        ]);
    }

    public function test_generate_accepts_export_file(): void
    {
        $superadmin = $this->actingAsSuperadmin();

        $branch = $this->createBranch();
        $fileAsset = $this->createFileAsset();

        $paymentRecapRun = $this->createRecapRun($branch);

        $response = $this->patchJson(
            route(
                'superadmin.payment-recap-runs.generate',
                $paymentRecapRun
            ),
            [
                'generated_by_user_id' => $superadmin->id,
                'export_file_id' => $fileAsset->id,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'generated_by_user_id',
                $superadmin->id
            )
            ->assertJsonPath(
                'export_file_id',
                $fileAsset->id
            )
            ->assertJsonPath(
                'status',
                'generated'
            );

        $this->assertDatabaseHas('payment_recap_runs', [
            'id' => $paymentRecapRun->id,
            'export_file_id' => $fileAsset->id,
        ]);
    }

    public function test_generate_validates_generated_by_user(): void
    {
        $this->actingAsSuperadmin();

        $branch = $this->createBranch();
        $paymentRecapRun = $this->createRecapRun($branch);

        $response = $this->patchJson(
            route(
                'superadmin.payment-recap-runs.generate',
                $paymentRecapRun
            ),
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'generated_by_user_id',
            ]);
    }

    public function test_generate_rejects_non_existing_export_file(): void
    {
        $superadmin = $this->actingAsSuperadmin();

        $branch = $this->createBranch();
        $paymentRecapRun = $this->createRecapRun($branch);

        $response = $this->patchJson(
            route(
                'superadmin.payment-recap-runs.generate',
                $paymentRecapRun
            ),
            [
                'generated_by_user_id' => $superadmin->id,
                'export_file_id' => (string) Str::uuid(),
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'export_file_id',
            ]);
    }
}
