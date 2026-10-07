<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherAvailabilityHttpTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperadmin(bool $isActive = true): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'superadmin_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Super Admin',
            'role_code' => 'superadmin',
            'is_active' => $isActive,
        ]);
    }

    private function makeUser(
        string $roleCode = 'teacher',
        bool $isActive = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);
    }

    private function makeBranch(array $overrides = []): Branch
    {
        return Branch::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . strtoupper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => 'Test Address',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ], $overrides));
    }

    private function makeTeacher(array $overrides = []): Teacher
    {
        $user = $this->makeUser('teacher');

        return Teacher::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'full_name' => 'Test Teacher',
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ], $overrides));
    }

    private function validPayload(
        Branch $branch,
        Teacher $teacher
    ): array {
        return [
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'class_type' => 'regular',
            'availability_status' => 'available',
            'notes' => 'Morning availability',
        ];
    }

    public function test_superadmin_can_open_teacher_availability_index(): void
    {
        $superadmin = $this->makeSuperadmin();

        $this->actingAs($superadmin)
            ->get(route('superadmin.teacher-availability.index'))
            ->assertOk();
    }

    public function test_superadmin_can_store_teacher_availability_through_http(): void
    {
        $superadmin = $this->makeSuperadmin();
        $branch = $this->makeBranch();
        $teacher = $this->makeTeacher();

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.teacher-availability.store'),
                $this->validPayload($branch, $teacher)
            );

        $response->assertRedirect();

        $this->assertDatabaseHas('teacher_availability', [
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-20 00:00:00',
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'class_type' => 'regular',
            'availability_status' => 'available',
            'notes' => 'Morning availability',
        ]);
    }

    public function test_superadmin_can_update_teacher_availability_through_http(): void
    {
        $superadmin = $this->makeSuperadmin();
        $branch = $this->makeBranch();
        $teacher = $this->makeTeacher();

        $availability = TeacherAvailability::query()->create([
            'id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-21',
            'starts_at' => '10:00:00',
            'ends_at' => '13:00:00',
            'class_type' => 'private',
            'availability_status' => 'available',
            'notes' => 'Original note',
        ]);

        $response = $this
            ->actingAs($superadmin)
            ->put(
                route(
                    'superadmin.teacher-availability.update',
                    $availability
                ),
                [
                    'teacher_id' => $teacher->id,
                    'branch_id' => $branch->id,
                    'available_on' => '2026-10-22',
                    'starts_at' => '14:00:00',
                    'ends_at' => '17:00:00',
                    'class_type' => 'group',
                    'availability_status' => 'booked',
                    'notes' => 'Updated note',
                ]
            );

        $response->assertRedirect();

        $this->assertDatabaseHas('teacher_availability', [
            'id' => $availability->id,
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'available_on' => '2026-10-22 00:00:00',
            'starts_at' => '14:00:00',
            'ends_at' => '17:00:00',
            'class_type' => 'group',
            'availability_status' => 'booked',
            'notes' => 'Updated note',
        ]);
    }

    public function test_non_superadmin_cannot_access_teacher_availability_index(): void
    {
        foreach (['admin', 'teacher', 'student'] as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)
                ->get(route('superadmin.teacher-availability.index'))
                ->assertForbidden();
        }
    }

    public function test_inactive_superadmin_cannot_access_teacher_availability_index(): void
    {
        $this->actingAs($this->makeSuperadmin(false))
            ->get(route('superadmin.teacher-availability.index'))
            ->assertForbidden();
    }

    public function test_guest_cannot_store_teacher_availability(): void
    {
        $branch = $this->makeBranch();
        $teacher = $this->makeTeacher();

        $this->post(
            route('superadmin.teacher-availability.store'),
            $this->validPayload($branch, $teacher)
        )->assertRedirect(route('login'));
    }

    public function test_required_teacher_availability_fields_are_validated(): void
    {
        $superadmin = $this->makeSuperadmin();

        $this->actingAs($superadmin)
            ->from('/superadmin/teacher-availability/create')
            ->post(
                route('superadmin.teacher-availability.store'),
                []
            )
            ->assertSessionHasErrors([
                'teacher_id',
                'branch_id',
                'available_on',
                'starts_at',
                'ends_at',
                'class_type',
                'availability_status',
            ]);
    }

    public function test_superadmin_cannot_store_when_end_time_is_not_after_start_time(): void
    {
        $superadmin = $this->makeSuperadmin();
        $branch = $this->makeBranch();
        $teacher = $this->makeTeacher();

        $payload = $this->validPayload($branch, $teacher);
        $payload['starts_at'] = '09:00:00';
        $payload['ends_at'] = '09:00:00';

        $this->actingAs($superadmin)
            ->from('/superadmin/teacher-availability/create')
            ->post(
                route('superadmin.teacher-availability.store'),
                $payload
            )
            ->assertSessionHasErrors('ends_at');
    }

    public function test_superadmin_cannot_store_with_invalid_foreign_keys(): void
    {
        $superadmin = $this->makeSuperadmin();

        $payload = [
            'teacher_id' => (string) Str::uuid(),
            'branch_id' => (string) Str::uuid(),
            'available_on' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'class_type' => 'regular',
            'availability_status' => 'available',
            'notes' => 'Morning availability',
        ];

        $this->actingAs($superadmin)
            ->from('/superadmin/teacher-availability/create')
            ->post(
                route('superadmin.teacher-availability.store'),
                $payload
            )
            ->assertSessionHasErrors(['teacher_id', 'branch_id']);
    }
}
