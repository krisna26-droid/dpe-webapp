<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MonthlyCharge;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyChargeModelTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(): Student
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'student_' . Str::random(8),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => password_hash(
                'password',
                PASSWORD_BCRYPT
            ),
            'full_name' => 'Test Student',
            'role_code' => 'student',
            'is_active' => true,
        ]);

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $user->id,
            'full_name' => $user->full_name,
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

    public function test_monthly_charge_uses_expected_table(): void
    {
        $charge = new MonthlyCharge();

        $this->assertSame(
            'monthly_charges',
            $charge->getTable()
        );
    }

    public function test_monthly_charge_does_not_use_incrementing_id(): void
    {
        $charge = new MonthlyCharge();

        $this->assertFalse(
            $charge->getIncrementing()
        );

        $this->assertSame(
            'string',
            $charge->getKeyType()
        );
    }

    public function test_monthly_charge_has_expected_fillable_fields(): void
    {
        $charge = new MonthlyCharge();

        $this->assertSame(
            [
                'id',
                'student_id',
                'branch_id',
                'charge_month',
                'amount_idr',
                'due_on',
            ],
            $charge->getFillable()
        );
    }

    public function test_monthly_charge_has_no_updated_at_column(): void
    {
        $charge = new MonthlyCharge();

        $this->assertNull(
            $charge->getUpdatedAtColumn()
        );

        $this->assertSame(
            'created_at',
            $charge->getCreatedAtColumn()
        );
    }

    public function test_monthly_charge_casts_dates_and_amount(): void
    {
        $charge = new MonthlyCharge();

        $this->assertSame(
            'date',
            $charge->getCasts()['charge_month']
        );

        $this->assertSame(
            'decimal:2',
            $charge->getCasts()['amount_idr']
        );

        $this->assertSame(
            'date',
            $charge->getCasts()['due_on']
        );

        $this->assertSame(
            'datetime',
            $charge->getCasts()['created_at']
        );
    }

    public function test_monthly_charge_belongs_to_student(): void
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

        $this->assertTrue(
            $charge->student->is($student)
        );
    }

    public function test_monthly_charge_belongs_to_branch(): void
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

        $this->assertTrue(
            $charge->branch->is($branch)
        );
    }

    public function test_student_has_many_monthly_charges(): void
    {
        $student = $this->createStudent();
        $branch = $this->createBranch();

        MonthlyCharge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'charge_month' => '2026-10-01',
            'amount_idr' => 500000,
            'due_on' => '2026-10-10',
        ]);

        $this->assertCount(
            1,
            $student->monthlyCharges
        );
    }

    public function test_branch_has_many_monthly_charges(): void
    {
        $student = $this->createStudent();
        $branch = $this->createBranch();

        MonthlyCharge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'charge_month' => '2026-10-01',
            'amount_idr' => 500000,
            'due_on' => '2026-10-10',
        ]);

        $this->assertCount(
            1,
            $branch->monthlyCharges
        );
    }
}
