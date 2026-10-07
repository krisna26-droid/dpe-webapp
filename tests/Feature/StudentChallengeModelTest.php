<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentChallenge;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentChallengeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_challenge_has_student_relation(): void
    {
        $student = $this->makeStudent();

        $challenge = $this->makeChallenge(
            student: $student
        );

        $this->assertTrue(
            $challenge->student->is($student)
        );
    }

    public function test_student_challenge_has_teacher_relation(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $challenge = $this->makeChallenge(
            student: $student,
            teacher: $teacher
        );

        $this->assertTrue(
            $challenge->teacher->is($teacher)
        );
    }

    public function test_student_challenge_has_nullable_session_relation(): void
    {
        $student = $this->makeStudent();

        $challenge = $this->makeChallenge(
            student: $student
        );

        $this->assertNull(
            $challenge->session
        );
    }

    public function test_student_challenge_has_session_relation(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();
        $student = $this->makeStudent();

        $session = $this->makeSession(
            $branch,
            $teacher,
            $program
        );

        $challenge = $this->makeChallenge(
            student: $student,
            teacher: $teacher,
            session: $session
        );

        $this->assertTrue(
            $challenge->session->is($session)
        );
    }

    public function test_student_challenge_casts_logged_on_to_date(): void
    {
        $student = $this->makeStudent();

        $challenge = $this->makeChallenge(
            student: $student
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $challenge->logged_on
        );
    }

    public function test_student_challenge_casts_created_at_to_datetime(): void
    {
        $student = $this->makeStudent();

        $challenge = $this->makeChallenge(
            student: $student
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $challenge->created_at
        );
    }

    public function test_student_challenge_has_no_updated_at(): void
    {
        $student = $this->makeStudent();

        $challenge = $this->makeChallenge(
            student: $student
        );

        $this->assertNull(
            $challenge->getUpdatedAtColumn()
        );
    }

    private function makeChallenge(
        Student $student,
        ?Teacher $teacher = null,
        ?LessonSession $session = null
    ): StudentChallenge {
        $teacher ??= $this->makeTeacher();

        return StudentChallenge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => $session?->id,
            'category_name' => 'Pronunciation',
            'internal_note' => 'Student needs additional practice.',
            'logged_on' => '2026-10-06',
        ]);
    }

    private function makeUser(
        string $roleCode
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '-' . Str::random(8),
            'email' => Str::random(8) . '@example.com',
            'full_name' => 'Test ' . ucfirst($roleCode),
            'password_hash' => bcrypt('password'),
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
            'began_on' => '2026-01-01',
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

    private function makeBranch(): Branch
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

    private function makeProgram(): Program
    {
        return Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-' . strtoupper(Str::random(6)),
            'name' => 'Test Program',
            'class_type' => 'private',
            'description' => null,
            'is_active' => true,
        ]);
    }

    private function makeSession(
        Branch $branch,
        Teacher $teacher,
        Program $program
    ): LessonSession {
        return LessonSession::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => '2026-10-06 09:00:00',
            'planned_end_at' => '2026-10-06 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'Pronunciation',
            'material' => null,
            'activity' => null,
        ]);
    }
}
