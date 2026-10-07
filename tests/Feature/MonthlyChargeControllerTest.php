<?php

namespace Tests\Feature;

use App\Http\Controllers\MonthlyChargeController;
use App\Models\MonthlyCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyChargeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'superadmin_' . Str::random(8),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => password_hash(
                'password',
                PASSWORD_BCRYPT
            ),
            'full_name' => 'Test Superadmin',
            'role_code' => 'superadmin',
            'is_active' => true,
        ]);
    }

    public function test_controller_exists(): void
    {
        $this->assertTrue(
            class_exists(MonthlyChargeController::class)
        );
    }

    public function test_controller_can_be_resolved(): void
    {
        $controller = app(MonthlyChargeController::class);

        $this->assertInstanceOf(
            MonthlyChargeController::class,
            $controller
        );
    }

    public function test_index_method_exists(): void
    {
        $this->assertTrue(
            method_exists(
                MonthlyChargeController::class,
                'index'
            )
        );
    }

    public function test_create_method_exists(): void
    {
        $this->assertTrue(
            method_exists(
                MonthlyChargeController::class,
                'create'
            )
        );
    }

    public function test_store_method_exists(): void
    {
        $this->assertTrue(
            method_exists(
                MonthlyChargeController::class,
                'store'
            )
        );
    }

    public function test_show_method_exists(): void
    {
        $this->assertTrue(
            method_exists(
                MonthlyChargeController::class,
                'show'
            )
        );
    }

    public function test_update_and_destroy_methods_exist(): void
    {
        $this->assertTrue(
            method_exists(
                MonthlyChargeController::class,
                'update'
            )
        );

        $this->assertTrue(
            method_exists(
                MonthlyChargeController::class,
                'destroy'
            )
        );
    }
}
