<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LearningVideo;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningVideoHttpTest extends TestCase
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
            'full_name' => 'Test ' . ucfirst($roleCode),
            'password_hash' => bcrypt('password'),
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

    public function test_guest_is_redirected_from_learning_video_index(): void
    {
        $response = $this->get(
            route('superadmin.learning-videos.index')
        );

        $response->assertRedirect();
    }

    public function test_non_superadmin_cannot_access_learning_video_index(): void
    {
        $user = $this->makeUser('teacher');

        $response = $this
            ->actingAs($user)
            ->get(route('superadmin.learning-videos.index'));

        $response->assertForbidden();
    }

    public function test_inactive_superadmin_cannot_access_learning_video_index(): void
    {
        $user = $this->makeUser('superadmin', false);

        $response = $this
            ->actingAs($user)
            ->get(route('superadmin.learning-videos.index'));

        $response->assertForbidden();
    }

    public function test_superadmin_can_view_learning_video_index(): void
    {
        $user = $this->makeUser('superadmin');

        $response = $this
            ->actingAs($user)
            ->get(route('superadmin.learning-videos.index'));

        $response
            ->assertOk()
            ->assertViewIs('learning-videos.index');
    }

    public function test_superadmin_can_view_create_form(): void
    {
        $user = $this->makeUser('superadmin');

        $response = $this
            ->actingAs($user)
            ->get(route('superadmin.learning-videos.create'));

        $response
            ->assertOk()
            ->assertViewIs('learning-videos.create');
    }

    public function test_superadmin_can_store_learning_video(): void
    {
        $user = $this->makeUser('superadmin');

        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $data = [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'video_on' => '2026-10-06',
            'topic' => 'Vocabulary Practice',
            'description' => 'Test learning video',
            'video_url' => 'https://example.com/video.mp4',
        ];

        $response = $this
            ->actingAs($user)
            ->post(
                route('superadmin.learning-videos.store'),
                $data
            );

        $learningVideo = LearningVideo::query()
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($learningVideo);

        $response->assertRedirect(
            route(
                'superadmin.learning-videos.show',
                $learningVideo
            )
        );

        $response->assertSessionHas(
            'success',
            'Learning video berhasil dibuat.'
        );

        $this->assertDatabaseHas('learning_videos', [
            'id' => $learningVideo->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'topic' => 'Vocabulary Practice',
            'video_url' => 'https://example.com/video.mp4',
        ]);
    }

    public function test_superadmin_can_view_learning_video(): void
    {
        $user = $this->makeUser('superadmin');
        $learningVideo = $this->makeLearningVideo();

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'superadmin.learning-videos.show',
                    $learningVideo
                )
            );

        $response
            ->assertOk()
            ->assertViewIs('learning-videos.show')
            ->assertViewHas('learningVideo');
    }

    public function test_superadmin_can_view_edit_form(): void
    {
        $user = $this->makeUser('superadmin');
        $learningVideo = $this->makeLearningVideo();

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'superadmin.learning-videos.edit',
                    $learningVideo
                )
            );

        $response
            ->assertOk()
            ->assertViewIs('learning-videos.edit')
            ->assertViewHas('learningVideo');
    }

    public function test_superadmin_can_update_learning_video(): void
    {
        $user = $this->makeUser('superadmin');

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

        $response = $this
            ->actingAs($user)
            ->put(
                route(
                    'superadmin.learning-videos.update',
                    $learningVideo
                ),
                $data
            );

        $response->assertRedirect(
            route(
                'superadmin.learning-videos.show',
                $learningVideo
            )
        );

        $response->assertSessionHas(
            'success',
            'Learning video berhasil diperbarui.'
        );

        $this->assertDatabaseHas('learning_videos', [
            'id' => $learningVideo->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'topic' => 'Updated Topic',
            'video_url' => 'https://example.com/updated.mp4',
        ]);
    }

    public function test_superadmin_can_delete_learning_video(): void
    {
        $user = $this->makeUser('superadmin');
        $learningVideo = $this->makeLearningVideo();

        $response = $this
            ->actingAs($user)
            ->delete(
                route(
                    'superadmin.learning-videos.destroy',
                    $learningVideo
                )
            );

        $response->assertRedirect(
            route('superadmin.learning-videos.index')
        );

        $response->assertSessionHas(
            'success',
            'Learning video berhasil dihapus.'
        );

        $this->assertDatabaseMissing('learning_videos', [
            'id' => $learningVideo->id,
        ]);
    }
}
