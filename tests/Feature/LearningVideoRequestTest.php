<?php

namespace Tests\Feature;

use App\Http\Requests\LearningVideo\StoreLearningVideoRequest;
use App\Http\Requests\LearningVideo\UpdateLearningVideoRequest;
use App\Models\Branch;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LearningVideoRequestTest extends TestCase
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
            'password_hash' => bcrypt('password'),
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
            'began_on' => '2026-09-01',
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
        string $branchId,
        string $teacherId,
        string $programId
    ): LessonSession {
        return LessonSession::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branchId,
            'teacher_id' => $teacherId,
            'program_id' => $programId,
            'class_group_id' => null,
            'planned_start_at' => '2026-10-06 09:00:00',
            'planned_end_at' => '2026-10-06 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'Test Topic',
            'material' => null,
            'activity' => null,
        ]);
    }

    private function validData(): array
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        return [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'video_on' => '2026-10-06',
            'topic' => 'Vocabulary Practice',
            'description' => 'Learning video description.',
            'video_url' => 'https://example.com/video.mp4',
        ];
    }

    public function test_store_request_accepts_valid_data(): void
    {
        $user = $this->makeUser('superadmin');
        $this->actingAs($user);

        $data = $this->validData();

        $request = StoreLearningVideoRequest::create(
            '/learning-videos',
            'POST',
            $data
        );

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_update_request_accepts_valid_data(): void
    {
        $user = $this->makeUser('superadmin');
        $this->actingAs($user);

        $data = $this->validData();

        $request = UpdateLearningVideoRequest::create(
            '/learning-videos/test',
            'PUT',
            $data
        );

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_rejects_missing_student(): void
    {
        $data = $this->validData();
        unset($data['student_id']);

        $request = new StoreLearningVideoRequest();

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'student_id',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_rejects_missing_teacher(): void
    {
        $data = $this->validData();
        unset($data['teacher_id']);

        $request = new StoreLearningVideoRequest();

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'teacher_id',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_rejects_missing_video_date(): void
    {
        $data = $this->validData();
        unset($data['video_on']);

        $request = new StoreLearningVideoRequest();

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'video_on',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_rejects_invalid_video_date(): void
    {
        $data = $this->validData();
        $data['video_on'] = '06-10-2026';

        $request = new StoreLearningVideoRequest();

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'video_on',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_rejects_missing_topic(): void
    {
        $data = $this->validData();
        unset($data['topic']);

        $request = new StoreLearningVideoRequest();

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'topic',
            $validator->errors()->toArray()
        );
    }

    public function test_store_request_accepts_nullable_session(): void
    {
        $data = $this->validData();
        $data['session_id'] = null;

        $request = new StoreLearningVideoRequest();

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_accepts_existing_session(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $branch = $this->makeBranch();
        $program = $this->makeProgram();

        $session = $this->makeSession(
            $branch->id,
            $teacher->id,
            $program->id
        );

        $data = [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => $session->id,
            'video_on' => '2026-10-06',
            'topic' => 'Vocabulary Practice',
            'description' => null,
            'video_url' => 'https://example.com/video.mp4',
        ];

        $request = new StoreLearningVideoRequest();

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_rejects_nonexistent_session(): void
    {
        $data = $this->validData();
        $data['session_id'] = (string) Str::uuid();

        $request = new StoreLearningVideoRequest();

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'session_id',
            $validator->errors()->toArray()
        );
    }

    public function test_authorize_accepts_active_superadmin(): void
    {
        $user = $this->makeUser('superadmin', true);
        $this->actingAs($user);

        $request = StoreLearningVideoRequest::create(
            '/learning-videos',
            'POST'
        );

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_rejects_non_superadmin(): void
    {
        $user = $this->makeUser('teacher', true);
        $this->actingAs($user);

        $request = StoreLearningVideoRequest::create(
            '/learning-videos',
            'POST'
        );

        $this->assertFalse($request->authorize());
    }

    public function test_authorize_rejects_inactive_superadmin(): void
    {
        $user = $this->makeUser('superadmin', false);
        $this->actingAs($user);

        $request = StoreLearningVideoRequest::create(
            '/learning-videos',
            'POST'
        );

        $this->assertFalse($request->authorize());
    }
}
