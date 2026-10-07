<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentChallenge;
use App\Models\Teacher;
use App\Models\User;
use App\Services\StudentChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentChallengeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_can_create_student_challenge(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $service = app(StudentChallengeService::class);

        $challenge = $service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Pronunciation',
            'internal_note' => 'Needs additional pronunciation practice.',
            'logged_on' => '2026-10-06 00:00:00',
        ]);

        $this->assertDatabaseHas('student_challenges', [
            'id' => $challenge->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Pronunciation',
            'internal_note' => 'Needs additional pronunciation practice.',
            'logged_on' => '2026-10-06 00:00:00',
        ]);
    }

    public function test_service_can_create_challenge_with_session(): void
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

        $service = app(StudentChallengeService::class);

        $challenge = $service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => $session->id,
            'category_name' => 'Grammar',
            'internal_note' => null,
            'logged_on' => '2026-10-06',
        ]);

        $this->assertDatabaseHas('student_challenges', [
            'id' => $challenge->id,
            'session_id' => $session->id,
            'category_name' => 'Grammar',
        ]);
    }

    public function test_service_can_update_student_challenge(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $challenge = StudentChallenge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Grammar',
            'internal_note' => 'Old note',
            'logged_on' => '2026-10-01',
        ]);

        $service = app(StudentChallengeService::class);

        $updated = $service->update($challenge, [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Pronunciation',
            'internal_note' => 'Updated note',
            'logged_on' => '2026-10-06',
        ]);

        $this->assertSame(
            $challenge->id,
            $updated->id
        );

        $this->assertDatabaseHas('student_challenges', [
            'id' => $challenge->id,
            'category_name' => 'Pronunciation',
            'internal_note' => 'Updated note',
            'logged_on' => '2026-10-06 00:00:00',
        ]);
    }

    public function test_service_can_clear_optional_session(): void
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

        $challenge = StudentChallenge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => $session->id,
            'category_name' => 'Grammar',
            'internal_note' => null,
            'logged_on' => '2026-10-01',
        ]);

        $service = app(StudentChallengeService::class);

        $service->update($challenge, [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Grammar',
            'internal_note' => null,
            'logged_on' => '2026-10-06',
        ]);

        $this->assertDatabaseHas('student_challenges', [
            'id' => $challenge->id,
            'session_id' => null,
        ]);
    }

    public function test_service_can_delete_student_challenge(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $challenge = StudentChallenge::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Vocabulary',
            'internal_note' => null,
            'logged_on' => '2026-10-06',
        ]);

        $service = app(StudentChallengeService::class);

        $service->delete($challenge);

        $this->assertDatabaseMissing('student_challenges', [
            'id' => $challenge->id,
        ]);
    }

    public function test_service_rejects_nonexistent_student(): void
    {
        $teacher = $this->makeTeacher();

        $service = app(StudentChallengeService::class);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $service->create([
            'student_id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'category_name' => 'Grammar',
            'internal_note' => null,
            'logged_on' => '2026-10-06',
        ]);
    }

    public function test_service_rejects_nonexistent_teacher(): void
    {
        $student = $this->makeStudent();

        $service = app(StudentChallengeService::class);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $service->create([
            'student_id' => $student->id,
            'teacher_id' => (string) Str::uuid(),
            'session_id' => null,
            'category_name' => 'Grammar',
            'internal_note' => null,
            'logged_on' => '2026-10-06',
        ]);
    }

    public function test_service_rejects_nonexistent_session(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $service = app(StudentChallengeService::class);

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $service->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => (string) Str::uuid(),
            'category_name' => 'Grammar',
            'internal_note' => null,
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
            'topic' => 'Grammar',
            'material' => null,
            'activity' => null,
        ]);
    }
}
