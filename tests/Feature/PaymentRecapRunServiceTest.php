<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\PaymentRecapRun;
use App\Models\User;
use App\Services\PaymentRecapRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentRecapRunServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentRecapRunService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PaymentRecapRunService::class);
    }

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

    public function test_create_creates_payment_recap_run(): void
    {
        $branch = $this->createBranch();

        $result = $this->service->create([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
        ]);

        $this->assertInstanceOf(
            PaymentRecapRun::class,
            $result
        );

        $this->assertDatabaseHas(
            'payment_recap_runs',
            [
                'id' => $result->id,
                'branch_id' => $branch->id,
                'status' => 'scheduled',
            ]
        );
    }

    public function test_create_generates_uuid_when_id_is_not_provided(): void
    {
        $branch = $this->createBranch();

        $result = $this->service->create([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
        ]);

        $this->assertTrue(
            Str::isUuid($result->id)
        );
    }

    public function test_create_accepts_explicit_id(): void
    {
        $branch = $this->createBranch();

        $id = (string) Str::uuid();

        $result = $this->service->create([
            'id' => $id,
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
        ]);

        $this->assertSame(
            $id,
            $result->id
        );
    }

    public function test_find_by_id_returns_payment_recap_run(): void
    {
        $branch = $this->createBranch();

        $run = $this->createRecapRun($branch);

        $result = $this->service->findById(
            $run->id
        );

        $this->assertTrue(
            $result->is($run)
        );
    }

    public function test_find_by_id_throws_when_not_found(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->findById(
            (string) Str::uuid()
        );
    }

    public function test_get_by_branch_returns_runs_for_branch(): void
    {
        $branch = $this->createBranch();

        $this->createRecapRun(
            $branch,
            '2026-09-01'
        );

        $this->createRecapRun(
            $branch,
            '2026-10-01'
        );

        $result = $this->service->getByBranch(
            $branch
        );

        $this->assertCount(
            2,
            $result
        );
    }

    public function test_get_by_branch_isolated_from_other_branch(): void
    {
        $branchA = $this->createBranch();
        $branchB = $this->createBranch();

        $this->createRecapRun(
            $branchA,
            '2026-10-01'
        );

        $this->createRecapRun(
            $branchB,
            '2026-10-01'
        );

        $result = $this->service->getByBranch(
            $branchA
        );

        $this->assertCount(
            1,
            $result
        );

        $this->assertSame(
            $branchA->id,
            $result->first()->branch_id
        );
    }

    public function test_get_by_branch_orders_by_recap_month_descending(): void
    {
        $branch = $this->createBranch();

        $older = $this->createRecapRun(
            $branch,
            '2026-09-01'
        );

        $newer = $this->createRecapRun(
            $branch,
            '2026-10-01'
        );

        $result = $this->service->getByBranch(
            $branch
        );

        $this->assertSame(
            $newer->id,
            $result->first()->id
        );

        $this->assertSame(
            $older->id,
            $result->last()->id
        );
    }

    public function test_mark_generated_sets_generation_information(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();

        $run = $this->createRecapRun($branch);

        $result = $this->service->markGenerated(
            $run,
            $user
        );

        $this->assertSame(
            'generated',
            $result->status
        );

        $this->assertNotNull(
            $result->generated_at
        );

        $this->assertSame(
            $user->id,
            $result->generated_by_user_id
        );

        $this->assertNull(
            $result->export_file_id
        );
    }

    public function test_mark_generated_can_attach_export_file(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();
        $file = $this->createFileAsset();

        $run = $this->createRecapRun($branch);

        $result = $this->service->markGenerated(
            $run,
            $user,
            $file->id
        );

        $this->assertSame(
            'generated',
            $result->status
        );

        $this->assertSame(
            $file->id,
            $result->export_file_id
        );

        $this->assertSame(
            $user->id,
            $result->generated_by_user_id
        );

        $this->assertNotNull(
            $result->generated_at
        );
    }

    public function test_create_respects_unique_branch_and_month_constraint(): void
    {
        $branch = $this->createBranch();

        $this->createRecapRun(
            $branch,
            '2026-10-01'
        );

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        $this->service->create([
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-26',
            'status' => 'scheduled',
        ]);
    }
}
