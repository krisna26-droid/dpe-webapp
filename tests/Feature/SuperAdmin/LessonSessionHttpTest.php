<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LessonSessionHttpTest extends TestCase
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

    private function makeUser(
        string $roleCode = 'teacher',
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
        $user = $this->makeUser('teacher');

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

    private function validPayload(
        Branch $branch,
        Teacher $teacher,
        Program $program,
        ?ClassGroup $classGroup = null
    ): array {
        return [
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
            'topic' => 'English Grammar',
            'material' => 'Grammar Book',
            'activity' => 'Discussion',
        ];
    }

    public function test_superadmin_can_store_lesson_session_through_http(): void
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

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.lesson-sessions.store'),
                $this->validPayload(
                    $branch,
                    $teacher,
                    $program,
                    $classGroup
                )
            );

        $response->assertRedirect();

        $this->assertDatabaseHas('lesson_sessions', [
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => $classGroup->id,
            'status' => 'scheduled',
            'topic' => 'English Grammar',
            'material' => 'Grammar Book',
            'activity' => 'Discussion',
        ]);
    }

    public function test_superadmin_can_update_lesson_session_through_http(): void
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

        $response = $this
            ->actingAs($superadmin)
            ->put(
                route(
                    'superadmin.lesson-sessions.update',
                    $lessonSession
                ),
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

        $response->assertRedirect();

        $this->assertDatabaseHas('lesson_sessions', [
            'id' => $lessonSession->id,
            'status' => 'completed',
            'topic' => 'Updated Topic',
            'material' => 'Updated Material',
            'activity' => 'Updated Activity',
        ]);
    }

    public function test_guest_cannot_store_lesson_session(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $response = $this->post(
            route('superadmin.lesson-sessions.store'),
            $this->validPayload(
                $branch,
                $teacher,
                $program
            )
        );

        $response->assertRedirect(
            route('login')
        );

        $this->assertDatabaseCount(
            'lesson_sessions',
            0
        );
    }

    public function test_non_superadmin_cannot_store_lesson_session(): void
    {
        $teacherUser = $this->makeUser('teacher');

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $response = $this
            ->actingAs($teacherUser)
            ->post(
                route('superadmin.lesson-sessions.store'),
                $this->validPayload(
                    $branch,
                    $teacher,
                    $program
                )
            );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'lesson_sessions',
            0
        );
    }

    public function test_inactive_superadmin_cannot_store_lesson_session(): void
    {
        $inactiveSuperadmin = $this->makeSuperadmin();

        $inactiveSuperadmin->update([
            'is_active' => false,
        ]);

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $response = $this
            ->actingAs($inactiveSuperadmin)
            ->post(
                route('superadmin.lesson-sessions.store'),
                $this->validPayload(
                    $branch,
                    $teacher,
                    $program
                )
            );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'lesson_sessions',
            0
        );
    }

    public function test_store_rejects_invalid_planned_time(): void
    {
        $superadmin = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $payload = $this->validPayload(
            $branch,
            $teacher,
            $program
        );

        $payload['planned_start_at'] = '2026-10-10 10:00:00';
        $payload['planned_end_at'] = '2026-10-10 09:00:00';

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.lesson-sessions.store'),
                $payload
            );

        $response->assertSessionHasErrors([
            'planned_end_at',
        ]);

        $this->assertDatabaseCount(
            'lesson_sessions',
            0
        );
    }

    public function test_store_rejects_nonexistent_foreign_keys(): void
    {
        $superadmin = $this->makeSuperadmin();

        $payload = [
            'branch_id' => (string) Str::uuid(),
            'teacher_id' => (string) Str::uuid(),
            'program_id' => (string) Str::uuid(),
            'class_group_id' => null,
            'planned_start_at' => '2026-10-10 09:00:00',
            'planned_end_at' => '2026-10-10 10:00:00',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'Test Topic',
            'material' => null,
            'activity' => null,
        ];

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.lesson-sessions.store'),
                $payload
            );

        $response->assertSessionHasErrors([
            'branch_id',
            'teacher_id',
            'program_id',
        ]);

        $this->assertDatabaseCount(
            'lesson_sessions',
            0
        );
    }
}