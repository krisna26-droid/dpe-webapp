<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherMessageHttpTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleCode): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => strtolower($roleCode)
                . '_'
                . Str::random(8),
            'email' => strtolower($roleCode)
                . '-'
                . Str::random(8)
                . '@example.com',
            'password_hash' => password_hash(
                'password',
                PASSWORD_BCRYPT
            ),
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
            'whatsapp_number' => '628' . random_int(
                1000000000,
                9999999999
            ),
            'is_active' => true,
        ]);
    }

    private function makeSession(Teacher $teacher): LessonSession
    {
        $branch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'name' => 'Test Branch',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 1,
            'is_active' => true,
        ]);

        $program = Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-' . Str::upper(Str::random(6)),
            'name' => 'Test Program',
            'class_type' => 'private',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ]);

        return LessonSession::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => '2026-10-10 09:00:00',
            'planned_end_at' => '2026-10-10 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'Test Session',
            'material' => null,
            'activity' => null,
        ]);
    }

    private function makeFileAsset(): FileAsset
    {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'teacher-messages/'
                . Str::uuid()
                . '.txt',
            'original_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 10,
        ]);
    }

    private function makeTeacherMessage(
        Student $student,
        Teacher $teacher
    ): TeacherMessage {
        return TeacherMessage::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'message_body' => 'Original teacher message.',
            'attachment_file_id' => null,
        ]);
    }

    public function test_superadmin_can_view_teacher_messages(): void
    {
        $user = $this->makeUser('superadmin');

        $this->actingAs($user);

        $response = $this->get(
            route('superadmin.teacher-messages.index')
        );

        $response->assertOk();

        $response->assertViewIs(
            'teacher-messages.index'
        );
    }

    public function test_superadmin_can_create_teacher_message(): void
    {
        $user = $this->makeUser('superadmin');

        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $this->actingAs($user);

        $response = $this->post(
            route('superadmin.teacher-messages.store'),
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => null,
                'message_body' => 'Created through HTTP.',
                'attachment_file_id' => null,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas(
            'teacher_messages',
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'message_body' => 'Created through HTTP.',
                'session_id' => null,
                'attachment_file_id' => null,
            ]
        );
    }

    public function test_superadmin_can_create_teacher_message_with_optional_relations(): void
    {
        $user = $this->makeUser('superadmin');

        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $session = $this->makeSession($teacher);
        $attachment = $this->makeFileAsset();

        $this->actingAs($user);

        $response = $this->post(
            route('superadmin.teacher-messages.store'),
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session->id,
                'message_body' => 'Created with relations.',
                'attachment_file_id' => $attachment->id,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas(
            'teacher_messages',
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session->id,
                'message_body' => 'Created with relations.',
                'attachment_file_id' => $attachment->id,
            ]
        );
    }

    public function test_superadmin_can_view_teacher_message_detail(): void
    {
        $user = $this->makeUser('superadmin');

        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $message = $this->makeTeacherMessage(
            $student,
            $teacher
        );

        $this->actingAs($user);

        $response = $this->get(
            route(
                'superadmin.teacher-messages.show',
                $message
            )
        );

        $response->assertOk();

        $response->assertViewIs(
            'teacher-messages.show'
        );

        $response->assertViewHas(
            'teacherMessage'
        );
    }

    public function test_superadmin_can_update_teacher_message(): void
    {
        $user = $this->makeUser('superadmin');

        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $message = $this->makeTeacherMessage(
            $student,
            $teacher
        );

        $session = $this->makeSession($teacher);
        $attachment = $this->makeFileAsset();

        $this->actingAs($user);

        $response = $this->put(
            route(
                'superadmin.teacher-messages.update',
                $message
            ),
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session->id,
                'message_body' => 'Updated through HTTP.',
                'attachment_file_id' => $attachment->id,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas(
            'teacher_messages',
            [
                'id' => $message->id,
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session->id,
                'message_body' => 'Updated through HTTP.',
                'attachment_file_id' => $attachment->id,
            ]
        );
    }

    public function test_superadmin_can_clear_optional_relations(): void
    {
        $user = $this->makeUser('superadmin');

        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $session = $this->makeSession($teacher);
        $attachment = $this->makeFileAsset();

        $message = TeacherMessage::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => $session->id,
            'message_body' => 'Message before clearing.',
            'attachment_file_id' => $attachment->id,
        ]);

        $this->actingAs($user);

        $response = $this->put(
            route(
                'superadmin.teacher-messages.update',
                $message
            ),
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => null,
                'message_body' => 'Message after clearing.',
                'attachment_file_id' => null,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas(
            'teacher_messages',
            [
                'id' => $message->id,
                'session_id' => null,
                'message_body' => 'Message after clearing.',
                'attachment_file_id' => null,
            ]
        );
    }

    public function test_superadmin_can_delete_teacher_message(): void
    {
        $user = $this->makeUser('superadmin');

        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $message = $this->makeTeacherMessage(
            $student,
            $teacher
        );

        $this->actingAs($user);

        $response = $this->delete(
            route(
                'superadmin.teacher-messages.destroy',
                $message
            )
        );

        $response->assertRedirect(
            route('superadmin.teacher-messages.index')
        );

        $this->assertDatabaseMissing(
            'teacher_messages',
            [
                'id' => $message->id,
            ]
        );
    }

    public function test_guest_cannot_access_teacher_messages(): void
    {
        $response = $this->get(
            route('superadmin.teacher-messages.index')
        );

        $response->assertRedirect();
    }
}
