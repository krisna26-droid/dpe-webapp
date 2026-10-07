<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BranchAdminAssignmentManagementTest extends TestCase
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

    private function createAssignment(
        Branch $branch,
        User $admin,
        string $startsOn = '2026-01-01',
        ?string $endsOn = '2026-12-31'
    ): BranchAdminAssignment {
        return BranchAdminAssignment::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'admin_user_id' => $admin->id,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);
    }

    public function test_superadmin_can_open_assignment_index(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this
            ->actingAs($superadmin)
            ->get(route('superadmin.branch-admin-assignments.index'));

        $response->assertOk();
    }

    public function test_non_superadmin_cannot_open_assignment_index(): void
    {
        $admin = $this->createAdmin();

        $response = $this
            ->actingAs($admin)
            ->get(route('superadmin.branch-admin-assignments.index'));

        $response->assertForbidden();
    }

    public function test_inactive_superadmin_cannot_access_assignment_management(): void
    {
        $superadmin = $this->createSuperadmin(false);

        $response = $this
            ->actingAs($superadmin)
            ->get(route('superadmin.branch-admin-assignments.index'));

        $response->assertForbidden();
    }

    public function test_superadmin_can_open_assignment_create_page(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this
            ->actingAs($superadmin)
            ->get(route('superadmin.branch-admin-assignments.create'));

        $response->assertOk();
    }

    public function test_superadmin_can_create_assignment(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin();
        $branch = $this->createBranch();

        $response = $this
            ->actingAs($superadmin)
            ->post(route('superadmin.branch-admin-assignments.store'), [
                'branch_id' => $branch->id,
                'admin_user_id' => $admin->id,
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ]);

        $response->assertRedirect(
            route('superadmin.branch-admin-assignments.index')
        );

        $createdAssignment = BranchAdminAssignment::query()
            ->where('branch_id', $branch->id)
            ->where('admin_user_id', $admin->id)
            ->first();

        $this->assertNotNull($createdAssignment);

        $this->assertTrue(
            $createdAssignment->starts_on->isSameDay('2026-01-01')
        );

        $this->assertTrue(
            $createdAssignment->ends_on->isSameDay('2026-12-31')
        );
    }

    public function test_assignment_can_have_no_end_date(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin();
        $branch = $this->createBranch();

        $response = $this
            ->actingAs($superadmin)
            ->post(route('superadmin.branch-admin-assignments.store'), [
                'branch_id' => $branch->id,
                'admin_user_id' => $admin->id,
                'starts_on' => '2026-01-01',
                'ends_on' => null,
            ]);

        $response->assertRedirect(
            route('superadmin.branch-admin-assignments.index')
        );

        $this->assertDatabaseHas(
            'branch_admin_assignments',
            [
                'branch_id' => $branch->id,
                'admin_user_id' => $admin->id,
                'ends_on' => null,
            ]
        );
    }

    public function test_admin_user_must_have_admin_role(): void
    {
        $superadmin = $this->createSuperadmin();
        $superadminTarget = $this->createSuperadmin();
        $branch = $this->createBranch();

        $response = $this
            ->actingAs($superadmin)
            ->post(route('superadmin.branch-admin-assignments.store'), [
                'branch_id' => $branch->id,
                'admin_user_id' => $superadminTarget->id,
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ]);

        $response->assertSessionHasErrors('admin_user_id');

        $this->assertDatabaseCount(
            'branch_admin_assignments',
            0
        );
    }

    public function test_inactive_admin_cannot_be_assigned(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin(false);
        $branch = $this->createBranch();

        $response = $this
            ->actingAs($superadmin)
            ->post(route('superadmin.branch-admin-assignments.store'), [
                'branch_id' => $branch->id,
                'admin_user_id' => $admin->id,
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ]);

        $response->assertSessionHasErrors('admin_user_id');

        $this->assertDatabaseCount(
            'branch_admin_assignments',
            0
        );
    }

    public function test_inactive_branch_cannot_receive_new_assignment(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin();
        $branch = $this->createBranch(false);

        $response = $this
            ->actingAs($superadmin)
            ->post(route('superadmin.branch-admin-assignments.store'), [
                'branch_id' => $branch->id,
                'admin_user_id' => $admin->id,
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ]);

        $response->assertSessionHasErrors('branch_id');

        $this->assertDatabaseCount(
            'branch_admin_assignments',
            0
        );
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin();
        $branch = $this->createBranch();

        $response = $this
            ->actingAs($superadmin)
            ->post(route('superadmin.branch-admin-assignments.store'), [
                'branch_id' => $branch->id,
                'admin_user_id' => $admin->id,
                'starts_on' => '2026-12-31',
                'ends_on' => '2026-01-01',
            ]);

        $response->assertSessionHasErrors('ends_on');

        $this->assertDatabaseCount(
            'branch_admin_assignments',
            0
        );
    }

    public function test_overlapping_assignment_is_rejected(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin();
        $branch = $this->createBranch();

        $this->createAssignment(
            $branch,
            $admin,
            '2026-01-01',
            '2026-12-31'
        );

        $response = $this
            ->actingAs($superadmin)
            ->post(route('superadmin.branch-admin-assignments.store'), [
                'branch_id' => $branch->id,
                'admin_user_id' => $admin->id,
                'starts_on' => '2026-06-01',
                'ends_on' => '2026-06-30',
            ]);

        $response->assertSessionHasErrors('admin_user_id');

        $this->assertDatabaseCount(
            'branch_admin_assignments',
            1
        );
    }

    public function test_assignment_starting_after_existing_assignment_is_allowed(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin();
        $branch = $this->createBranch();

        $this->createAssignment(
            $branch,
            $admin,
            '2026-01-01',
            '2026-03-31'
        );

        $response = $this
            ->actingAs($superadmin)
            ->post(route('superadmin.branch-admin-assignments.store'), [
                'branch_id' => $branch->id,
                'admin_user_id' => $admin->id,
                'starts_on' => '2026-04-01',
                'ends_on' => '2026-12-31',
            ]);

        $response->assertRedirect(
            route('superadmin.branch-admin-assignments.index')
        );

        $this->assertDatabaseCount(
            'branch_admin_assignments',
            2
        );
    }

    public function test_superadmin_can_open_assignment_show_page(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin();
        $branch = $this->createBranch();

        $assignment = $this->createAssignment(
            $branch,
            $admin
        );

        $response = $this
            ->actingAs($superadmin)
            ->get(route(
                'superadmin.branch-admin-assignments.show',
                $assignment
            ));

        $response->assertOk();
    }

    public function test_superadmin_can_open_assignment_edit_page(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin();
        $branch = $this->createBranch();

        $assignment = $this->createAssignment(
            $branch,
            $admin
        );

        $response = $this
            ->actingAs($superadmin)
            ->get(route(
                'superadmin.branch-admin-assignments.edit',
                $assignment
            ));

        $response->assertOk();
    }

    public function test_superadmin_can_update_assignment(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin();
        $branch = $this->createBranch();

        $assignment = $this->createAssignment(
            $branch,
            $admin,
            '2026-01-01',
            '2026-06-30'
        );

        $response = $this
            ->actingAs($superadmin)
            ->put(route(
                'superadmin.branch-admin-assignments.update',
                $assignment
            ), [
                'branch_id' => $branch->id,
                'admin_user_id' => $admin->id,
                'starts_on' => '2026-02-01',
                'ends_on' => '2026-12-31',
            ]);

        $response->assertRedirect(
            route(
                'superadmin.branch-admin-assignments.show',
                $assignment
            )
        );

        $updatedAssignment = BranchAdminAssignment::query()
            ->find($assignment->id);

        $this->assertNotNull($updatedAssignment);

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
        $admin = $this->createAdmin();
        $branch = $this->createBranch();

        $assignment = $this->createAssignment(
            $branch,
            $admin
        );

        $response = $this
            ->actingAs($superadmin)
            ->delete(route(
                'superadmin.branch-admin-assignments.destroy',
                $assignment
            ));

        $response->assertRedirect(
            route('superadmin.branch-admin-assignments.index')
        );

        $this->assertDatabaseMissing(
            'branch_admin_assignments',
            [
                'id' => $assignment->id,
            ]
        );
    }

    public function test_admin_cannot_delete_assignment(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createAdmin();
        $branch = $this->createBranch();

        $assignment = $this->createAssignment(
            $branch,
            $admin
        );

        $response = $this
            ->actingAs($admin)
            ->delete(route(
                'superadmin.branch-admin-assignments.destroy',
                $assignment
            ));

        $response->assertForbidden();

        $this->assertDatabaseHas(
            'branch_admin_assignments',
            [
                'id' => $assignment->id,
            ]
        );
    }
}