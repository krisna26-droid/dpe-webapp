<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuardianHttpTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode = 'superadmin',
        bool $isActive = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);
    }

    private function makeStudent(): Student
    {
        $user = $this->makeUser('student');

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $user->id,
            'full_name' => 'Test Student',
            'photo_file_id' => null,
            'school_name' => 'Test School',
            'grade_name' => 'Grade 5',
            'began_on' => '2026-09-01',
            'status' => 'active',
            'special_notes_internal' => null,
        ]);
    }

    private function makeGuardian(
        ?Student $student = null,
        bool $isPrimary = false
    ): Guardian {
        $student ??= $this->makeStudent();

        return Guardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => $isPrimary,
        ]);
    }

    private function actingAsSuperadmin(): User
    {
        $user = $this->makeUser('superadmin');

        $this->actingAs($user);

        return $user;
    }

    private function validData(Student $student): array
    {
        return [
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => false,
        ];
    }

    public function test_guest_cannot_access_guardian_index(): void
    {
        $response = $this->get(
            route('superadmin.guardians.index')
        );

        $response->assertRedirect();
    }

    public function test_non_superadmin_cannot_access_guardian_index(): void
    {
        $user = $this->makeUser('teacher');

        $response = $this
            ->actingAs($user)
            ->get(
                route('superadmin.guardians.index')
            );

        $response->assertForbidden();
    }

    public function test_superadmin_can_access_guardian_index(): void
    {
        $this->actingAsSuperadmin();

        $this->get(
            route('superadmin.guardians.index')
        )->assertOk();
    }

    public function test_superadmin_can_access_guardian_create_page(): void
    {
        $this->actingAsSuperadmin();

        $this->get(
            route('superadmin.guardians.create')
        )->assertOk();
    }

    public function test_superadmin_can_access_guardian_show_page(): void
    {
        $this->actingAsSuperadmin();

        $guardian = $this->makeGuardian();

        $this->get(
            route(
                'superadmin.guardians.show',
                $guardian
            )
        )->assertOk();
    }

    public function test_superadmin_can_access_guardian_edit_page(): void
    {
        $this->actingAsSuperadmin();

        $guardian = $this->makeGuardian();

        $this->get(
            route(
                'superadmin.guardians.edit',
                $guardian
            )
        )->assertOk();
    }

    public function test_superadmin_can_create_guardian(): void
    {
        $this->actingAsSuperadmin();

        $student = $this->makeStudent();

        $data = $this->validData($student);

        $response = $this->post(
            route('superadmin.guardians.store'),
            $data
        );

        $response
            ->assertRedirect();

        $this->assertDatabaseHas(
            'guardians',
            [
                'student_id' => $student->id,
                'full_name' => 'Test Parent',
                'relationship_name' => 'Father',
                'whatsapp_number' => '081111111111',
                'is_primary' => 0,
            ]
        );
    }

    public function test_store_guardian_requires_required_fields(): void
    {
        $this->actingAsSuperadmin();

        $response = $this->post(
            route('superadmin.guardians.store'),
            []
        );

        $response->assertSessionHasErrors([
            'student_id',
            'full_name',
            'whatsapp_number',
        ]);
    }

    public function test_store_guardian_rejects_non_existing_student(): void
    {
        $this->actingAsSuperadmin();

        $response = $this->post(
            route('superadmin.guardians.store'),
            [
                'student_id' => (string) Str::uuid(),
                'full_name' => 'Test Parent',
                'relationship_name' => 'Father',
                'whatsapp_number' => '081111111111',
                'is_primary' => false,
            ]
        );

        $response->assertSessionHasErrors([
            'student_id',
        ]);
    }

    public function test_superadmin_can_update_guardian(): void
    {
        $this->actingAsSuperadmin();

        $student = $this->makeStudent();
        $guardian = $this->makeGuardian($student);

        $data = [
            'student_id' => $student->id,
            'full_name' => 'Updated Parent',
            'relationship_name' => 'Mother',
            'whatsapp_number' => '082222222222',
            'is_primary' => false,
        ];

        $response = $this->put(
            route(
                'superadmin.guardians.update',
                $guardian
            ),
            $data
        );

        $response->assertRedirect();

        $this->assertDatabaseHas(
            'guardians',
            [
                'id' => $guardian->id,
                'student_id' => $student->id,
                'full_name' => 'Updated Parent',
                'relationship_name' => 'Mother',
                'whatsapp_number' => '082222222222',
                'is_primary' => 0,
            ]
        );
    }

    public function test_superadmin_can_delete_guardian(): void
    {
        $this->actingAsSuperadmin();

        $guardian = $this->makeGuardian();

        $response = $this->delete(
            route(
                'superadmin.guardians.destroy',
                $guardian
            )
        );

        $response->assertRedirect(
            route('superadmin.guardians.index')
        );

        $this->assertDatabaseMissing(
            'guardians',
            [
                'id' => $guardian->id,
            ]
        );
    }

    public function test_non_superadmin_cannot_create_guardian(): void
    {
        $user = $this->makeUser('teacher');

        $student = $this->makeStudent();

        $response = $this
            ->actingAs($user)
            ->post(
                route('superadmin.guardians.store'),
                $this->validData($student)
            );

        $response->assertForbidden();

        $this->assertDatabaseCount('guardians', 0);
    }

    public function test_non_superadmin_cannot_update_guardian(): void
    {
        $user = $this->makeUser('teacher');

        $guardian = $this->makeGuardian();

        $response = $this
            ->actingAs($user)
            ->put(
                route(
                    'superadmin.guardians.update',
                    $guardian
                ),
                [
                    'student_id' => $guardian->student_id,
                    'full_name' => 'Unauthorized Update',
                    'relationship_name' => 'Father',
                    'whatsapp_number' => '081111111111',
                    'is_primary' => false,
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseHas(
            'guardians',
            [
                'id' => $guardian->id,
                'full_name' => 'Test Parent',
            ]
        );
    }

    public function test_non_superadmin_cannot_delete_guardian(): void
    {
        $user = $this->makeUser('teacher');

        $guardian = $this->makeGuardian();

        $response = $this
            ->actingAs($user)
            ->delete(
                route(
                    'superadmin.guardians.destroy',
                    $guardian
                )
            );

        $response->assertForbidden();

        $this->assertDatabaseHas(
            'guardians',
            [
                'id' => $guardian->id,
            ]
        );
    }
}
