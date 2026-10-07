<?php

namespace Tests\Feature;

use App\Http\Requests\StudentChallenge\StoreStudentChallengeRequest;
use App\Http\Requests\StudentChallenge\UpdateStudentChallengeRequest;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentChallengeRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_request_authorizes_active_superadmin(): void
    {
        $user = $this->makeUser('superadmin');

        $request = StoreStudentChallengeRequest::create(
            '/student-challenges',
            'POST'
        );

        $this->actingAs($user);

        $this->assertTrue($request->authorize());
    }

    public function test_update_request_authorizes_active_superadmin(): void
    {
        $user = $this->makeUser('superadmin');

        $request = UpdateStudentChallengeRequest::create(
            '/student-challenges/1',
            'PUT'
        );

        $this->actingAs($user);

        $this->assertTrue($request->authorize());
    }

    public function test_store_request_rejects_invalid_data(): void
    {
        $request = StoreStudentChallengeRequest::create(
            '/student-challenges',
            'POST',
            [
                'student_id' => 'not-found',
                'teacher_id' => 'not-found',
                'session_id' => 'not-found',
                'category_name' => '',
                'internal_note' => null,
                'logged_on' => '06-10-2026',
            ]
        );

        $validator = validator(
            $request->all(),
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'student_id',
            $validator->errors()->toArray()
        );
        $this->assertArrayHasKey(
            'teacher_id',
            $validator->errors()->toArray()
        );
        $this->assertArrayHasKey(
            'category_name',
            $validator->errors()->toArray()
        );
        $this->assertArrayHasKey(
            'logged_on',
            $validator->errors()->toArray()
        );
    }

    public function test_update_request_rejects_invalid_data(): void
    {
        $request = UpdateStudentChallengeRequest::create(
            '/student-challenges/1',
            'PUT',
            [
                'student_id' => 'not-found',
                'teacher_id' => 'not-found',
                'session_id' => 'not-found',
                'category_name' => '',
                'internal_note' => null,
                'logged_on' => '06-10-2026',
            ]
        );

        $validator = validator(
            $request->all(),
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'student_id',
            $validator->errors()->toArray()
        );
        $this->assertArrayHasKey(
            'teacher_id',
            $validator->errors()->toArray()
        );
        $this->assertArrayHasKey(
            'category_name',
            $validator->errors()->toArray()
        );
        $this->assertArrayHasKey(
            'logged_on',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_accepts_valid_data_without_session(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $request = StoreStudentChallengeRequest::create(
            '/student-challenges',
            'POST',
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => null,
                'category_name' => 'Pronunciation',
                'internal_note' => 'Needs additional practice.',
                'logged_on' => '2026-10-06',
            ]
        );

        $validator = validator(
            $request->all(),
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_accepts_valid_data_with_session(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $session = $this->makeLessonSession($student, $teacher);

        $request = StoreStudentChallengeRequest::create(
            '/student-challenges',
            'POST',
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session->id,
                'category_name' => 'Grammar',
                'internal_note' => null,
                'logged_on' => '2026-10-06',
            ]
        );

        $validator = validator(
            $request->all(),
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_update_request_accepts_valid_data(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $request = UpdateStudentChallengeRequest::create(
            '/student-challenges/1',
            'PUT',
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => null,
                'category_name' => 'Vocabulary',
                'internal_note' => 'Updated note.',
                'logged_on' => '2026-10-06',
            ]
        );

        $validator = validator(
            $request->all(),
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_category_name_cannot_exceed_255_characters(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $request = StoreStudentChallengeRequest::create(
            '/student-challenges',
            'POST',
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => null,
                'category_name' => str_repeat('A', 256),
                'internal_note' => null,
                'logged_on' => '2026-10-06',
            ]
        );

        $validator = validator(
            $request->all(),
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'category_name',
            $validator->errors()->toArray()
        );
    }

    public function test_logged_on_must_use_y_m_d_format(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $request = StoreStudentChallengeRequest::create(
            '/student-challenges',
            'POST',
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => null,
                'category_name' => 'Pronunciation',
                'internal_note' => null,
                'logged_on' => '2026/10/06',
            ]
        );

        $validator = validator(
            $request->all(),
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'logged_on',
            $validator->errors()->toArray()
        );
    }

    public function test_session_id_is_optional(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $request = StoreStudentChallengeRequest::create(
            '/student-challenges',
            'POST',
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'category_name' => 'Pronunciation',
                'internal_note' => null,
                'logged_on' => '2026-10-06',
            ]
        );

        $validator = validator(
            $request->all(),
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    private function makeUser(string $roleCode): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => strtolower($roleCode) . '_' . Str::random(8),
            'email' => strtolower($roleCode) . '-' . Str::random(8) . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test User',
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
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);
    }

    private function makeLessonSession(
        Student $student,
        Teacher $teacher
    ): LessonSession {
        $branch = \App\Models\Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . strtoupper(Str::random(6)),
            'name' => 'Test Branch',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ]);

        $program = \App\Models\Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-' . strtoupper(Str::random(6)),
            'name' => 'Test Program',
            'class_type' => 'private',
            'description' => null,
            'is_active' => true,
        ]);

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
            'topic' => 'Test Session',
            'material' => null,
            'activity' => null,
        ]);
    }
}
