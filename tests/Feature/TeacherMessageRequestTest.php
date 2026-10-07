<?php

namespace Tests\Feature;

use App\Http\Requests\TeacherMessage\StoreTeacherMessageRequest;
use App\Http\Requests\TeacherMessage\UpdateTeacherMessageRequest;
use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherMessageRequestTest extends TestCase
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

    private function validData(
        Student $student,
        Teacher $teacher
    ): array {
        return [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'message_body' => 'Test teacher message.',
            'attachment_file_id' => null,
        ];
    }

    public function test_store_request_authorizes_active_superadmin(): void
    {
        $user = $this->makeUser('superadmin');

        $this->actingAs($user);

        $request = new StoreTeacherMessageRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_store_request_rejects_non_superadmin(): void
    {
        $user = $this->makeUser('admin');

        $this->actingAs($user);

        $request = new StoreTeacherMessageRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_store_request_rejects_inactive_superadmin(): void
    {
        $user = $this->makeUser('superadmin');

        $user->update([
            'is_active' => false,
        ]);

        $this->actingAs($user);

        $request = new StoreTeacherMessageRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_store_request_accepts_valid_data_without_optional_relations(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        $validator = Validator::make(
            $data,
            (new StoreTeacherMessageRequest())->rules()
        );

        $this->assertTrue($validator->passes());
        $this->assertSame([], $validator->errors()->all());
    }

    public function test_store_request_accepts_valid_data_with_optional_relations(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $session = $this->makeSession($teacher);
        $attachment = $this->makeFileAsset();

        $data = $this->validData(
            $student,
            $teacher
        );

        $data['session_id'] = $session->id;
        $data['attachment_file_id'] = $attachment->id;

        $validator = Validator::make(
            $data,
            (new StoreTeacherMessageRequest())->rules()
        );

        $this->assertTrue($validator->passes());
        $this->assertSame([], $validator->errors()->all());
    }

    public function test_store_request_requires_student_id(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        unset($data['student_id']);

        $validator = Validator::make(
            $data,
            (new StoreTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('student_id')
        );
    }

    public function test_store_request_requires_teacher_id(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        unset($data['teacher_id']);

        $validator = Validator::make(
            $data,
            (new StoreTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('teacher_id')
        );
    }

    public function test_store_request_requires_message_body(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        unset($data['message_body']);

        $validator = Validator::make(
            $data,
            (new StoreTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('message_body')
        );
    }

    public function test_store_request_rejects_nonexistent_student(): void
    {
        $teacher = $this->makeTeacher();

        $data = [
            'student_id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'message_body' => 'Test teacher message.',
            'attachment_file_id' => null,
        ];

        $validator = Validator::make(
            $data,
            (new StoreTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('student_id')
        );
    }

    public function test_store_request_rejects_nonexistent_teacher(): void
    {
        $student = $this->makeStudent();

        $data = [
            'student_id' => $student->id,
            'teacher_id' => (string) Str::uuid(),
            'session_id' => null,
            'message_body' => 'Test teacher message.',
            'attachment_file_id' => null,
        ];

        $validator = Validator::make(
            $data,
            (new StoreTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('teacher_id')
        );
    }

    public function test_store_request_rejects_nonexistent_session(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        $data['session_id'] = (string) Str::uuid();

        $validator = Validator::make(
            $data,
            (new StoreTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('session_id')
        );
    }

    public function test_store_request_rejects_nonexistent_attachment(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        $data['attachment_file_id'] = (string) Str::uuid();

        $validator = Validator::make(
            $data,
            (new StoreTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('attachment_file_id')
        );
    }

    public function test_update_request_authorizes_active_superadmin(): void
    {
        $user = $this->makeUser('superadmin');

        $this->actingAs($user);

        $request = new UpdateTeacherMessageRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_update_request_rejects_non_superadmin(): void
    {
        $user = $this->makeUser('teacher');

        $this->actingAs($user);

        $request = new UpdateTeacherMessageRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_update_request_rejects_inactive_superadmin(): void
    {
        $user = $this->makeUser('superadmin');

        $user->update([
            'is_active' => false,
        ]);

        $this->actingAs($user);

        $request = new UpdateTeacherMessageRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_update_request_accepts_valid_data_without_optional_relations(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        $validator = Validator::make(
            $data,
            (new UpdateTeacherMessageRequest())->rules()
        );

        $this->assertTrue($validator->passes());
        $this->assertSame([], $validator->errors()->all());
    }

    public function test_update_request_accepts_valid_data_with_optional_relations(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $session = $this->makeSession($teacher);
        $attachment = $this->makeFileAsset();

        $data = $this->validData(
            $student,
            $teacher
        );

        $data['session_id'] = $session->id;
        $data['attachment_file_id'] = $attachment->id;

        $validator = Validator::make(
            $data,
            (new UpdateTeacherMessageRequest())->rules()
        );

        $this->assertTrue($validator->passes());
        $this->assertSame([], $validator->errors()->all());
    }

    public function test_update_request_requires_student_id(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        unset($data['student_id']);

        $validator = Validator::make(
            $data,
            (new UpdateTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('student_id')
        );
    }

    public function test_update_request_requires_teacher_id(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        unset($data['teacher_id']);

        $validator = Validator::make(
            $data,
            (new UpdateTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('teacher_id')
        );
    }

    public function test_update_request_requires_message_body(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        unset($data['message_body']);

        $validator = Validator::make(
            $data,
            (new UpdateTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('message_body')
        );
    }

    public function test_update_request_rejects_nonexistent_student(): void
    {
        $teacher = $this->makeTeacher();

        $data = [
            'student_id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'message_body' => 'Test teacher message.',
            'attachment_file_id' => null,
        ];

        $validator = Validator::make(
            $data,
            (new UpdateTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('student_id')
        );
    }

    public function test_update_request_rejects_nonexistent_teacher(): void
    {
        $student = $this->makeStudent();

        $data = [
            'student_id' => $student->id,
            'teacher_id' => (string) Str::uuid(),
            'session_id' => null,
            'message_body' => 'Test teacher message.',
            'attachment_file_id' => null,
        ];

        $validator = Validator::make(
            $data,
            (new UpdateTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('teacher_id')
        );
    }

    public function test_update_request_rejects_nonexistent_session(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        $data['session_id'] = (string) Str::uuid();

        $validator = Validator::make(
            $data,
            (new UpdateTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('session_id')
        );
    }

    public function test_update_request_rejects_nonexistent_attachment(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        $data['attachment_file_id'] = (string) Str::uuid();

        $validator = Validator::make(
            $data,
            (new UpdateTeacherMessageRequest())->rules()
        );

        $this->assertTrue(
            $validator->errors()->has('attachment_file_id')
        );
    }
}
