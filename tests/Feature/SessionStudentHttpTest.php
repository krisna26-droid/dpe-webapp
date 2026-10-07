<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionStudentHttpTest extends TestCase
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

    private function makeEvent(
        ?LessonSession $session = null,
        ?Student $student = null
    ): SessionStudent {
        $session ??= $this->makeLessonSession();
        $student ??= $this->makeStudent();

        return SessionStudent::query()->create([
            'session_id' => $session->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
            'individual_learning_note' => 'Good progress.',
            'recorded_at' => now(),
        ]);
    }

    public function test_guest_cannot_access_session_students(): void
    {
        $response = $this->getJson(
            '/superadmin/session-students/' .
                Str::uuid() . '/' .
                Str::uuid()
        );

        $response->assertUnauthorized();
    }

    public function test_non_superadmin_cannot_access_session_students(): void
    {
        $user = $this->makeUser('teacher');

        $response = $this->actingAs($user)->getJson(
            '/superadmin/session-students/' .
                Str::uuid() . '/' .
                Str::uuid()
        );

        $response->assertForbidden();
    }

    public function test_can_store_session_student(): void
    {
        $user = $this->makeUser('superadmin');
        $session = $this->makeLessonSession();
        $student = $this->makeStudent();

        $response = $this->actingAs($user)->postJson(
            '/superadmin/session-students',
            [
                'session_id' => $session->id,
                'student_id' => $student->id,
                'attendance_status' => 'present',
                'individual_learning_note' => 'Good progress.',
                'recorded_at' => now()->toDateTimeString(),
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'session_id',
                $session->id
            )
            ->assertJsonPath(
                'student_id',
                $student->id
            )
            ->assertJsonPath(
                'attendance_status',
                'present'
            )
            ->assertJsonPath(
                'individual_learning_note',
                'Good progress.'
            );

        $this->assertDatabaseHas('session_students', [
            'session_id' => $session->id,
            'student_id' => $student->id,
            'attendance_status' => 'present',
        ]);
    }

    public function test_can_show_session_student(): void
    {
        $user = $this->makeUser('superadmin');
        $sessionStudent = $this->makeEvent();

        $response = $this->actingAs($user)->getJson(
            '/superadmin/session-students/' .
                $sessionStudent->session_id . '/' .
                $sessionStudent->student_id
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'session_id',
                $sessionStudent->session_id
            )
            ->assertJsonPath(
                'student_id',
                $sessionStudent->student_id
            )
            ->assertJsonPath(
                'attendance_status',
                'present'
            );
    }

    public function test_can_get_session_students_by_session(): void
    {
        $user = $this->makeUser('superadmin');
        $session = $this->makeLessonSession();

        $this->makeEvent(
            $session,
            $this->makeStudent()
        );

        $this->makeEvent(
            $session,
            $this->makeStudent()
        );

        $response = $this->actingAs($user)->getJson(
            '/superadmin/lesson-sessions/' .
                $session->id .
                '/students'
        );

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_can_get_session_students_by_student(): void
    {
        $user = $this->makeUser('superadmin');
        $student = $this->makeStudent();

        $this->makeEvent(
            $this->makeLessonSession(),
            $student
        );

        $this->makeEvent(
            $this->makeLessonSession(),
            $student
        );

        $response = $this->actingAs($user)->getJson(
            '/superadmin/students/' .
                $student->id .
                '/lesson-sessions'
        );

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_can_update_session_student(): void
    {
        $user = $this->makeUser('superadmin');
        $sessionStudent = $this->makeEvent();

        $response = $this->actingAs($user)->putJson(
            '/superadmin/session-students/' .
                $sessionStudent->session_id . '/' .
                $sessionStudent->student_id,
            [
                'session_id' => $sessionStudent->session_id,
                'student_id' => $sessionStudent->student_id,
                'attendance_status' => 'absent',
                'individual_learning_note' => 'Updated note.',
                'recorded_at' => now()->toDateTimeString(),
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'session_id',
                $sessionStudent->session_id
            )
            ->assertJsonPath(
                'student_id',
                $sessionStudent->student_id
            )
            ->assertJsonPath(
                'attendance_status',
                'absent'
            )
            ->assertJsonPath(
                'individual_learning_note',
                'Updated note.'
            );

        $this->assertDatabaseHas('session_students', [
            'session_id' => $sessionStudent->session_id,
            'student_id' => $sessionStudent->student_id,
            'attendance_status' => 'absent',
            'individual_learning_note' => 'Updated note.',
        ]);
    }

    public function test_can_destroy_session_student(): void
    {
        $user = $this->makeUser('superadmin');
        $sessionStudent = $this->makeEvent();

        $response = $this->actingAs($user)->deleteJson(
            '/superadmin/session-students/' .
                $sessionStudent->session_id . '/' .
                $sessionStudent->student_id
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Session student deleted successfully.'
            );

        $this->assertDatabaseMissing('session_students', [
            'session_id' => $sessionStudent->session_id,
            'student_id' => $sessionStudent->student_id,
        ]);
    }
}
