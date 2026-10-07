<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\GuardianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GuardianServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_guardian(): void
    {
        $student = $this->createStudent();

        $service = app(GuardianService::class);

        $guardian = $service->create([
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => true,
        ]);

        $this->assertInstanceOf(
            Guardian::class,
            $guardian
        );

        $this->assertNotEmpty($guardian->id);

        $this->assertSame(
            $student->id,
            $guardian->student_id
        );

        $this->assertSame(
            'Test Parent',
            $guardian->full_name
        );

        $this->assertSame(
            'Father',
            $guardian->relationship_name
        );

        $this->assertSame(
            '081111111111',
            $guardian->whatsapp_number
        );

        $this->assertTrue(
            $guardian->is_primary
        );
    }

    public function test_it_can_create_guardian_without_optional_relationship(): void
    {
        $student = $this->createStudent();

        $service = app(GuardianService::class);

        $guardian = $service->create([
            'student_id' => $student->id,
            'full_name' => 'Test Guardian',
            'whatsapp_number' => '081222222222',
            'is_primary' => false,
        ]);

        $this->assertNull(
            $guardian->relationship_name
        );

        $this->assertFalse(
            $guardian->is_primary
        );
    }

    public function test_it_can_create_multiple_guardians_for_one_student(): void
    {
        $student = $this->createStudent();

        $service = app(GuardianService::class);

        $first = $service->create([
            'student_id' => $student->id,
            'full_name' => 'Father',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => true,
        ]);

        $second = $service->create([
            'student_id' => $student->id,
            'full_name' => 'Mother',
            'relationship_name' => 'Mother',
            'whatsapp_number' => '082222222222',
            'is_primary' => false,
        ]);

        $this->assertNotSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            2,
            Guardian::query()
                ->where('student_id', $student->id)
                ->count()
        );
    }

    public function test_it_rejects_second_primary_guardian_for_same_student(): void
    {
        $student = $this->createStudent();

        $service = app(GuardianService::class);

        $service->create([
            'student_id' => $student->id,
            'full_name' => 'Father',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => true,
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        $service->create([
            'student_id' => $student->id,
            'full_name' => 'Mother',
            'relationship_name' => 'Mother',
            'whatsapp_number' => '082222222222',
            'is_primary' => true,
        ]);
    }

    public function test_it_can_update_guardian(): void
    {
        $student = $this->createStudent();

        $service = app(GuardianService::class);

        $guardian = $service->create([
            'student_id' => $student->id,
            'full_name' => 'Old Name',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => false,
        ]);

        $updated = $service->update(
            $guardian,
            [
                'student_id' => $student->id,
                'full_name' => 'New Name',
                'relationship_name' => 'Mother',
                'whatsapp_number' => '082222222222',
                'is_primary' => true,
            ]
        );

        $this->assertSame(
            $guardian->id,
            $updated->id
        );

        $this->assertSame(
            'New Name',
            $updated->full_name
        );

        $this->assertSame(
            'Mother',
            $updated->relationship_name
        );

        $this->assertSame(
            '082222222222',
            $updated->whatsapp_number
        );

        $this->assertTrue(
            $updated->is_primary
        );
    }

    public function test_it_can_delete_guardian(): void
    {
        $student = $this->createStudent();

        $service = app(GuardianService::class);

        $guardian = $service->create([
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => false,
        ]);

        $id = $guardian->id;

        $service->delete($guardian);

        $this->assertDatabaseMissing(
            'guardians',
            [
                'id' => $id,
            ]
        );
    }

    public function test_it_fails_when_student_does_not_exist(): void
    {
        $service = app(GuardianService::class);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $service->create([
            'student_id' => (string) Str::uuid(),
            'full_name' => 'Test Parent',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => false,
        ]);
    }

    private function createStudent(): Student
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'student_' . Str::lower(
                Str::random(8)
            ),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test Student',
            'role_code' => 'student',
            'is_active' => true,
        ]);

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
}
