<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherMessageModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_message_has_student_relation(): void
    {
        $student = $this->makeStudent();

        $message = $this->makeTeacherMessage(
            studentId: $student->id
        );

        $this->assertTrue(
            $message->student->is($student)
        );
    }

    public function test_teacher_message_has_teacher_relation(): void
    {
        $teacher = $this->makeTeacher();

        $message = $this->makeTeacherMessage(
            teacherId: $teacher->id
        );

        $this->assertTrue(
            $message->teacher->is($teacher)
        );
    }

    public function test_teacher_message_has_nullable_session_relation(): void
    {
        $message = $this->makeTeacherMessage();

        $this->assertNull(
            $message->session
        );
    }

    public function test_teacher_message_has_session_relation(): void
    {
        $session = $this->makeLessonSession();

        $message = $this->makeTeacherMessage(
            sessionId: $session->id
        );

        $this->assertTrue(
            $message->session->is($session)
        );
    }

    public function test_teacher_message_has_nullable_attachment_relation(): void
    {
        $message = $this->makeTeacherMessage();

        $this->assertNull(
            $message->attachment
        );
    }

    public function test_teacher_message_has_attachment_relation(): void
    {
        $fileAsset = $this->makeFileAsset();

        $message = $this->makeTeacherMessage(
            attachmentFileId: $fileAsset->id
        );

        $this->assertTrue(
            $message->attachment->is($fileAsset)
        );
    }

    public function test_teacher_message_casts_created_at_to_datetime(): void
    {
        $message = $this->makeTeacherMessage();

        $message->refresh();

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $message->created_at
        );
    }

    public function test_teacher_message_has_no_updated_at(): void
    {
        $message = $this->makeTeacherMessage();

        $this->assertNull(
            $message->updated_at
        );
    }

    private function makeTeacherMessage(
        ?string $studentId = null,
        ?string $teacherId = null,
        ?string $sessionId = null,
        ?string $attachmentFileId = null
    ): TeacherMessage {
        $studentId ??= $this->makeStudent()->id;
        $teacherId ??= $this->makeTeacher()->id;

        return TeacherMessage::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $studentId,
            'teacher_id' => $teacherId,
            'session_id' => $sessionId,
            'message_body' => 'Test teacher message.',
            'attachment_file_id' => $attachmentFileId,
        ]);
    }

    private function makeStudent(): Student
    {
        $user = \App\Models\User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'student_' . Str::random(8),
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
            'began_on' => '2026-10-01',
            'status' => 'active',
        ]);
    }

    private function makeTeacher(): Teacher
    {
        $user = \App\Models\User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'teacher_' . Str::random(8),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test Teacher',
            'role_code' => 'teacher',
            'is_active' => true,
        ]);

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'employee_code' => 'TEST-TEACHER-' . Str::upper(
                Str::random(8)
            ),
            'whatsapp_number' => '628' . random_int(
                1000000000,
                9999999999
            ),
        ]);
    }

    private function makeLessonSession(): LessonSession
    {
        $branch = \App\Models\Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'name' => 'Test Branch',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 1,
            'is_active' => true,
        ]);

        $teacher = $this->makeTeacher();
        $program = $this->makeProgram();

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
            'topic' => 'Test Topic',
            'material' => 'Test Material',
            'activity' => 'Test Activity',
        ]);
    }

    private function makeProgram(): \App\Models\Program
    {
        return \App\Models\Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-' . Str::upper(Str::random(6)),
            'name' => 'Test Program',
            'class_type' => 'private',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ]);
    }

    private function makeFileAsset(): FileAsset
    {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'teacher-messages/test.txt',
            'original_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 10,
        ]);
    }
}
