<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentTeacherAssignmentHttpTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode,
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

    private function validData(): array
    {
        return [
            'student_id' => (string) Str::uuid(),
            'teacher_id' => (string) Str::uuid(),
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
            'is_report_owner' => false,
        ];
    }

    public function test_guest_cannot_store_student_teacher_assignment(): void
    {
        $response = $this->post(
            route('superadmin.student-teacher-assignments.store'),
            $this->validData()
        );

        $response->assertRedirect();
    }

    public function test_non_superadmin_cannot_store_student_teacher_assignment(): void
    {
        $user = $this->makeUser('teacher');

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'superadmin.student-teacher-assignments.store'
                ),
                $this->validData()
            );

        $response->assertForbidden();
    }

    public function test_inactive_superadmin_cannot_store_student_teacher_assignment(): void
    {
        $user = $this->makeUser(
            'superadmin',
            false
        );

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'superadmin.student-teacher-assignments.store'
                ),
                $this->validData()
            );

        $response->assertForbidden();
    }

    public function test_superadmin_cannot_store_without_student_id(): void
    {
        $user = $this->makeUser('superadmin');

        $data = $this->validData();

        unset($data['student_id']);

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'superadmin.student-teacher-assignments.store'
                ),
                $data
            );

        $response->assertSessionHasErrors([
            'student_id',
        ]);
    }

    public function test_superadmin_cannot_store_without_teacher_id(): void
    {
        $user = $this->makeUser('superadmin');

        $data = $this->validData();

        unset($data['teacher_id']);

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'superadmin.student-teacher-assignments.store'
                ),
                $data
            );

        $response->assertSessionHasErrors([
            'teacher_id',
        ]);
    }

    public function test_superadmin_cannot_store_without_starts_on(): void
    {
        $user = $this->makeUser('superadmin');

        $data = $this->validData();

        unset($data['starts_on']);

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'superadmin.student-teacher-assignments.store'
                ),
                $data
            );

        $response->assertSessionHasErrors([
            'starts_on',
        ]);
    }

    public function test_superadmin_cannot_store_when_end_date_is_before_start_date(): void
    {
        $user = $this->makeUser('superadmin');

        $data = $this->validData();

        $data['starts_on'] = '2026-10-10';
        $data['ends_on'] = '2026-10-01';

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'superadmin.student-teacher-assignments.store'
                ),
                $data
            );

        $response->assertSessionHasErrors([
            'ends_on',
        ]);
    }

    public function test_superadmin_cannot_store_with_invalid_report_owner_type(): void
    {
        $user = $this->makeUser('superadmin');

        $data = $this->validData();

        $data['is_report_owner'] = 'not-a-boolean';

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'superadmin.student-teacher-assignments.store'
                ),
                $data
            );

        $response->assertSessionHasErrors([
            'is_report_owner',
        ]);
    }
}