<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LessonSessionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LessonSessionManagementTest extends TestCase
{
    use RefreshDatabase;

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

    private function validPayload(
        Branch $branch,
        Program $program,
        Teacher $teacher,
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
            'topic' => 'Introduction',
            'material' => 'English Material',
            'activity' => 'Speaking Practice',
        ];
    }

    public function test_lesson_session_can_be_created(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();
        $classGroup = $this->makeClassGroup(
            $branch,
            $program,
            $teacher
        );

        $service = app(LessonSessionService::class);

        $session = $service->create(
            $this->validPayload(
                $branch,
                $program,
                $teacher,
                $classGroup
            )
        );

        $this->assertNotEmpty($session->id);

        $this->assertDatabaseHas('lesson_sessions', [
            'id' => $session->id,
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'class_group_id' => $classGroup->id,
            'status' => 'scheduled',
            'topic' => 'Introduction',
            'material' => 'English Material',
            'activity' => 'Speaking Practice',
        ]);
    }

    public function test_lesson_session_can_have_no_class_group(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $payload = $this->validPayload(
            $branch,
            $program,
            $teacher
        );

        $payload['class_group_id'] = null;

        $session = $service->create($payload);

        $this->assertNull($session->class_group_id);

        $this->assertDatabaseHas('lesson_sessions', [
            'id' => $session->id,
            'class_group_id' => null,
        ]);
    }

    public function test_lesson_session_can_store_actual_times(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $payload = $this->validPayload(
            $branch,
            $program,
            $teacher
        );

        $payload['actual_start_at'] = '2026-10-10 09:05:00';
        $payload['actual_end_at'] = '2026-10-10 10:05:00';

        $session = $service->create($payload);

        $this->assertSame(
            '2026-10-10 09:05:00',
            $session->fresh()->actual_start_at->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-10-10 10:05:00',
            $session->fresh()->actual_end_at->format('Y-m-d H:i:s')
        );
    }

    public function test_lesson_session_can_reference_previous_session_when_rescheduled(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $original = $service->create(
            $this->validPayload(
                $branch,
                $program,
                $teacher
            )
        );

        $rescheduledPayload = $this->validPayload(
            $branch,
            $program,
            $teacher
        );

        $rescheduledPayload['planned_start_at'] =
            '2026-10-11 09:00:00';

        $rescheduledPayload['planned_end_at'] =
            '2026-10-11 10:00:00';

        $rescheduledPayload['rescheduled_from_session_id'] =
            $original->id;

        $rescheduled = $service->create($rescheduledPayload);

        $this->assertSame(
            $original->id,
            $rescheduled->rescheduled_from_session_id
        );

        $this->assertDatabaseHas('lesson_sessions', [
            'id' => $rescheduled->id,
            'rescheduled_from_session_id' => $original->id,
        ]);
    }

    public function test_non_existing_branch_is_rejected(): void
    {
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service->create([
            'branch_id' => (string) Str::uuid(),
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
        ]);
    }

    public function test_non_existing_teacher_is_rejected(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();

        $service = app(LessonSessionService::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service->create([
            'branch_id' => $branch->id,
            'teacher_id' => (string) Str::uuid(),
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
        ]);
    }

    public function test_non_existing_program_is_rejected(): void
    {
        $branch = $this->makeBranch();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service->create([
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'program_id' => (string) Str::uuid(),
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
        ]);
    }

    public function test_non_existing_class_group_is_rejected(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $payload = $this->validPayload(
            $branch,
            $program,
            $teacher
        );

        $payload['class_group_id'] = (string) Str::uuid();

        $service->create($payload);
    }

    public function test_non_existing_rescheduled_session_is_rejected(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $payload = $this->validPayload(
            $branch,
            $program,
            $teacher
        );

        $payload['rescheduled_from_session_id'] =
            (string) Str::uuid();

        $service->create($payload);
    }

    public function test_planned_end_must_be_after_planned_start(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $payload = $this->validPayload(
            $branch,
            $program,
            $teacher
        );

        $payload['planned_start_at'] =
            '2026-10-10 10:00:00';

        $payload['planned_end_at'] =
            '2026-10-10 09:00:00';

        $this->expectException(ValidationException::class);

        $service->create($payload);
    }

    public function test_actual_end_cannot_be_before_actual_start(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $payload = $this->validPayload(
            $branch,
            $program,
            $teacher
        );

        $payload['actual_start_at'] =
            '2026-10-10 10:00:00';

        $payload['actual_end_at'] =
            '2026-10-10 09:00:00';

        $this->expectException(ValidationException::class);

        $service->create($payload);
    }

    public function test_lesson_session_can_be_updated(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $session = $service->create(
            $this->validPayload(
                $branch,
                $program,
                $teacher
            )
        );

        $newProgram = $this->makeProgram();
        $newTeacher = $this->makeTeacher();

        $updatedPayload = $this->validPayload(
            $branch,
            $newProgram,
            $newTeacher
        );

        $updatedPayload['status'] = 'completed';
        $updatedPayload['topic'] = 'Updated Topic';
        $updatedPayload['material'] = 'Updated Material';
        $updatedPayload['activity'] = 'Updated Activity';

        $updated = $service->update(
            $session,
            $updatedPayload
        );

        $this->assertSame(
            $newProgram->id,
            $updated->program_id
        );

        $this->assertSame(
            $newTeacher->id,
            $updated->teacher_id
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
            'Updated Material',
            $updated->material
        );

        $this->assertSame(
            'Updated Activity',
            $updated->activity
        );
    }

    public function test_lesson_session_update_can_clear_nullable_fields(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();
        $classGroup = $this->makeClassGroup(
            $branch,
            $program,
            $teacher
        );

        $service = app(LessonSessionService::class);

        $session = $service->create(
            $this->validPayload(
                $branch,
                $program,
                $teacher,
                $classGroup
            )
        );

        $updatedPayload = $this->validPayload(
            $branch,
            $program,
            $teacher
        );

        $updatedPayload['class_group_id'] = null;
        $updatedPayload['actual_start_at'] = null;
        $updatedPayload['actual_end_at'] = null;
        $updatedPayload['rescheduled_from_session_id'] = null;
        $updatedPayload['topic'] = null;
        $updatedPayload['material'] = null;
        $updatedPayload['activity'] = null;

        $updated = $service->update(
            $session,
            $updatedPayload
        );

        $this->assertNull($updated->class_group_id);
        $this->assertNull($updated->actual_start_at);
        $this->assertNull($updated->actual_end_at);
        $this->assertNull($updated->rescheduled_from_session_id);
        $this->assertNull($updated->topic);
        $this->assertNull($updated->material);
        $this->assertNull($updated->activity);
    }

    public function test_status_is_stored_without_assuming_allowed_values(): void
    {
        $branch = $this->makeBranch();
        $program = $this->makeProgram();
        $teacher = $this->makeTeacher();

        $service = app(LessonSessionService::class);

        $payload = $this->validPayload(
            $branch,
            $program,
            $teacher
        );

        $payload['status'] = 'custom-status';

        $session = $service->create($payload);

        $this->assertSame(
            'custom-status',
            $session->fresh()->status
        );
    }
}