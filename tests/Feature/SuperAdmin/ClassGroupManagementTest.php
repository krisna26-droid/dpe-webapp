<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ClassGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClassGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperadmin(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'superadmin-' . Str::random(8),
            'email' => Str::random(8) . '@example.com',
            'full_name' => 'Superadmin Test',
            'password_hash' => bcrypt('password'),
            'role_code' => 'superadmin',
            'is_active' => true,
        ]);
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'admin-' . Str::random(8),
            'email' => Str::random(8) . '@example.com',
            'full_name' => 'Admin Test',
            'password_hash' => bcrypt('password'),
            'role_code' => 'admin',
            'is_active' => true,
        ]);
    }

    private function createBranch(
        bool $isActive = true
    ): Branch {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . strtoupper(Str::random(6)),
            'name' => 'Test Branch ' . Str::random(5),
            'address' => null,
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 1,
            'is_active' => $isActive,
        ]);
    }

    private function createProgram(
        bool $isActive = true
    ): Program {
        return Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PRG-' . strtoupper(Str::random(6)),
            'name' => 'Test Program ' . Str::random(5),
            'class_type' => 'regular',
            'monthly_video_target_override' => null,
            'is_active' => $isActive,
        ]);
    }

    private function createTeacher(
        bool $isActive = true
    ): Teacher {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'teacher-' . Str::random(8),
            'email' => Str::random(8) . '@example.com',
            'full_name' => 'Teacher Test',
            'password_hash' => bcrypt('password'),
            'role_code' => 'teacher',
            'is_active' => true,
        ]);

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'full_name' => 'Test Teacher',
            'whatsapp_number' => '081234567890',
            'is_active' => $isActive,
        ]);
    }

    private function createClassGroup(
        Branch $branch,
        Program $program,
        ?Teacher $teacher = null
    ): ClassGroup {
        return ClassGroup::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'default_teacher_id' => $teacher?->id,
            'code' => 'CLASS-' . strtoupper(Str::random(6)),
            'name' => 'Test Class Group',
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_create_class_group(): void
    {
        $branch = $this->createBranch();
        $program = $this->createProgram();
        $teacher = $this->createTeacher();

        $service = app(ClassGroupService::class);

        $classGroup = $service->create([
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'default_teacher_id' => $teacher->id,
            'code' => 'CLASS-001',
            'name' => 'English Class 1',
        ]);

        $this->assertDatabaseHas('class_groups', [
            'id' => $classGroup->id,
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'default_teacher_id' => $teacher->id,
            'code' => 'CLASS-001',
            'name' => 'English Class 1',
            'is_active' => 1,
        ]);
    }

    public function test_class_group_is_active_by_default(): void
    {
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $service = app(ClassGroupService::class);

        $classGroup = $service->create([
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'default_teacher_id' => null,
            'code' => 'CLASS-002',
            'name' => 'English Class 2',
        ]);

        $this->assertTrue($classGroup->is_active);
    }

    public function test_class_group_can_have_no_default_teacher(): void
    {
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $service = app(ClassGroupService::class);

        $classGroup = $service->create([
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'default_teacher_id' => null,
            'code' => 'CLASS-003',
            'name' => 'English Class 3',
        ]);

        $this->assertNull($classGroup->default_teacher_id);
    }

    public function test_inactive_branch_is_rejected(): void
    {
        $branch = $this->createBranch(false);
        $program = $this->createProgram();

        $service = app(ClassGroupService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->create([
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'default_teacher_id' => null,
            'code' => 'CLASS-004',
            'name' => 'Inactive Branch Class',
        ]);
    }

    public function test_inactive_program_is_rejected(): void
    {
        $branch = $this->createBranch();
        $program = $this->createProgram(false);

        $service = app(ClassGroupService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->create([
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'default_teacher_id' => null,
            'code' => 'CLASS-005',
            'name' => 'Inactive Program Class',
        ]);
    }

    public function test_inactive_teacher_is_rejected_as_default_teacher(): void
    {
        $branch = $this->createBranch();
        $program = $this->createProgram();
        $teacher = $this->createTeacher(false);

        $service = app(ClassGroupService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->create([
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'default_teacher_id' => $teacher->id,
            'code' => 'CLASS-006',
            'name' => 'Inactive Teacher Class',
        ]);
    }

    public function test_same_code_is_allowed_for_different_branches(): void
    {
        $branchA = $this->createBranch();
        $branchB = $this->createBranch();
        $program = $this->createProgram();

        $service = app(ClassGroupService::class);

        $first = $service->create([
            'branch_id' => $branchA->id,
            'program_id' => $program->id,
            'code' => 'CLASS-SAME',
            'name' => 'Branch A Class',
        ]);

        $second = $service->create([
            'branch_id' => $branchB->id,
            'program_id' => $program->id,
            'code' => 'CLASS-SAME',
            'name' => 'Branch B Class',
        ]);

        $this->assertNotSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'class_groups',
            2
        );
    }

    public function test_duplicate_code_in_same_branch_is_rejected_by_database(): void
    {
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $service = app(ClassGroupService::class);

        $service->create([
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'code' => 'CLASS-DUP',
            'name' => 'First Class',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $service->create([
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'code' => 'CLASS-DUP',
            'name' => 'Second Class',
        ]);
    }

    public function test_class_group_can_be_updated(): void
    {
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $classGroup = $this->createClassGroup(
            $branch,
            $program
        );

        $newProgram = $this->createProgram();

        $service = app(ClassGroupService::class);

        $updated = $service->update(
            $classGroup,
            [
                'branch_id' => $branch->id,
                'program_id' => $newProgram->id,
                'default_teacher_id' => null,
                'code' => 'CLASS-UPDATED',
                'name' => 'Updated Class',
            ]
        );

        $this->assertSame(
            $newProgram->id,
            $updated->program_id
        );

        $this->assertSame(
            'CLASS-UPDATED',
            $updated->code
        );

        $this->assertSame(
            'Updated Class',
            $updated->name
        );
    }

    public function test_class_group_can_be_deactivated(): void
    {
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $classGroup = $this->createClassGroup(
            $branch,
            $program
        );

        $service = app(ClassGroupService::class);

        $updated = $service->toggleStatus(
            $classGroup
        );

        $this->assertFalse(
            $updated->is_active
        );
    }

    public function test_class_group_can_be_reactivated(): void
    {
        $branch = $this->createBranch();
        $program = $this->createProgram();

        $classGroup = $this->createClassGroup(
            $branch,
            $program
        );

        $classGroup->update([
            'is_active' => false,
        ]);

        $service = app(ClassGroupService::class);

        $updated = $service->toggleStatus(
            $classGroup
        );

        $this->assertTrue(
            $updated->is_active
        );
    }
}