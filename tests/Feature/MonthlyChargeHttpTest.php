<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MonthlyCharge;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyChargeHttpTest extends TestCase
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
            'due_on' => '2026-10-10',
        ]);
    }

    public function test_guest_cannot_access_monthly_charges(): void
    {
        $response = $this->get(
            route('superadmin.monthly-charges.index')
        );

        $response->assertRedirect();
    }

    public function test_non_superadmin_cannot_access_monthly_charges(): void
    {
        $user = $this->createUser('admin');

        $response = $this->actingAs($user)
            ->get(
                route('superadmin.monthly-charges.index')
            );

        $response->assertForbidden();
    }

    public function test_index_page_is_accessible_by_superadmin(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)
            ->get(
                route('superadmin.monthly-charges.index')
            );

        $response->assertOk();
        $response->assertViewIs('monthly-charges.index');
    }

    public function test_create_page_is_accessible_by_superadmin(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)
            ->get(
                route('superadmin.monthly-charges.create')
            );

        $response->assertOk();
        $response->assertViewIs('monthly-charges.create');
    }

    public function test_store_creates_monthly_charge(): void
    {
        $user = $this->createUser();
        $student = $this->createStudent();
        $branch = $this->createBranch();

        $response = $this->actingAs($user)
            ->post(
                route('superadmin.monthly-charges.store'),
                [
                    'student_id' => $student->id,
                    'branch_id' => $branch->id,
                    'charge_month' => '2026-10-01',
                    'amount_idr' => 500000,
                    'due_on' => '2026-10-10',
                ]
            );

        $response->assertRedirect();

        $this->assertDatabaseHas('monthly_charges', [
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'charge_month' => '2026-10-01 00:00:00',
            'amount_idr' => '500000.00',
            'due_on' => '2026-10-10 00:00:00',
        ]);
    }

    public function test_show_page_is_accessible(): void
    {
        $user = $this->createUser();
        $charge = $this->createCharge();

        $response = $this->actingAs($user)
            ->get(
                route(
                    'superadmin.monthly-charges.show',
                    $charge
                )
            );

        $response->assertOk();
        $response->assertViewIs('monthly-charges.show');
    }

    public function test_edit_page_is_accessible(): void
    {
        $user = $this->createUser();
        $charge = $this->createCharge();

        $response = $this->actingAs($user)
            ->get(
                route(
                    'superadmin.monthly-charges.edit',
                    $charge
                )
            );

        $response->assertOk();
        $response->assertViewIs('monthly-charges.edit');
    }

    public function test_update_updates_monthly_charge(): void
    {
        $user = $this->createUser();
        $charge = $this->createCharge();

        $newStudent = $this->createStudent();
        $newBranch = $this->createBranch();

        $response = $this->actingAs($user)
            ->put(
                route(
                    'superadmin.monthly-charges.update',
                    $charge
                ),
                [
                    'student_id' => $newStudent->id,
                    'branch_id' => $newBranch->id,
                    'charge_month' => '2026-11-01',
                    'amount_idr' => 750000,
                    'due_on' => '2026-11-15',
                ]
            );

        $response->assertRedirect();

        $this->assertDatabaseHas('monthly_charges', [
            'id' => $charge->id,
            'student_id' => $newStudent->id,
            'branch_id' => $newBranch->id,
            'charge_month' => '2026-11-01 00:00:00',
            'amount_idr' => '750000.00',
            'due_on' => '2026-11-15 00:00:00',
        ]);
    }

    public function test_destroy_deletes_monthly_charge(): void
    {
        $user = $this->createUser();
        $charge = $this->createCharge();

        $response = $this->actingAs($user)
            ->delete(
                route(
                    'superadmin.monthly-charges.destroy',
                    $charge
                )
            );

        $response->assertRedirect(
            route('superadmin.monthly-charges.index')
        );

        $this->assertDatabaseMissing('monthly_charges', [
            'id' => $charge->id,
        ]);
    }
}
