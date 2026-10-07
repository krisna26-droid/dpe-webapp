<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\SessionStudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionStudentServiceTest extends TestCase
{
    use RefreshDatabase;

    private SessionStudentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SessionStudentService::class);
    }

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
        $portalUser = $this->makeUser('student');

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Test Student',
            'photo_file_id' => null,
            'school_name' => null,
            'grade_name' => null,
            'began_on' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'special_notes_internal' => null,
        ]);
    }

    private function makeTeacher(): Teacher
    {
        $teacherUser = $this->makeUser('teacher');

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $teacherUser->id,
            'whatsapp_number' => '081234567890',
            'photo_file_id' => null,
            'is_active' => true,
        ]);
    }

    private function makeBranch(): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => null,
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ]);
    }

    private function makeProgram(): Program
    {
        return Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-' . Str::upper(Str::random(6)),
            'name' => 'Test Program',
            'class_type' => 'private',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ]);
    }

    private function makeLessonSession(): LessonSession
    {
        $branch = $this->makeBranch();
        $teacher = $this->makeTeacher();
        $program = $this->makeProgram();

        $session = new LessonSession();

        $session->forceFill([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => now()->startOfHour(),
            'planned_end_at' => now()->startOfHour()->addHour(),
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'planned',
            'rescheduled_from_session_id' => null,
            'topic' => 'Test Topic',
            'material' => 'Test Material',
            'activity' => 'Test Activity',
        ]);

        $session->save();

        return $session;
    }

    private function makeSessionStudent(): SessionStudent
    {
        $session = $this->makeLessonSession();
        $student = $this->makeStudent();

        return $this->service->create([
            'session_id' => $session->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
            'individual_learning_note' => 'Good progress.',
            'recorded_at' => now(),
        ]);
    }

    public function test_can_create_session_student(): void
    {
        $session = $this->makeLessonSession();
        $student = $this->makeStudent();

        $sessionStudent = $this->service->create([
            'session_id' => $session->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
            'individual_learning_note' => 'Good progress.',
            'recorded_at' => now(),
        ]);

        $this->assertInstanceOf(
            SessionStudent::class,
            $sessionStudent
        );

        $this->assertSame(
            $session->id,
            $sessionStudent->session_id
        );

        $this->assertSame(
            $student->id,
            $sessionStudent->student_id
        );

        $this->assertSame(
            'present',
            $sessionStudent->attendance_status
        );

        $this->assertDatabaseHas('session_students', [
            'session_id' => $session->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
        ]);
    }

    public function test_can_find_by_composite_key(): void
    {
        $sessionStudent = $this->makeSessionStudent();

        $found = $this->service->find(
            $sessionStudent->session_id,
            $sessionStudent->student_id
        );

        $this->assertInstanceOf(
            SessionStudent::class,
            $found
        );

        $this->assertSame(
            $sessionStudent->session_id,
            $found->session_id
        );

        $this->assertSame(
            $sessionStudent->student_id,
            $found->student_id
        );
    }

    public function test_find_throws_when_composite_key_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->find(
            (string) Str::uuid(),
            (string) Str::uuid()
        );
    }

    public function test_can_get_by_session(): void
    {
        $session = $this->makeLessonSession();

        $studentOne = $this->makeStudent();
        $studentTwo = $this->makeStudent();

        $this->service->create([
            'session_id' => $session->id,
            'student_id' => $studentOne->id,
            'attendance_status' => 'present',
            'individual_learning_note' => 'Good.',
            'recorded_at' => now(),
        ]);

        $this->service->create([
            'session_id' => $session->id,
            'student_id' => $studentTwo->id,
            'attendance_status' => 'absent',
            'individual_learning_note' => 'Absent today.',
            'recorded_at' => now(),
        ]);

        $result = $this->service->getBySession($session);

        $this->assertCount(2, $result);
        $this->assertTrue(
            $result->pluck('student_id')->contains($studentOne->id)
        );
        $this->assertTrue(
            $result->pluck('student_id')->contains($studentTwo->id)
        );
    }

    public function test_can_get_by_student(): void
    {
        $sessionOne = $this->makeLessonSession();
        $sessionTwo = $this->makeLessonSession();

        $student = $this->makeStudent();

        $this->service->create([
            'session_id' => $sessionOne->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
            'individual_learning_note' => 'Good.',
            'recorded_at' => now(),
        ]);

        $this->service->create([
            'session_id' => $sessionTwo->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
            'individual_learning_note' => 'Improving.',
            'recorded_at' => now(),
        ]);

        $result = $this->service->getByStudent($student);

        $this->assertCount(2, $result);
        $this->assertTrue(
            $result->pluck('session_id')->contains($sessionOne->id)
        );
        $this->assertTrue(
            $result->pluck('session_id')->contains($sessionTwo->id)
        );
    }

    public function test_can_update_by_composite_key(): void
    {
        $sessionStudent = $this->makeSessionStudent();

        $updated = $this->service->update(
            $sessionStudent->session_id,
            $sessionStudent->student_id,
            [
                'session_id' => $sessionStudent->session_id,
                'student_id' => $sessionStudent->student_id,
                'attendance_status' => 'absent',
                'individual_learning_note' => 'Updated note.',
                'recorded_at' => now(),
            ]
        );

        $this->assertSame(
            'absent',
            $updated->attendance_status
        );

        $this->assertSame(
            'Updated note.',
            $updated->individual_learning_note
        );

        $this->assertDatabaseHas('session_students', [
            'session_id' => $sessionStudent->session_id,
            'student_id' => $sessionStudent->student_id,
            'attendance_status' => 'absent',
            'individual_learning_note' => 'Updated note.',
        ]);
    }

    public function test_update_can_change_composite_key(): void
    {
        $sessionStudent = $this->makeSessionStudent();

        $newSession = $this->makeLessonSession();
        $newStudent = $this->makeStudent();

        $updated = $this->service->update(
            $sessionStudent->session_id,
            $sessionStudent->student_id,
            [
                'session_id' => $newSession->id,
                'student_id' => $newStudent->id,
                'attendance_status' => 'present',
                'individual_learning_note' => 'Moved to another session.',
                'recorded_at' => now(),
            ]
        );

        $this->assertSame(
            $newSession->id,
            $updated->session_id
        );

        $this->assertSame(
            $newStudent->id,
            $updated->student_id
        );

        $this->assertDatabaseMissing('session_students', [
            'session_id' => $sessionStudent->session_id,
            'student_id' => $sessionStudent->student_id,
        ]);

        $this->assertDatabaseHas('session_students', [
            'session_id' => $newSession->id,
            'student_id' => $newStudent->id,
            'attendance_status' => 'present',
        ]);
    }

    public function test_update_throws_when_original_composite_key_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->update(
            (string) Str::uuid(),
            (string) Str::uuid(),
            [
                'session_id' => (string) Str::uuid(),
                'student_id' => (string) Str::uuid(),
                'attendance_status' => 'present',
                'individual_learning_note' => null,
                'recorded_at' => now(),
            ]
        );
    }

    public function test_can_delete_by_composite_key(): void
    {
        $sessionStudent = $this->makeSessionStudent();

        $this->service->delete(
            $sessionStudent->session_id,
            $sessionStudent->student_id
        );

        $this->assertDatabaseMissing('session_students', [
            'session_id' => $sessionStudent->session_id,
            'student_id' => $sessionStudent->student_id,
        ]);
    }

    public function test_delete_throws_when_composite_key_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->delete(
            (string) Str::uuid(),
            (string) Str::uuid()
        );
    }
}
