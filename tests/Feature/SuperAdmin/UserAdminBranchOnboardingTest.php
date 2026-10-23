<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserAdminBranchOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperadmin(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'superadmin_' . Str::random(8),
            'email' => null,
            'password_hash' => Hash::make('Password123!'),
            'full_name' => 'Super Admin',
            'role_code' => 'superadmin',
            'is_active' => true,
        ]);
    }

    public function test_admin_account_can_be_followed_by_branch_assignment(): void
    {
        $this->actingAs($this->createSuperadmin());

        $this->post(route('superadmin.users.store'), [
            'full_name' => 'Admin Cabang Baru',
            'username' => 'admin_cabang_baru',
            'email' => 'admin_cabang_baru@example.test',
            'role_code' => 'admin',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => '1',
        ])->assertRedirect(route('superadmin.users.index'))
            ->assertSessionHas('created_admin_user_id');

        $admin = User::query()->where('username', 'admin_cabang_baru')->firstOrFail();
        $assignmentUrl = route('superadmin.branch-admin-assignments.create', [
            'admin_user_id' => $admin->id,
        ]);

        $this->get(route('superadmin.users.index'))
            ->assertOk()
            ->assertSee('Tambah penugasan admin')
            ->assertSee($assignmentUrl, false);

        $this->get($assignmentUrl)
            ->assertOk()
            ->assertSee('Admin Cabang Baru')
            ->assertSee('value="' . $admin->id . '" selected', false);

        $branch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'name' => 'Cabang Admin Baru',
            'address' => null,
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ]);

        $this->post(route('superadmin.branch-admin-assignments.store'), [
            'branch_id' => $branch->id,
            'admin_user_id' => $admin->id,
            'starts_on' => '2026-10-09',
            'ends_on' => null,
        ])->assertRedirect(route('superadmin.branch-admin-assignments.index'));

        $this->assertDatabaseHas('branch_admin_assignments', [
            'branch_id' => $branch->id,
            'admin_user_id' => $admin->id,
        ]);
    }
}
