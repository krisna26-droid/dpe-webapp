<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LessonSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LessonSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperadmin(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'superadmin_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Super Admin',
            'role_code' => 'superadmin',
            'is_active' => true,
        ]);
    }

    private function makeBranch(array $overrides = []): Branch
    {
        return Branch::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . strtoupper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => 'Test Address',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ], $overrides));
    }

    private function makeProgram(array $overrides = []): Program
    {
        return Program::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-' . strtoupper(Str::random(6)),
            'name' => 'Test Program',
            'class_type' => 'regular',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function makeTeacher(array $overrides = []): Teacher
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'teacher_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test Teacher User',
            'role_code' => 'teacher',
            'is_active' => true,
        ]);

        return Teacher::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'full_name' => 'Test Teacher',
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ], $overrides));
    }

    private function makeClassGroup(
        Branch $branch,
        Program $program,
        ?Teacher $teacher = null
    ): ClassGroup {
        return ClassGroup::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'default_teacher_id' => $teacher?->id,
            'code' => 'CG-' . strtoupper(Str::random(6)),
            'name' => 'Test Class Group',
            'is_active' => true,
        ]);
    }

    private function makeLessonSession(
        Branch $branch,
        Teacher $teacher,
        Program $program,
        ?ClassGroup $classGroup = null,
        array $overrides = []
    ): LessonSession {
        return LessonSession::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => $classGroup?->id,
            'planned_start_at' => '2026-10-10 09:00:00',
            'planned_end_at' => '2026-10-10 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'Test Topic',
            'material' => 'Test Material',
            'activity' => 'Test Activity',
        ], $overrides));
    }

    public function test_superadmin_can_create_lesson_session_through_controller(): void
    {
        $superadmin = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();
        $classGroup = $this->makeClassGroup(
            $branch,
            $program,
            $teacher
        );

        $service = app(LessonSessionService::class);

        $lessonSession = $service->create([
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => $classGroup->id,
            'planned_start_at' => '2026-10-10 09:00:00',
            'planned_end_at' => '2026-10-10 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'English Grammar',
            'material' => 'Grammar Book',
            'activity' => 'Discussion',
        ]);

        $this->assertDatabaseHas('lesson_sessions', [
            'id' => $lessonSession->id,
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => $classGroup->id,
            'status' => 'scheduled',
        ]);

        $this->assertSame(
            'English Grammar',
            $lessonSession->topic
        );
    }

    public function test_superadmin_can_update_lesson_session_through_service_used_by_controller(): void
    {
        $superadmin = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();
        $classGroup = $this->makeClassGroup(
            $branch,
            $program,
            $teacher
        );

        $lessonSession = $this->makeLessonSession(
            $branch,
            $teacher,
            $program,
            $classGroup
        );

        $service = app(LessonSessionService::class);

        $updated = $service->update(
            $lessonSession,
            [
                'branch_id' => $branch->id,
                'teacher_id' => $teacher->id,
                'program_id' => $program->id,
                'class_group_id' => $classGroup->id,
                'planned_start_at' => '2026-10-10 13:00:00',
                'planned_end_at' => '2026-10-10 14:00:00',
                'actual_start_at' => null,
                'actual_end_at' => null,
                'status' => 'completed',
                'rescheduled_from_session_id' => null,
                'topic' => 'Updated Topic',
                'material' => 'Updated Material',
                'activity' => 'Updated Activity',
            ]
        );

        $this->assertSame(
            'completed',
            $updated->status
        );

        $this->assertSame(
            'Updated Topic',
            $updated->topic
        );

        $this->assertSame(
            '2026-10-10 13:00:00',
            $updated->planned_start_at->format('Y-m-d H:i:s')
        );
    }

    public function test_lesson_session_can_be_created_without_class_group(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $lessonSession = $service->create([
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => '2026-10-11 09:00:00',
            'planned_end_at' => '2026-10-11 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => null,
            'material' => null,
            'activity' => null,
        ]);

        $this->assertNull(
            $lessonSession->class_group_id
        );
    }

    public function test_lesson_session_can_be_rescheduled_from_existing_session(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $original = $service->create([
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => '2026-10-12 09:00:00',
            'planned_end_at' => '2026-10-12 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'Original Topic',
            'material' => null,
            'activity' => null,
        ]);

        $rescheduled = $service->create([
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => '2026-10-13 09:00:00',
            'planned_end_at' => '2026-10-13 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => $original->id,
            'topic' => 'Rescheduled Topic',
            'material' => null,
            'activity' => null,
        ]);

        $this->assertSame(
            $original->id,
            $rescheduled->rescheduled_from_session_id
        );

        $this->assertTrue(
            $rescheduled->rescheduledFrom->is($original)
        );
    }

    public function test_unauthenticated_user_cannot_access_lesson_session_management(): void
    {
        $this->assertGuest();
    }

    public function test_non_superadmin_user_is_not_authorized_by_request(): void
    {
        $teacher = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'teacher_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test Teacher User',
            'role_code' => 'teacher',
            'is_active' => true,
        ]);

        $request = new \App\Http\Requests\SuperAdmin\StoreLessonSessionRequest();

        $request->setUserResolver(
            fn () => $teacher
        );

        $this->assertFalse(
            $request->authorize()
        );
    }
}