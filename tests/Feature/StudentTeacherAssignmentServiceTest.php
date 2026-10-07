<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\StudentTeacherAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudentTeacherAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentTeacherAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StudentTeacherAssignmentService::class);
    }

    private function makeUser(string $roleCode): User
    {
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

    public function test_service_can_create_assignment(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $assignment = $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
            'is_report_owner' => false,
        ]);

        $this->assertInstanceOf(
            StudentTeacherAssignment::class,
            $assignment
        );

        $this->assertNotEmpty($assignment->id);

        $this->assertTrue(
            Str::isUuid($assignment->id)
        );

        $this->assertSame(
            $student->id,
            $assignment->student_id
        );

        $this->assertSame(
            $teacher->id,
            $assignment->teacher_id
        );

        $this->assertSame(
            '2026-10-01',
            $assignment->starts_on->toDateString()
        );

        $this->assertSame(
            '2026-12-31',
            $assignment->ends_on->toDateString()
        );

        $this->assertFalse(
            $assignment->is_report_owner
        );
    }

    public function test_service_can_create_open_ended_assignment(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $assignment = $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
            'is_report_owner' => false,
        ]);

        $this->assertNull(
            $assignment->ends_on
        );
    }

    public function test_service_can_create_report_owner_assignment(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $assignment = $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
            'is_report_owner' => true,
        ]);

        $this->assertTrue(
            $assignment->is_report_owner
        );

        $this->assertSame(
            $student->id,
            $assignment->student_id
        );

        $this->assertNull(
            $assignment->ends_on
        );
    }

    public function test_service_can_update_assignment(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $assignment = $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
            'is_report_owner' => false,
        ]);

        $newTeacher = $this->makeTeacher();

        $updated = $this->service->update(
            $assignment,
            [
                'student_id' => $student->id,
                'teacher_id' => $newTeacher->id,
                'starts_on' => '2026-11-01',
                'ends_on' => '2027-01-31',
                'is_report_owner' => true,
            ]
        );

        $this->assertSame(
            $assignment->id,
            $updated->id
        );

        $this->assertSame(
            $student->id,
            $updated->student_id
        );

        $this->assertSame(
            $newTeacher->id,
            $updated->teacher_id
        );

        $this->assertSame(
            '2026-11-01',
            $updated->starts_on->toDateString()
        );

        $this->assertSame(
            '2027-01-31',
            $updated->ends_on->toDateString()
        );

        $this->assertTrue(
            $updated->is_report_owner
        );
    }

    public function test_service_rejects_end_date_before_start_date(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $this->expectException(
            ValidationException::class
        );

        $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-01',
            'is_report_owner' => false,
        ]);
    }

    public function test_service_accepts_same_start_and_end_date(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $assignment = $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-10',
            'is_report_owner' => false,
        ]);

        $this->assertSame(
            '2026-10-10',
            $assignment->starts_on->toDateString()
        );

        $this->assertSame(
            '2026-10-10',
            $assignment->ends_on->toDateString()
        );
    }

    public function test_service_rejects_nonexistent_student(): void
    {
        $teacher = $this->makeTeacher();

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->create([
            'student_id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
            'is_report_owner' => false,
        ]);
    }

    public function test_service_rejects_nonexistent_teacher(): void
    {
        $student = $this->makeStudent();

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => (string) Str::uuid(),
            'starts_on' => '2026-10-01',
            'ends_on' => null,
            'is_report_owner' => false,
        ]);
    }

    public function test_service_can_create_multiple_report_owner_assignments_when_previous_assignment_has_end_date(): void
    {
        $student = $this->makeStudent();

        $teacherOne = $this->makeTeacher();
        $teacherTwo = $this->makeTeacher();

        $first = $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacherOne->id,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
            'is_report_owner' => true,
        ]);

        $second = $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacherTwo->id,
            'starts_on' => '2026-11-01',
            'ends_on' => null,
            'is_report_owner' => true,
        ]);

        $this->assertNotSame(
            $first->id,
            $second->id
        );

        $this->assertTrue(
            $first->is_report_owner
        );

        $this->assertTrue(
            $second->is_report_owner
        );

        $this->assertSame(
            '2026-10-31',
            $first->ends_on->toDateString()
        );

        $this->assertNull(
            $second->ends_on
        );
    }

    public function test_service_does_not_apply_unrequested_overlap_rule(): void
    {
        $student = $this->makeStudent();

        $teacherOne = $this->makeTeacher();
        $teacherTwo = $this->makeTeacher();

        $first = $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacherOne->id,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
            'is_report_owner' => false,
        ]);

        $second = $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacherTwo->id,
            'starts_on' => '2026-11-01',
            'ends_on' => '2027-01-31',
            'is_report_owner' => false,
        ]);

        $this->assertNotSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'student_teacher_assignments',
            2
        );
    }

    public function test_service_preserves_report_owner_flag_on_update(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $assignment = $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
            'is_report_owner' => true,
        ]);

        $updated = $this->service->update(
            $assignment,
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'starts_on' => '2026-10-05',
                'ends_on' => null,
                'is_report_owner' => true,
            ]
        );

        $this->assertTrue(
            $updated->is_report_owner
        );

        $this->assertNull(
            $updated->ends_on
        );
    }

    public function test_service_can_remove_report_owner_flag_on_update(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $assignment = $this->service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01',
            'ends_on' => null,
            'is_report_owner' => true,
        ]);

        $updated = $this->service->update(
            $assignment,
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'starts_on' => '2026-10-01',
                'ends_on' => null,
                'is_report_owner' => false,
            ]
        );

        $this->assertFalse(
            $updated->is_report_owner
        );
    }
}
