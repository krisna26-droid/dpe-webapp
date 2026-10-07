<?php

namespace Tests\Feature;

use App\Http\Controllers\PaymentRecapRunController;
use App\Http\Requests\PaymentRecapRunStoreRequest;
use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\PaymentRecapRun;
use App\Models\User;
use App\Services\PaymentRecapRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentRecapRunControllerTest extends TestCase
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

    private function createRecapRun(
        Branch $branch
    ): PaymentRecapRun {
        return PaymentRecapRun::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
            'generated_at' => null,
            'generated_by_user_id' => null,
            'export_file_id' => null,
        ]);
    }

    public function test_controller_uses_payment_recap_run_service(): void
    {
        $controller = app(PaymentRecapRunController::class);

        $reflection = new \ReflectionClass($controller);

        $this->assertTrue(
            $reflection->hasProperty(
                'paymentRecapRunService'
            )
        );
    }

    public function test_store_method_exists(): void
    {
        $this->assertTrue(
            method_exists(
                PaymentRecapRunController::class,
                'store'
            )
        );
    }

    public function test_show_method_exists(): void
    {
        $this->assertTrue(
            method_exists(
                PaymentRecapRunController::class,
                'show'
            )
        );
    }

    public function test_by_branch_method_exists(): void
    {
        $this->assertTrue(
            method_exists(
                PaymentRecapRunController::class,
                'byBranch'
            )
        );
    }

    public function test_mark_generated_method_exists(): void
    {
        $this->assertTrue(
            method_exists(
                PaymentRecapRunController::class,
                'markGenerated'
            )
        );
    }

    public function test_controller_is_constructed_with_service(): void
    {
        $service = app(PaymentRecapRunService::class);

        $controller = new PaymentRecapRunController(
            $service
        );

        $this->assertInstanceOf(
            PaymentRecapRunController::class,
            $controller
        );
    }

    public function test_store_accepts_payment_recap_run_request(): void
    {
        $reflection = new \ReflectionMethod(
            PaymentRecapRunController::class,
            'store'
        );

        $parameters = $reflection->getParameters();

        $this->assertCount(
            1,
            $parameters
        );

        $this->assertSame(
            PaymentRecapRunStoreRequest::class,
            $parameters[0]
                ->getType()
                ->getName()
        );
    }

    public function test_show_accepts_payment_recap_run_id(): void
    {
        $reflection = new \ReflectionMethod(
            PaymentRecapRunController::class,
            'show'
        );

        $parameters = $reflection->getParameters();

        $this->assertCount(
            1,
            $parameters
        );

        $this->assertSame(
            'paymentRecapRun',
            $parameters[0]->getName()
        );
    }

    public function test_by_branch_accepts_branch_id(): void
    {
        $reflection = new \ReflectionMethod(
            PaymentRecapRunController::class,
            'byBranch'
        );

        $parameters = $reflection->getParameters();

        $this->assertCount(
            1,
            $parameters
        );

        $this->assertSame(
            'branch',
            $parameters[0]->getName()
        );
    }

    public function test_mark_generated_accepts_request_and_payment_recap_run(): void
    {
        $reflection = new \ReflectionMethod(
            PaymentRecapRunController::class,
            'markGenerated'
        );

        $parameters = $reflection->getParameters();

        $this->assertCount(
            2,
            $parameters
        );

        $this->assertSame(
            'request',
            $parameters[0]->getName()
        );

        $this->assertSame(
            'paymentRecapRun',
            $parameters[1]->getName()
        );
    }
}
