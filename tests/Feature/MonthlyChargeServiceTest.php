<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MonthlyCharge;
use App\Models\Student;
use App\Models\User;
use App\Services\MonthlyChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyChargeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(
        string $role = 'student',
        bool $active = true
    ): User {
        $id = (string) Str::uuid();

        return User::query()->create([
            'id' => $id,
            'username' => $role . '_' . Str::random(8),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => password_hash(
                'password',
                PASSWORD_BCRYPT
            ),
            'full_name' => 'Test ' . ucfirst($role),
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

    public function test_service_can_create_monthly_charge(): void
    {
        $student = $this->createStudent();
        $branch = $this->createBranch();

        $service = app(MonthlyChargeService::class);

        $charge = $service->create([
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'charge_month' => '2026-10-01',
            'amount_idr' => 500000,
            'due_on' => '2026-10-10',
        ]);

        $this->assertInstanceOf(
            MonthlyCharge::class,
            $charge
        );

        $this->assertNotEmpty($charge->id);

        $this->assertDatabaseHas('monthly_charges', [
            'id' => $charge->id,
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'charge_month' => '2026-10-01 00:00:00',
            'amount_idr' => '500000.00',
            'due_on' => '2026-10-10 00:00:00',
        ]);
    }

    public function test_service_can_update_monthly_charge(): void
    {
        $student = $this->createStudent();
        $branch = $this->createBranch();

        $charge = MonthlyCharge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'charge_month' => '2026-10-01',
            'amount_idr' => 500000,
            'due_on' => '2026-10-10',
        ]);

        $newStudent = $this->createStudent();
        $newBranch = $this->createBranch();

        $service = app(MonthlyChargeService::class);

        $updated = $service->update(
            $charge,
            [
                'student_id' => $newStudent->id,
                'branch_id' => $newBranch->id,
                'charge_month' => '2026-11-01',
                'amount_idr' => 750000,
                'due_on' => '2026-11-15',
            ]
        );

        $this->assertTrue(
            $updated->is($charge)
        );

        $this->assertSame(
            $newStudent->id,
            $updated->student_id
        );

        $this->assertSame(
            $newBranch->id,
            $updated->branch_id
        );

        $this->assertDatabaseHas('monthly_charges', [
            'id' => $charge->id,
            'student_id' => $newStudent->id,
            'branch_id' => $newBranch->id,
            'charge_month' => '2026-11-01 00:00:00',
            'amount_idr' => '750000.00',
            'due_on' => '2026-11-15 00:00:00',
        ]);
    }

    public function test_service_can_delete_monthly_charge(): void
    {
        $student = $this->createStudent();
        $branch = $this->createBranch();

        $charge = MonthlyCharge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'charge_month' => '2026-10-01',
            'amount_idr' => 500000,
            'due_on' => '2026-10-10',
        ]);

        $service = app(MonthlyChargeService::class);

        $service->delete($charge);

        $this->assertDatabaseMissing('monthly_charges', [
            'id' => $charge->id,
        ]);
    }

    public function test_service_fails_when_student_does_not_exist(): void
    {
        $branch = $this->createBranch();

        $service = app(MonthlyChargeService::class);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $service->create([
            'student_id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'charge_month' => '2026-10-01',
            'amount_idr' => 500000,
            'due_on' => '2026-10-10',
        ]);
    }

    public function test_service_fails_when_branch_does_not_exist(): void
    {
        $student = $this->createStudent();

        $service = app(MonthlyChargeService::class);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $service->create([
            'student_id' => $student->id,
            'branch_id' => (string) Str::uuid(),
            'charge_month' => '2026-10-01',
            'amount_idr' => 500000,
            'due_on' => '2026-10-10',
        ]);
    }

    public function test_service_can_set_zero_amount(): void
    {
        $student = $this->createStudent();
        $branch = $this->createBranch();

        $service = app(MonthlyChargeService::class);

        $charge = $service->create([
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'charge_month' => '2026-10-01',
            'amount_idr' => 0,
            'due_on' => '2026-10-10',
        ]);

        $this->assertSame(
            '0.00',
            $charge->amount_idr
        );
    }

    public function test_service_can_update_to_zero_amount(): void
    {
        $student = $this->createStudent();
        $branch = $this->createBranch();

        $charge = MonthlyCharge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'charge_month' => '2026-10-01',
            'amount_idr' => 500000,
            'due_on' => '2026-10-10',
        ]);

        $service = app(MonthlyChargeService::class);

        $updated = $service->update(
            $charge,
            [
                'student_id' => $student->id,
                'branch_id' => $branch->id,
                'charge_month' => '2026-10-01',
                'amount_idr' => 0,
                'due_on' => '2026-10-10',
            ]
        );

        $this->assertSame(
            '0.00',
            $updated->amount_idr
        );
    }
}
