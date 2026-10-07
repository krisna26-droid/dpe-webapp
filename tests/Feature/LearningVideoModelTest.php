<?php

namespace Tests\Feature;

use App\Models\LearningVideo;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningVideoModelTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeVideo(
        ?Student $student = null,
        ?Teacher $teacher = null
    ): LearningVideo {
        $student ??= $this->makeStudent();
        $teacher ??= $this->makeTeacher();

        return LearningVideo::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => null,
            'video_on' => '2026-10-01',
            'topic' => 'Basic English',
            'description' => 'Basic English learning video',
            'video_url' => 'https://example.com/video/basic-english',
        ]);
    }

    public function test_learning_video_has_correct_student_relation(): void
    {
        $student = $this->makeStudent();
        $video = $this->makeVideo($student);

        $this->assertTrue($video->student->is($student));
    }

    public function test_learning_video_has_correct_teacher_relation(): void
    {
        $teacher = $this->makeTeacher();
        $video = $this->makeVideo(null, $teacher);

        $this->assertTrue($video->teacher->is($teacher));
    }

    public function test_learning_video_has_nullable_session_relation(): void
    {
        $video = $this->makeVideo();

        $this->assertNull($video->session_id);
        $this->assertNull($video->session);
    }

    public function test_learning_video_casts_video_on_to_date(): void
    {
        $video = $this->makeVideo();

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $video->video_on
        );

        $this->assertSame(
            '2026-10-01',
            $video->video_on->format('Y-m-d')
        );
    }

    public function test_learning_video_casts_created_at_to_datetime(): void
    {
        $video = $this->makeVideo();

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $video->created_at
        );
    }

    public function test_learning_video_can_store_all_business_fields(): void
    {
        $video = $this->makeVideo();

        $this->assertSame(
            'Basic English',
            $video->topic
        );

        $this->assertSame(
            'Basic English learning video',
            $video->description
        );

        $this->assertSame(
            'https://example.com/video/basic-english',
            $video->video_url
        );

        $this->assertNotNull($video->student_id);
        $this->assertNotNull($video->teacher_id);
    }

    public function test_learning_video_can_reference_lesson_session(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();

        $branch = \App\Models\Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . strtoupper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => null,
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 1,
            'is_active' => true,
        ]);

        $program = \App\Models\Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-' . strtoupper(Str::random(6)),
            'name' => 'Test Program',
            'class_type' => 'private',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ]);

        $session = LessonSession::query()->create([
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
            'topic' => 'Basic English',
            'material' => null,
            'activity' => null,
        ]);

        $video = LearningVideo::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'session_id' => $session->id,
            'video_on' => '2026-10-01',
            'topic' => 'Basic English',
            'description' => null,
            'video_url' => 'https://example.com/video/basic',
        ]);

        $this->assertTrue(
            $video->session->is($session)
        );
    }
}
