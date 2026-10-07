<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuardianModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_has_correct_student_relation(): void
    {
        $student = $this->createStudent();

        $guardian = Guardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Parent',
            'whatsapp_number' => '081111111111',
            'is_primary' => true,
        ]);

        $this->assertInstanceOf(
            Student::class,
            $guardian->student
        );

        $this->assertSame(
            $student->id,
            $guardian->student->id
        );
    }

    public function test_guardian_casts_primary_flag_to_boolean(): void
    {
        $student = $this->createStudent();

        $guardian = Guardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Parent',
            'whatsapp_number' => '081111111111',
            'is_primary' => 1,
        ]);

        $this->assertIsBool(
            $guardian->is_primary
        );

        $this->assertTrue(
            $guardian->is_primary
        );
    }

    public function test_guardian_casts_created_at_to_datetime(): void
    {
        $student = $this->createStudent();

        $guardian = Guardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => null,
            'whatsapp_number' => '081111111111',
            'is_primary' => false,
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $guardian->created_at
        );
    }

    public function test_guardian_can_have_nullable_relationship_name(): void
    {
        $student = $this->createStudent();

        $guardian = Guardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Test Guardian',
            'relationship_name' => null,
            'whatsapp_number' => '081222222222',
            'is_primary' => false,
        ]);

        $this->assertNull(
            $guardian->relationship_name
        );
    }

    public function test_guardian_can_store_all_business_fields(): void
    {
        $student = $this->createStudent();

        $guardian = Guardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Test Father',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081333333333',
            'is_primary' => true,
        ]);

        $this->assertSame(
            $student->id,
            $guardian->student_id
        );

        $this->assertSame(
            'Test Father',
            $guardian->full_name
        );

        $this->assertSame(
            'Father',
            $guardian->relationship_name
        );

        $this->assertSame(
            '081333333333',
            $guardian->whatsapp_number
        );

        $this->assertTrue(
            $guardian->is_primary
        );
    }

    public function test_student_can_have_multiple_guardians(): void
    {
        $student = $this->createStudent();

        Guardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Father',
            'relationship_name' => 'Father',
            'whatsapp_number' => '081111111111',
            'is_primary' => true,
        ]);

        Guardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Mother',
            'relationship_name' => 'Mother',
            'whatsapp_number' => '082222222222',
            'is_primary' => false,
        ]);

        $this->assertCount(
            2,
            $student->fresh()->guardians
        );
    }

    public function test_student_relation_returns_guardians(): void
    {
        $student = $this->createStudent();

        Guardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'full_name' => 'Test Parent',
            'relationship_name' => 'Parent',
            'whatsapp_number' => '081111111111',
            'is_primary' => false,
        ]);

        $this->assertTrue(
            $student->guardians()->exists()
        );

        $this->assertSame(
            $student->id,
            $student->guardians()->first()->student_id
        );
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
