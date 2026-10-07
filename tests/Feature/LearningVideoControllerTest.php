<?php

namespace Tests\Feature;

use App\Http\Controllers\LearningVideoController;
use App\Http\Requests\LearningVideo\StoreLearningVideoRequest;
use App\Http\Requests\LearningVideo\UpdateLearningVideoRequest;
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
use Illuminate\View\View;
use Tests\TestCase;

class LearningVideoControllerTest extends TestCase
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

    private function makeLearningVideo(
        ?string $sessionId = null
    ): LearningVideo {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        return LearningVideo::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => $sessionId,
            'video_on' => '2026-10-06',
            'topic' => 'Vocabulary Practice',
            'description' => 'Test description',
            'video_url' => 'https://example.com/video.mp4',
        ]);
    }

    private function makeStoreRequest(array $data): StoreLearningVideoRequest
    {
        return new class($data) extends StoreLearningVideoRequest {
            public function __construct(
                private array $testValidatedData
            ) {}

            public function validated($key = null, $default = null)
            {
                if ($key === null) {
                    return $this->testValidatedData;
                }

                return $this->testValidatedData[$key] ?? $default;
            }
        };
    }

    private function makeUpdateRequest(array $data): UpdateLearningVideoRequest
    {
        return new class($data) extends UpdateLearningVideoRequest {
            public function __construct(
                private array $testValidatedData
            ) {}

            public function validated($key = null, $default = null)
            {
                if ($key === null) {
                    return $this->testValidatedData;
                }

                return $this->testValidatedData[$key] ?? $default;
            }
        };
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
            'description' => 'Updated description',
            'video_url' => 'https://example.com/updated-video.mp4',
        ];
    }

    public function test_index_returns_view_with_learning_videos(): void
    {
        $controller = app(LearningVideoController::class);

        $this->makeLearningVideo();

        $response = $controller->index();

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame(
            'learning-videos.index',
            $response->name()
        );

        $this->assertTrue(
            $response->getData()['learningVideos']->total() === 1
        );
    }

    public function test_create_returns_view(): void
    {
        $controller = app(LearningVideoController::class);

        $response = $controller->create();

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame(
            'learning-videos.create',
            $response->name()
        );
    }

    public function test_store_uses_service_and_redirects_to_show(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'video_on' => '2026-10-06',
            'topic' => 'Vocabulary Practice',
            'description' => 'Test description',
            'video_url' => 'https://example.com/video.mp4',
        ];

        $learningVideo = LearningVideo::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'video_on' => '2026-10-06',
            'topic' => 'Vocabulary Practice',
            'description' => 'Test description',
            'video_url' => 'https://example.com/video.mp4',
        ]);

        $service = $this->mock(LearningVideoService::class);

        $service
            ->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($learningVideo);

        $controller = new LearningVideoController($service);

        $response = $controller->store(
            $this->makeStoreRequest($data)
        );

        $this->assertTrue($response->isRedirect());
        $this->assertSame(
            route('superadmin.learning-videos.show', $learningVideo),
            $response->getTargetUrl()
        );
        $this->assertSame(
            'Learning video berhasil dibuat.',
            session('success')
        );
    }

    public function test_show_returns_view_with_learning_video(): void
    {
        $controller = app(LearningVideoController::class);

        $learningVideo = $this->makeLearningVideo();

        $response = $controller->show($learningVideo);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame(
            'learning-videos.show',
            $response->name()
        );
        $this->assertSame(
            $learningVideo->id,
            $response->getData()['learningVideo']->id
        );
    }

    public function test_edit_returns_view_with_learning_video(): void
    {
        $controller = app(LearningVideoController::class);

        $learningVideo = $this->makeLearningVideo();

        $response = $controller->edit($learningVideo);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame(
            'learning-videos.edit',
            $response->name()
        );
        $this->assertSame(
            $learningVideo->id,
            $response->getData()['learningVideo']->id
        );
    }

    public function test_update_uses_service_and_redirects_to_show(): void
    {
        $learningVideo = $this->makeLearningVideo();

        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'video_on' => '2026-10-07',
            'topic' => 'Updated Topic',
            'description' => 'Updated description',
            'video_url' => 'https://example.com/updated.mp4',
        ];

        $updatedLearningVideo = $learningVideo->fill($data);

        $service = $this->mock(LearningVideoService::class);

        $service
            ->shouldReceive('update')
            ->once()
            ->with($learningVideo, $data)
            ->andReturn($updatedLearningVideo);

        $controller = new LearningVideoController($service);

        $response = $controller->update(
            $this->makeUpdateRequest($data),
            $learningVideo
        );

        $this->assertTrue($response->isRedirect());
        $this->assertSame(
            route('superadmin.learning-videos.show', $learningVideo),
            $response->getTargetUrl()
        );
        $this->assertSame(
            'Learning video berhasil diperbarui.',
            session('success')
        );
    }

    public function test_destroy_uses_service_and_redirects_to_index(): void
    {
        $learningVideo = $this->makeLearningVideo();

        $service = $this->mock(LearningVideoService::class);

        $service
            ->shouldReceive('delete')
            ->once()
            ->with($learningVideo);

        $controller = new LearningVideoController($service);

        $response = $controller->destroy($learningVideo);

        $this->assertTrue($response->isRedirect());
        $this->assertSame(
            route('superadmin.learning-videos.index'),
            $response->getTargetUrl()
        );
        $this->assertSame(
            'Learning video berhasil dihapus.',
            session('success')
        );
    }
}
