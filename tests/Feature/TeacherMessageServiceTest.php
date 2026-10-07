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
use App\Services\TeacherMessageService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private TeacherMessageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TeacherMessageService::class);
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
            'whatsapp_number' => '628' . random_int(1000000000, 9999999999),
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
            'storage_key' => 'teacher-messages/' . Str::uuid() . '.txt',
            'original_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 10,
        ]);
    }

    private function validData(Student $student, Teacher $teacher): array
    {
        return [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'message_body' => 'Test teacher message.',
            'attachment_file_id' => null,
        ];
    }

    public function test_service_can_create_teacher_message_without_optional_relations(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $message = $this->service->create(
            $this->validData($student, $teacher)
        );

        $this->assertInstanceOf(TeacherMessage::class, $message);
        $this->assertNotEmpty($message->id);
        $this->assertSame($student->id, $message->student_id);
        $this->assertSame($teacher->id, $message->teacher_id);
        $this->assertNull($message->session_id);
        $this->assertSame('Test teacher message.', $message->message_body);
        $this->assertNull($message->attachment_file_id);

        $this->assertDatabaseHas('teacher_messages', [
            'id' => $message->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'message_body' => 'Test teacher message.',
            'attachment_file_id' => null,
        ]);
    }

    public function test_service_can_create_teacher_message_with_session_and_attachment(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $session = $this->makeSession($teacher);
        $attachment = $this->makeFileAsset();

        $data = $this->validData($student, $teacher);
        $data['session_id'] = $session->id;
        $data['message_body'] = 'Message with supporting material.';
        $data['attachment_file_id'] = $attachment->id;

        $message = $this->service->create($data);

        $this->assertSame($session->id, $message->session_id);
        $this->assertSame($attachment->id, $message->attachment_file_id);
        $this->assertSame(
            'Message with supporting material.',
            $message->message_body
        );

        $this->assertDatabaseHas('teacher_messages', [
            'id' => $message->id,
            'session_id' => $session->id,
            'attachment_file_id' => $attachment->id,
        ]);
    }

    public function test_service_can_update_teacher_message_fields_and_optional_relations(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $message = $this->service->create(
            $this->validData($student, $teacher)
        );

        $session = $this->makeSession($teacher);
        $attachment = $this->makeFileAsset();
        $updated = $this->service->update(
            $message,
            [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'session_id' => $session->id,
                'message_body' => 'Updated teacher message.',
                'attachment_file_id' => $attachment->id,
            ]
        );

        $this->assertSame($message->id, $updated->id);
        $this->assertSame($student->id, $updated->student_id);
        $this->assertSame($teacher->id, $updated->teacher_id);
        $this->assertSame($session->id, $updated->session_id);
        $this->assertSame(
            'Updated teacher message.',
            $updated->message_body
        );
        $this->assertSame($attachment->id, $updated->attachment_file_id);

        $this->assertDatabaseHas('teacher_messages', [
            'id' => $message->id,
            'session_id' => $session->id,
            'message_body' => 'Updated teacher message.',
            'attachment_file_id' => $attachment->id,
        ]);
    }

    public function test_service_can_clear_optional_session_and_attachment(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $session = $this->makeSession($teacher);
        $attachment = $this->makeFileAsset();

        $data = $this->validData($student, $teacher);
        $data['session_id'] = $session->id;
        $data['attachment_file_id'] = $attachment->id;
        $message = $this->service->create($data);

        $updated = $this->service->update(
            $message,
            $this->validData($student, $teacher)
        );

        $this->assertNull($updated->session_id);
        $this->assertNull($updated->attachment_file_id);

        $this->assertDatabaseHas('teacher_messages', [
            'id' => $message->id,
            'session_id' => null,
            'attachment_file_id' => null,
        ]);
    }

    public function test_service_can_delete_teacher_message(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $message = $this->service->create(
            $this->validData($student, $teacher)
        );

        $this->service->delete($message);

        $this->assertDatabaseMissing('teacher_messages', [
            'id' => $message->id,
        ]);
    }

    public function test_service_rejects_nonexistent_student_on_create(): void
    {
        $teacher = $this->makeTeacher();
        $data = $this->validData(
            new Student(['id' => (string) Str::uuid()]),
            $teacher
        );

        $this->expectException(ModelNotFoundException::class);

        $this->service->create($data);
    }

    public function test_service_rejects_nonexistent_teacher_on_create(): void
    {
        $student = $this->makeStudent();
        $data = $this->validData(
            $student,
            new Teacher(['id' => (string) Str::uuid()])
        );

        $this->expectException(ModelNotFoundException::class);

        $this->service->create($data);
    }

    public function test_service_rejects_nonexistent_session_on_create(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $data = $this->validData($student, $teacher);
        $data['session_id'] = (string) Str::uuid();

        $this->expectException(ModelNotFoundException::class);

        $this->service->create($data);
    }

    public function test_service_rejects_nonexistent_attachment_on_create(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $data = $this->validData($student, $teacher);
        $data['attachment_file_id'] = (string) Str::uuid();

        $this->expectException(ModelNotFoundException::class);

        $this->service->create($data);
    }

    public function test_service_rejects_nonexistent_related_records_on_update(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $message = $this->service->create(
            $this->validData($student, $teacher)
        );
        $data = $this->validData($student, $teacher);
        $data['attachment_file_id'] = (string) Str::uuid();

        $this->expectException(ModelNotFoundException::class);

        $this->service->update($message, $data);
    }
}