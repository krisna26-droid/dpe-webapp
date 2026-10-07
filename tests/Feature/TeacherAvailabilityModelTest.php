<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherAvailabilityModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(
                Str::random(8)
            ),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => true,
        ]);
    }

    private function makeTeacher(): Teacher
    {
        $user = $this->makeUser('teacher');

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);
    }

    private function makeBranch(): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . strtoupper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => null,
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 1,
            'is_active' => true,
        ]);
    }

    private function makeAvailability(
        Teacher $teacher,
        Branch $branch
    ): TeacherAvailability {
        return TeacherAvailability::query()->create([
            'id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-01',
            'starts_at' => '09:00:00',
            'ends_at' => '10:00:00',
            'class_type' => 'private',
            'availability_status' => 'available',
            'notes' => 'Test availability',
        ]);
    }

    public function test_availability_has_correct_teacher_relation(): void
    {
        $relation = (new TeacherAvailability())->teacher();

        $this->assertInstanceOf(
            BelongsTo::class,
            $relation
        );

        $this->assertSame(
            'teacher_id',
            $relation->getForeignKeyName()
        );

        $this->assertSame(
            'id',
            $relation->getOwnerKeyName()
        );

        $this->assertSame(
            'teachers',
            $relation->getRelated()->getTable()
        );
    }

    public function test_availability_has_correct_branch_relation(): void
    {
        $relation = (new TeacherAvailability())->branch();

        $this->assertInstanceOf(
            BelongsTo::class,
            $relation
        );

        $this->assertSame(
            'branch_id',
            $relation->getForeignKeyName()
        );

        $this->assertSame(
            'id',
            $relation->getOwnerKeyName()
        );

        $this->assertSame(
            'branches',
            $relation->getRelated()->getTable()
        );
    }

    public function test_availability_casts_available_on_to_date(): void
    {
        $teacher = $this->makeTeacher();
        $branch = $this->makeBranch();

        $availability = $this->makeAvailability(
            $teacher,
            $branch
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $availability->available_on
        );
    }

    public function test_availability_can_be_created_with_nullable_notes(): void
    {
        $teacher = $this->makeTeacher();
        $branch = $this->makeBranch();

        $availability = TeacherAvailability::query()->create([
            'id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-01',
            'starts_at' => '09:00:00',
            'ends_at' => '10:00:00',
            'class_type' => 'private',
            'availability_status' => 'available',
            'notes' => null,
        ]);

        $this->assertDatabaseHas(
            'teacher_availability',
            [
                'id' => $availability->id,
                'teacher_id' => $teacher->id,
                'branch_id' => $branch->id,
                'class_type' => 'private',
                'availability_status' => 'available',
                'notes' => null,
            ]
        );
    }

    public function test_availability_can_store_all_business_fields(): void
    {
        $teacher = $this->makeTeacher();
        $branch = $this->makeBranch();

        $availability = TeacherAvailability::query()->create([
            'id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-15',
            'starts_at' => '13:30:00',
            'ends_at' => '15:00:00',
            'class_type' => 'group',
            'availability_status' => 'unavailable',
            'notes' => 'Teacher has another activity after this slot.',
        ]);

        $this->assertSame(
            $teacher->id,
            $availability->teacher_id
        );

        $this->assertSame(
            $branch->id,
            $availability->branch_id
        );

        $this->assertSame(
            'group',
            $availability->class_type
        );

        $this->assertSame(
            'unavailable',
            $availability->availability_status
        );

        $this->assertSame(
            'Teacher has another activity after this slot.',
            $availability->notes
        );
    }
}