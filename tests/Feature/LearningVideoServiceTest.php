<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LearningVideo;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LearningVideoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningVideoServiceTest extends TestCase
{
    use RefreshDatabase;

    private LearningVideoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LearningVideoService::class);
    }

    private function makeUser(
        string $roleCode
    ): User {
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
        $user = $this->makeUser('student');

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $user->id,
            'full_name' => 'Test Student',
            'photo_file_id' => null,
            'school_name' => 'Test School',
            'grade_name' => 'Grade 5',
            'began_on' => '2026-09-01',
            'status' => 'active',
            'special_notes_internal' => null,
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
            'monthly_video_target_override' => null,
            'is_active' => true,
        ]);
    }

    private function makeSession(
        Teacher $teacher
    ): LessonSession {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();

        return LessonSession::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => '2026-10-01 09:00:00',
            'planned_end_at' => '2026-10-01 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => null,
            'material' => null,
            'activity' => null,
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
            'video_on' => '2026-10-01',
            'topic' => 'Basic English',
            'description' => 'Basic English learning video',
            'video_url' => 'https://example.com/video/basic',
        ];
    }

    public function test_service_can_create_learning_video(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $video = $this->service->create(
            $this->validData($student, $teacher)
        );

        $this->assertInstanceOf(
            LearningVideo::class,
            $video
        );

        $this->assertSame(
            $student->id,
            $video->student_id
        );

        $this->assertSame(
            $teacher->id,
            $video->teacher_id
        );

        $this->assertNull(
            $video->session_id
        );
    }

    public function test_service_can_create_video_with_session(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $session = $this->makeSession($teacher);

        $data = $this->validData(
            $student,
            $teacher
        );

        $data['session_id'] = $session->id;

        $video = $this->service->create($data);

        $this->assertSame(
            $session->id,
            $video->session_id
        );
    }

    public function test_service_can_update_learning_video(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $video = $this->service->create(
            $this->validData($student, $teacher)
        );

        $updatedData = [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'video_on' => '2026-10-05',
            'topic' => 'Conversation Practice',
            'description' => 'Updated learning material',
            'video_url' => 'https://example.com/video/conversation',
        ];

        $updated = $this->service->update(
            $video,
            $updatedData
        );

        $this->assertSame(
            'Conversation Practice',
            $updated->topic
        );

        $this->assertSame(
            'Updated learning material',
            $updated->description
        );

        $this->assertSame(
            '2026-10-05',
            $updated->video_on->format('Y-m-d')
        );

        $this->assertSame(
            'https://example.com/video/conversation',
            $updated->video_url
        );
    }

    public function test_service_can_clear_optional_session(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $session = $this->makeSession($teacher);

        $data = $this->validData(
            $student,
            $teacher
        );

        $data['session_id'] = $session->id;

        $video = $this->service->create($data);

        $data['session_id'] = null;

        $updated = $this->service->update(
            $video,
            $data
        );

        $this->assertNull(
            $updated->session_id
        );
    }

    public function test_service_can_delete_learning_video(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $video = $this->service->create(
            $this->validData($student, $teacher)
        );

        $this->service->delete($video);

        $this->assertDatabaseMissing(
            'learning_videos',
            [
                'id' => $video->id,
            ]
        );
    }

    public function test_service_rejects_nonexistent_student(): void
    {
        $teacher = $this->makeTeacher();

        $data = [
            'student_id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'video_on' => '2026-10-01',
            'topic' => 'Basic English',
            'description' => null,
            'video_url' => 'https://example.com/video/basic',
        ];

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->create($data);
    }

    public function test_service_rejects_nonexistent_teacher(): void
    {
        $student = $this->makeStudent();

        $data = [
            'student_id' => $student->id,
            'teacher_id' => (string) Str::uuid(),
            'session_id' => null,
            'video_on' => '2026-10-01',
            'topic' => 'Basic English',
            'description' => null,
            'video_url' => 'https://example.com/video/basic',
        ];

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->create($data);
    }

    public function test_service_rejects_nonexistent_session(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = $this->validData(
            $student,
            $teacher
        );

        $data['session_id'] = (string) Str::uuid();

        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->create($data);
    }
}
