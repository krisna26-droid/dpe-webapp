<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentTeacherAssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => true,
        ]);
    }

    private function makeStudent(): Student
    {
        $portalUser = $this->makeUser('student');

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Test Student',
            'began_on' => '2026-10-01',
            'status' => 'active',
        ]);
    }

    private function makeTeacher(): Teacher
    {
        $user = $this->makeUser('teacher');

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'full_name' => 'Test Teacher',
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);
    }

    public function test_assignment_has_correct_student_relation(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $assignment = StudentTeacherAssignment::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
            'is_report_owner' => false,
        ]);

        $this->assertTrue(
            $assignment->student->is($student)
        );
    }

    public function test_assignment_has_correct_teacher_relation(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $assignment = StudentTeacherAssignment::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
            'is_report_owner' => false,
        ]);

        $this->assertTrue(
            $assignment->teacher->is($teacher)
        );
    }

    public function test_assignment_casts_dates_and_report_owner(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $assignment = StudentTeacherAssignment::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
            'is_report_owner' => true,
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $assignment->starts_on
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $assignment->ends_on
        );

        $this->assertTrue(
            $assignment->is_report_owner
        );
    }

    public function test_assignment_can_have_no_end_date(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $assignment = StudentTeacherAssignment::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
            'is_report_owner' => true,
        ]);

        $this->assertNull(
            $assignment->ends_on
        );
    }
}
