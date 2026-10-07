<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use App\Services\TeacherAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeacherAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private TeacherAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TeacherAvailabilityService::class);
    }

    public function test_it_can_create_teacher_availability(): void
    {
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $availability = $this->service->create([
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-10',
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'class_type' => 'regular',
            'availability_status' => 'available',
            'notes' => 'Morning availability',
        ]);

        $this->assertInstanceOf(
            TeacherAvailability::class,
            $availability
        );

        $this->assertNotEmpty($availability->id);

        $this->assertSame(
            $teacher->id,
            $availability->teacher_id
        );

        $this->assertSame(
            $branch->id,
            $availability->branch_id
        );

        $this->assertSame(
            '2026-10-10',
            $availability->available_on->format('Y-m-d')
        );

        $this->assertSame(
            '09:00:00',
            $availability->starts_at
        );

        $this->assertSame(
            '12:00:00',
            $availability->ends_at
        );

        $this->assertSame(
            'regular',
            $availability->class_type
        );

        $this->assertSame(
            'available',
            $availability->availability_status
        );

        $this->assertSame(
            'Morning availability',
            $availability->notes
        );

        $this->assertDatabaseHas(
            'teacher_availability',
            [
                'id' => $availability->id,
                'teacher_id' => $teacher->id,
                'branch_id' => $branch->id,
                'class_type' => 'regular',
                'availability_status' => 'available',
            ]
        );
    }

    public function test_it_can_create_teacher_availability_without_notes(): void
    {
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $availability = $this->service->create([
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-11',
            'starts_at' => '13:00:00',
            'ends_at' => '15:00:00',
            'class_type' => 'private',
            'availability_status' => 'available',
        ]);

        $this->assertNull($availability->notes);

        $this->assertDatabaseHas(
            'teacher_availability',
            [
                'id' => $availability->id,
                'notes' => null,
            ]
        );
    }

    public function test_it_can_update_teacher_availability(): void
    {
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $availability = $this->service->create([
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-12',
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'class_type' => 'regular',
            'availability_status' => 'available',
            'notes' => 'Original note',
        ]);

        $newTeacher = $this->createTeacher();
        $newBranch = $this->createBranch();

        $updated = $this->service->update(
            $availability,
            [
                'teacher_id' => $newTeacher->id,
                'branch_id' => $newBranch->id,
                'available_on' => '2026-10-13',
                'starts_at' => '14:00:00',
                'ends_at' => '17:00:00',
                'class_type' => 'group',
                'availability_status' => 'booked',
                'notes' => 'Updated note',
            ]
        );

        $this->assertSame(
            $availability->id,
            $updated->id
        );

        $this->assertSame(
            $newTeacher->id,
            $updated->teacher_id
        );

        $this->assertSame(
            $newBranch->id,
            $updated->branch_id
        );

        $this->assertSame(
            '2026-10-13',
            $updated->available_on->format('Y-m-d')
        );

        $this->assertSame(
            '14:00:00',
            $updated->starts_at
        );

        $this->assertSame(
            '17:00:00',
            $updated->ends_at
        );

        $this->assertSame(
            'group',
            $updated->class_type
        );

        $this->assertSame(
            'booked',
            $updated->availability_status
        );

        $this->assertSame(
            'Updated note',
            $updated->notes
        );

        $this->assertDatabaseHas(
            'teacher_availability',
            [
                'id' => $availability->id,
                'teacher_id' => $newTeacher->id,
                'branch_id' => $newBranch->id,
                'class_type' => 'group',
                'availability_status' => 'booked',
                'notes' => 'Updated note',
            ]
        );
    }

    public function test_it_rejects_end_time_equal_to_start_time(): void
    {
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $this->expectException(ValidationException::class);

        $this->service->create([
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-14',
            'starts_at' => '10:00:00',
            'ends_at' => '10:00:00',
            'class_type' => 'regular',
            'availability_status' => 'available',
            'notes' => null,
        ]);
    }

    public function test_it_rejects_end_time_before_start_time(): void
    {
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $this->expectException(ValidationException::class);

        $this->service->create([
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-15',
            'starts_at' => '15:00:00',
            'ends_at' => '14:00:00',
            'class_type' => 'regular',
            'availability_status' => 'available',
            'notes' => null,
        ]);
    }

    public function test_it_fails_when_teacher_does_not_exist(): void
    {
        $branch = $this->createBranch();

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->create([
            'teacher_id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'available_on' => '2026-10-16',
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'class_type' => 'regular',
            'availability_status' => 'available',
            'notes' => null,
        ]);
    }

    public function test_it_fails_when_branch_does_not_exist(): void
    {
        $teacher = $this->createTeacher();

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->create([
            'teacher_id' => $teacher->id,
            'branch_id' => (string) Str::uuid(),
            'available_on' => '2026-10-17',
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'class_type' => 'regular',
            'availability_status' => 'available',
            'notes' => null,
        ]);
    }

    private function createTeacher(bool $isActive = true): Teacher
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'teacher-' . Str::random(8),
            'email' => Str::random(8) . '@example.com',
            'full_name' => 'Teacher Test',
            'password_hash' => bcrypt('password'),
            'role_code' => 'teacher',
            'is_active' => true,
        ]);

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'full_name' => 'Test Teacher',
            'whatsapp_number' => '081234567890',
            'is_active' => $isActive,
        ]);
    }

    private function createBranch(): Branch
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
}
