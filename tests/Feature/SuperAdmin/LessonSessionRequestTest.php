<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Requests\SuperAdmin\StoreLessonSessionRequest;
use App\Http\Requests\SuperAdmin\UpdateLessonSessionRequest;
use App\Models\Branch;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LessonSessionRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperadmin(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'superadmin_'.Str::lower(Str::random(8)),
            'email' => Str::uuid().'@example.com',
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
            'code' => 'BR-'.strtoupper(Str::random(6)),
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
            'code' => 'PRG-'.strtoupper(Str::random(6)),
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
            'username' => 'teacher_'.Str::lower(Str::random(8)),
            'email' => Str::uuid().'@example.com',
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

    private function makeTeacherUser(
        string $roleCode = 'teacher',
        bool $isActive = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'user_'.Str::lower(Str::random(8)),
            'email' => Str::uuid().'@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test User',
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);
    }

    private function validateStore(
        User $user,
        array $data
    ): array {
        $request = StoreLessonSessionRequest::create(
            '/superadmin/lesson-sessions',
            'POST',
            $data
        );

        $request->setUserResolver(
            fn () => $user
        );

        $validator = app('validator')->make(
            $request->all(),
            $request->rules()
        );

        return [
            'request' => $request,
            'validator' => $validator,
        ];
    }

    private function validateUpdate(
        User $user,
        array $data
    ): array {
        $request = UpdateLessonSessionRequest::create(
            '/superadmin/lesson-sessions/test',
            'PUT',
            $data
        );

        $request->setUserResolver(
            fn () => $user
        );

        $validator = app('validator')->make(
            $request->all(),
            $request->rules()
        );

        return [
            'request' => $request,
            'validator' => $validator,
        ];
    }

    public function test_store_request_authorizes_active_superadmin(): void
    {
        $user = $this->makeSuperadmin();

        $request = StoreLessonSessionRequest::create(
            '/superadmin/lesson-sessions',
            'POST'
        );

        $request->setUserResolver(
            fn () => $user
        );

        $this->assertTrue(
            $request->authorize()
        );
    }

    public function test_store_request_rejects_non_superadmin(): void
    {
        $user = $this->makeTeacherUser();

        $request = StoreLessonSessionRequest::create(
            '/superadmin/lesson-sessions',
            'POST'
        );

        $request->setUserResolver(
            fn () => $user
        );

        $this->assertFalse(
            $request->authorize()
        );
    }

    public function test_store_request_rejects_inactive_superadmin(): void
    {
        $user = $this->makeSuperadmin();

        $user->update([
            'is_active' => false,
        ]);

        $request = StoreLessonSessionRequest::create(
            '/superadmin/lesson-sessions',
            'POST'
        );

        $request->setUserResolver(
            fn () => $user
        );

        $this->assertFalse(
            $request->authorize()
        );
    }

    public function test_store_request_requires_required_fields(): void
    {
        $user = $this->makeSuperadmin();

        $result = $this->validateStore(
            $user,
            []
        );

        $this->assertTrue(
            $result['validator']->fails()
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('branch_id')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('teacher_id')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('program_id')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('planned_start_at')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('planned_end_at')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('status')
        );
    }

    public function test_store_request_accepts_nullable_fields(): void
    {
        $user = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $data = [
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
            'topic' => null,
            'material' => null,
            'activity' => null,
        ];

        $result = $this->validateStore(
            $user,
            $data
        );

        $this->assertFalse(
            $result['validator']->fails()
        );
    }

    public function test_store_request_rejects_end_before_planned_start(): void
    {
        $user = $this->makeSuperadmin();

        $data = [
            'branch_id' => (string) Str::uuid(),
            'teacher_id' => (string) Str::uuid(),
            'program_id' => (string) Str::uuid(),
            'class_group_id' => null,
            'planned_start_at' => '2026-10-10 10:00:00',
            'planned_end_at' => '2026-10-10 09:00:00',
            'status' => 'scheduled',
        ];

        $result = $this->validateStore(
            $user,
            $data
        );

        $this->assertTrue(
            $result['validator']->fails()
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('planned_end_at')
        );
    }

    public function test_store_request_rejects_actual_end_before_actual_start(): void
    {
        $user = $this->makeSuperadmin();

        $data = [
            'branch_id' => (string) Str::uuid(),
            'teacher_id' => (string) Str::uuid(),
            'program_id' => (string) Str::uuid(),
            'class_group_id' => null,
            'planned_start_at' => '2026-10-10 09:00:00',
            'planned_end_at' => '2026-10-10 10:00:00',
            'actual_start_at' => '2026-10-10 10:00:00',
            'actual_end_at' => '2026-10-10 09:00:00',
            'status' => 'scheduled',
        ];

        $result = $this->validateStore(
            $user,
            $data
        );

        $this->assertTrue(
            $result['validator']->fails()
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('actual_end_at')
        );
    }

    public function test_store_request_rejects_invalid_foreign_key_format(): void
    {
        $user = $this->makeSuperadmin();

        $data = [
            'branch_id' => 'non-existing-branch',
            'teacher_id' => 'non-existing-teacher',
            'program_id' => 'non-existing-program',
            'class_group_id' => 'non-existing-class-group',
            'planned_start_at' => '2026-10-10 09:00:00',
            'planned_end_at' => '2026-10-10 10:00:00',
            'status' => 'scheduled',
            'rescheduled_from_session_id' => 'non-existing-session',
        ];

        $result = $this->validateStore(
            $user,
            $data
        );

        $this->assertTrue(
            $result['validator']->fails()
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('branch_id')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('teacher_id')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('program_id')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('class_group_id')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('rescheduled_from_session_id')
        );
    }

    public function test_store_request_rejects_invalid_date_values(): void
    {
        $user = $this->makeSuperadmin();

        $data = [
            'branch_id' => (string) Str::uuid(),
            'teacher_id' => (string) Str::uuid(),
            'program_id' => (string) Str::uuid(),
            'planned_start_at' => 'not-a-date',
            'planned_end_at' => 'also-not-a-date',
            'actual_start_at' => 'invalid',
            'actual_end_at' => 'invalid',
            'status' => 'scheduled',
        ];

        $result = $this->validateStore(
            $user,
            $data
        );

        $this->assertTrue(
            $result['validator']->fails()
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('planned_start_at')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('planned_end_at')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('actual_start_at')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('actual_end_at')
        );
    }

    public function test_update_request_authorizes_active_superadmin(): void
    {
        $user = $this->makeSuperadmin();

        $request = UpdateLessonSessionRequest::create(
            '/superadmin/lesson-sessions/test',
            'PUT'
        );

        $request->setUserResolver(
            fn () => $user
        );

        $this->assertTrue(
            $request->authorize()
        );
    }

    public function test_update_request_rejects_non_superadmin(): void
    {
        $user = $this->makeTeacherUser();

        $request = UpdateLessonSessionRequest::create(
            '/superadmin/lesson-sessions/test',
            'PUT'
        );

        $request->setUserResolver(
            fn () => $user
        );

        $this->assertFalse(
            $request->authorize()
        );
    }

    public function test_update_request_requires_required_fields(): void
    {
        $user = $this->makeSuperadmin();

        $result = $this->validateUpdate(
            $user,
            []
        );

        $this->assertTrue(
            $result['validator']->fails()
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('branch_id')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('teacher_id')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('program_id')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('planned_start_at')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('planned_end_at')
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('status')
        );
    }

    public function test_update_request_accepts_valid_date_range(): void
    {
        $user = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $data = [
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => '2026-10-10 09:00:00',
            'planned_end_at' => '2026-10-10 10:00:00',
            'actual_start_at' => '2026-10-10 09:05:00',
            'actual_end_at' => '2026-10-10 10:05:00',
            'status' => 'completed',
            'rescheduled_from_session_id' => null,
            'topic' => 'Updated Topic',
            'material' => 'Updated Material',
            'activity' => 'Updated Activity',
        ];

        $result = $this->validateUpdate(
            $user,
            $data
        );

        $this->assertFalse(
            $result['validator']->fails()
        );
    }

    public function test_update_request_rejects_end_before_planned_start(): void
    {
        $user = $this->makeSuperadmin();

        $data = [
            'branch_id' => (string) Str::uuid(),
            'teacher_id' => (string) Str::uuid(),
            'program_id' => (string) Str::uuid(),
            'planned_start_at' => '2026-10-10 10:00:00',
            'planned_end_at' => '2026-10-10 09:00:00',
            'status' => 'scheduled',
        ];

        $result = $this->validateUpdate(
            $user,
            $data
        );

        $this->assertTrue(
            $result['validator']->fails()
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('planned_end_at')
        );
    }

    public function test_update_request_rejects_actual_end_before_actual_start(): void
    {
        $user = $this->makeSuperadmin();

        $data = [
            'branch_id' => (string) Str::uuid(),
            'teacher_id' => (string) Str::uuid(),
            'program_id' => (string) Str::uuid(),
            'planned_start_at' => '2026-10-10 09:00:00',
            'planned_end_at' => '2026-10-10 10:00:00',
            'actual_start_at' => '2026-10-10 10:00:00',
            'actual_end_at' => '2026-10-10 09:00:00',
            'status' => 'scheduled',
        ];

        $result = $this->validateUpdate(
            $user,
            $data
        );

        $this->assertTrue(
            $result['validator']->fails()
        );

        $this->assertTrue(
            $result['validator']
                ->errors()
                ->has('actual_end_at')
        );
    }

    public function test_status_is_not_restricted_to_assumed_values(): void
    {
        $user = $this->makeSuperadmin();

        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $data = [
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => '2026-10-10 09:00:00',
            'planned_end_at' => '2026-10-10 10:00:00',
            'status' => 'custom-status',
        ];

        $result = $this->validateStore(
            $user,
            $data
        );

        $this->assertFalse(
            $result['validator']->fails()
        );
    }
}
