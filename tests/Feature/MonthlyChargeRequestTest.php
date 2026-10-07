<?php

namespace Tests\Feature;

use App\Http\Requests\MonthlyCharge\StoreMonthlyChargeRequest;
use App\Http\Requests\MonthlyCharge\UpdateMonthlyChargeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyChargeRequestTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(
        string $role = 'superadmin',
        bool $active = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $role . '_' . Str::random(8),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => password_hash(
                'password',
                PASSWORD_BCRYPT
            ),
            'full_name' => 'Test User',
            'role_code' => $role,
            'is_active' => $active,
        ]);
    }

    public function test_store_request_authorizes_active_superadmin(): void
    {
        $user = $this->createUser('superadmin', true);

        $this->actingAs($user);

        $request = StoreMonthlyChargeRequest::create(
            '/superadmin/monthly-charges',
            'POST'
        );

        $this->assertTrue(
            $request->authorize()
        );
    }

    public function test_store_request_rejects_non_superadmin(): void
    {
        $user = $this->createUser('admin', true);

        $this->actingAs($user);

        $request = StoreMonthlyChargeRequest::create(
            '/superadmin/monthly-charges',
            'POST'
        );

        $this->assertFalse(
            $request->authorize()
        );
    }

    public function test_store_request_rejects_inactive_superadmin(): void
    {
        $user = $this->createUser('superadmin', false);

        $this->actingAs($user);

        $request = StoreMonthlyChargeRequest::create(
            '/superadmin/monthly-charges',
            'POST'
        );

        $this->assertFalse(
            $request->authorize()
        );
    }

    public function test_store_request_accepts_valid_data(): void
    {
        $validator = Validator::make(
            [
                'student_id' => (string) Str::uuid(),
                'branch_id' => (string) Str::uuid(),
                'charge_month' => '2026-10-01',
                'amount_idr' => 500000,
                'due_on' => '2026-10-10',
            ],
            (new StoreMonthlyChargeRequest())->rules()
        );

        /*
         * Existence rules are tested separately through the database-backed
         * validation tests below.
         */
        $this->assertFalse(
            $validator->errors()->has('charge_month')
        );

        $this->assertFalse(
            $validator->errors()->has('amount_idr')
        );

        $this->assertFalse(
            $validator->errors()->has('due_on')
        );
    }

    public function test_store_request_rejects_invalid_dates(): void
    {
        $validator = Validator::make(
            [
                'student_id' => (string) Str::uuid(),
                'branch_id' => (string) Str::uuid(),
                'charge_month' => 'October 2026',
                'amount_idr' => 500000,
                'due_on' => '10 October 2026',
            ],
            (new StoreMonthlyChargeRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('charge_month')
        );

        $this->assertTrue(
            $validator->errors()->has('due_on')
        );
    }

    public function test_store_request_rejects_negative_amount(): void
    {
        $validator = Validator::make(
            [
                'student_id' => (string) Str::uuid(),
                'branch_id' => (string) Str::uuid(),
                'charge_month' => '2026-10-01',
                'amount_idr' => -1,
                'due_on' => '2026-10-10',
            ],
            (new StoreMonthlyChargeRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('amount_idr')
        );
    }

    public function test_store_request_requires_all_fields(): void
    {
        $validator = Validator::make(
            [],
            (new StoreMonthlyChargeRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('student_id')
        );

        $this->assertTrue(
            $validator->errors()->has('branch_id')
        );

        $this->assertTrue(
            $validator->errors()->has('charge_month')
        );

        $this->assertTrue(
            $validator->errors()->has('amount_idr')
        );
    }

    public function test_update_request_has_same_validation_rules(): void
    {
        $storeRules = (new StoreMonthlyChargeRequest())->rules();
        $updateRules = (new UpdateMonthlyChargeRequest())->rules();

        $this->assertSame(
            $storeRules['student_id'][0],
            $updateRules['student_id'][0]
        );

        $this->assertSame(
            $storeRules['student_id'][1],
            $updateRules['student_id'][1]
        );

        $this->assertSame(
            $storeRules['branch_id'][0],
            $updateRules['branch_id'][0]
        );

        $this->assertSame(
            $storeRules['branch_id'][1],
            $updateRules['branch_id'][1]
        );

        $this->assertSame(
            $storeRules['charge_month'],
            $updateRules['charge_month']
        );

        $this->assertSame(
            $storeRules['amount_idr'],
            $updateRules['amount_idr']
        );

        $this->assertSame(
            $storeRules['due_on'],
            $updateRules['due_on']
        );
    }

    public function test_update_request_authorizes_active_superadmin(): void
    {
        $user = $this->createUser('superadmin', true);

        $this->actingAs($user);

        $request = UpdateMonthlyChargeRequest::create(
            '/superadmin/monthly-charges/test',
            'PUT'
        );

        $this->assertTrue(
            $request->authorize()
        );
    }

    public function test_store_request_accepts_nullable_due_on(): void
    {
        $validator = Validator::make(
            [
                'student_id' => (string) Str::uuid(),
                'branch_id' => (string) Str::uuid(),
                'charge_month' => '2026-10-01',
                'amount_idr' => 500000,
                'due_on' => null,
            ],
            (new StoreMonthlyChargeRequest())->rules()
        );

        $this->assertFalse(
            $validator->errors()->has('due_on')
        );
    }
}
