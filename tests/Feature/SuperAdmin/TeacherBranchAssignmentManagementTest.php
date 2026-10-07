<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherBranchAssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperadmin(
        bool $active = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'superadmin-' . Str::random(8),
            'email' => 'superadmin-' . Str::random(8) . '@test.local',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Super Admin',
            'role_code' => 'superadmin',
            'is_active' => $active,
        ]);
    }

    private function createAdmin(
        bool $active = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'admin-' . Str::random(8),
            'email' => 'admin-' . Str::random(8) . '@test.local',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Admin Test',
            'role_code' => 'admin',
            'is_active' => $active,
        ]);
    }

    private function createTeacherUser(
        bool $active = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'teacher-' . Str::random(8),
            'email' => 'teacher-' . Str::random(8) . '@test.local',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Teacher Test ' . Str::random(6),
            'role_code' => 'teacher',
            'is_active' => $active,
        ]);
    }

    private function createBranch(
        bool $active = true
    ): Branch {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . strtoupper(Str::random(6)),
            'name' => 'Branch Test',
            'address' => 'Test Address',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => $active,
        ]);
    }

    private function createTeacher(
        bool $active = true
    ): Teacher {
        $user = $this->createTeacherUser($active);

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'is_active' => $active,
        ]);
    }

    private function createAssignment(
        Teacher $teacher,
        Branch $branch,
        string $startsOn = '2026-01-01',
        ?string $endsOn = '2026-12-31'
    ): TeacherBranchAssignment {
        return TeacherBranchAssignment::query()->create([
            'id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);
    }

    public function test_superadmin_can_open_assignment_index(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this
            ->actingAs($superadmin)
            ->get(route('superadmin.teacher-branch-assignments.index'));

        $response->assertOk();
    }

    public function test_non_superadmin_cannot_open_assignment_index(): void
    {
        $admin = $this->createAdmin();

        $response = $this
            ->actingAs($admin)
            ->get(route('superadmin.teacher-branch-assignments.index'));

        $response->assertForbidden();
    }

    public function test_inactive_superadmin_cannot_access_assignment_management(): void
    {
        $superadmin = $this->createSuperadmin(false);

        $response = $this
            ->actingAs($superadmin)
            ->get(route('superadmin.teacher-branch-assignments.index'));

        $response->assertForbidden();
    }

    public function test_superadmin_can_open_assignment_create_page(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this
            ->actingAs($superadmin)
            ->get(route('superadmin.teacher-branch-assignments.create'));

        $response->assertOk();
    }

    public function test_superadmin_can_create_assignment(): void
    {
        $superadmin = $this->createSuperadmin();
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.teacher-branch-assignments.store'),
                [
                    'teacher_id' => $teacher->id,
                    'branch_id' => $branch->id,
                    'starts_on' => '2026-01-01',
                    'ends_on' => '2026-12-31',
                ]
            );

        $response->assertRedirect(
            route('superadmin.teacher-branch-assignments.index')
        );

        $assignment = TeacherBranchAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('branch_id', $branch->id)
            ->first();

        $this->assertNotNull($assignment);

        $this->assertTrue(
            $assignment->starts_on->isSameDay('2026-01-01')
        );

        $this->assertTrue(
            $assignment->ends_on->isSameDay('2026-12-31')
        );
    }

    public function test_assignment_can_have_no_end_date(): void
    {
        $superadmin = $this->createSuperadmin();
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.teacher-branch-assignments.store'),
                [
                    'teacher_id' => $teacher->id,
                    'branch_id' => $branch->id,
                    'starts_on' => '2026-01-01',
                    'ends_on' => null,
                ]
            );

        $response->assertRedirect(
            route('superadmin.teacher-branch-assignments.index')
        );

        $this->assertDatabaseHas(
            'teacher_branch_assignments',
            [
                'teacher_id' => $teacher->id,
                'branch_id' => $branch->id,
                'ends_on' => null,
            ]
        );
    }

    public function test_inactive_teacher_cannot_be_assigned(): void
    {
        $superadmin = $this->createSuperadmin();
        $teacher = $this->createTeacher(false);
        $branch = $this->createBranch();

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.teacher-branch-assignments.store'),
                [
                    'teacher_id' => $teacher->id,
                    'branch_id' => $branch->id,
                    'starts_on' => '2026-01-01',
                    'ends_on' => '2026-12-31',
                ]
            );

        $response->assertSessionHasErrors('teacher_id');

        $this->assertDatabaseCount(
            'teacher_branch_assignments',
            0
        );
    }

    public function test_inactive_branch_cannot_receive_new_assignment(): void
    {
        $superadmin = $this->createSuperadmin();
        $teacher = $this->createTeacher();
        $branch = $this->createBranch(false);

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.teacher-branch-assignments.store'),
                [
                    'teacher_id' => $teacher->id,
                    'branch_id' => $branch->id,
                    'starts_on' => '2026-01-01',
                    'ends_on' => '2026-12-31',
                ]
            );

        $response->assertSessionHasErrors('branch_id');

        $this->assertDatabaseCount(
            'teacher_branch_assignments',
            0
        );
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        $superadmin = $this->createSuperadmin();
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.teacher-branch-assignments.store'),
                [
                    'teacher_id' => $teacher->id,
                    'branch_id' => $branch->id,
                    'starts_on' => '2026-12-31',
                    'ends_on' => '2026-01-01',
                ]
            );

        $response->assertSessionHasErrors('ends_on');

        $this->assertDatabaseCount(
            'teacher_branch_assignments',
            0
        );
    }

    public function test_overlapping_assignment_for_same_teacher_is_rejected(): void
    {
        $superadmin = $this->createSuperadmin();
        $teacher = $this->createTeacher();

        $branchOne = $this->createBranch();
        $branchTwo = $this->createBranch();

        $this->createAssignment(
            $teacher,
            $branchOne,
            '2026-01-01',
            '2026-12-31'
        );

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.teacher-branch-assignments.store'),
                [
                    'teacher_id' => $teacher->id,
                    'branch_id' => $branchTwo->id,
                    'starts_on' => '2026-06-01',
                    'ends_on' => '2026-06-30',
                ]
            );

        $response->assertSessionHasErrors('teacher_id');

        $this->assertDatabaseCount(
            'teacher_branch_assignments',
            1
        );
    }

    public function test_assignment_starting_after_existing_assignment_is_allowed(): void
    {
        $superadmin = $this->createSuperadmin();
        $teacher = $this->createTeacher();

        $branchOne = $this->createBranch();
        $branchTwo = $this->createBranch();

        $this->createAssignment(
            $teacher,
            $branchOne,
            '2026-01-01',
            '2026-03-31'
        );

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.teacher-branch-assignments.store'),
                [
                    'teacher_id' => $teacher->id,
                    'branch_id' => $branchTwo->id,
                    'starts_on' => '2026-04-01',
                    'ends_on' => '2026-12-31',
                ]
            );

        $response->assertRedirect(
            route('superadmin.teacher-branch-assignments.index')
        );

        $this->assertDatabaseCount(
            'teacher_branch_assignments',
            2
        );
    }

    public function test_superadmin_can_open_assignment_show_page(): void
    {
        $superadmin = $this->createSuperadmin();
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $assignment = $this->createAssignment(
            $teacher,
            $branch
        );

        $response = $this
            ->actingAs($superadmin)
            ->get(
                route(
                    'superadmin.teacher-branch-assignments.show',
                    $assignment
                )
            );

        $response->assertOk();
    }

    public function test_superadmin_can_open_assignment_edit_page(): void
    {
        $superadmin = $this->createSuperadmin();
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $assignment = $this->createAssignment(
            $teacher,
            $branch
        );

        $response = $this
            ->actingAs($superadmin)
            ->get(
                route(
                    'superadmin.teacher-branch-assignments.edit',
                    $assignment
                )
            );

        $response->assertOk();
    }

    public function test_superadmin_can_update_assignment(): void
    {
        $superadmin = $this->createSuperadmin();
        $teacher = $this->createTeacher();

        $branchOne = $this->createBranch();
        $branchTwo = $this->createBranch();

        $assignment = $this->createAssignment(
            $teacher,
            $branchOne,
            '2026-01-01',
            '2026-06-30'
        );

        $response = $this
            ->actingAs($superadmin)
            ->put(
                route(
                    'superadmin.teacher-branch-assignments.update',
                    $assignment
                ),
                [
                    'teacher_id' => $teacher->id,
                    'branch_id' => $branchTwo->id,
                    'starts_on' => '2026-02-01',
                    'ends_on' => '2026-12-31',
                ]
            );

        $response->assertRedirect(
            route(
                'superadmin.teacher-branch-assignments.show',
                $assignment
            )
        );

        $updatedAssignment = TeacherBranchAssignment::query()
            ->find($assignment->id);

        $this->assertNotNull($updatedAssignment);

        $this->assertSame(
            $branchTwo->id,
            $updatedAssignment->branch_id
        );

        $this->assertTrue(
            $updatedAssignment->starts_on->isSameDay('2026-02-01')
        );

        $this->assertTrue(
            $updatedAssignment->ends_on->isSameDay('2026-12-31')
        );
    }

    public function test_superadmin_can_delete_assignment(): void
    {
        $superadmin = $this->createSuperadmin();
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $assignment = $this->createAssignment(
            $teacher,
            $branch
        );

        $response = $this
            ->actingAs($superadmin)
            ->delete(
                route(
                    'superadmin.teacher-branch-assignments.destroy',
                    $assignment
                )
            );

        $response->assertRedirect(
            route('superadmin.teacher-branch-assignments.index')
        );

        $this->assertDatabaseMissing(
            'teacher_branch_assignments',
            [
                'id' => $assignment->id,
            ]
        );
    }

    public function test_admin_cannot_delete_assignment(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher();
        $branch = $this->createBranch();

        $assignment = $this->createAssignment(
            $teacher,
            $branch
        );

        $response = $this
            ->actingAs($admin)
            ->delete(
                route(
                    'superadmin.teacher-branch-assignments.destroy',
                    $assignment
                )
            );

        $response->assertForbidden();

        $this->assertDatabaseHas(
            'teacher_branch_assignments',
            [
                'id' => $assignment->id,
            ]
        );
    }
}