<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Branch;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTeacherOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role = 'superadmin'): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $role . '_' . Str::random(8),
            'email' => null,
            'password_hash' => Hash::make('Password123!'),
            'full_name' => 'Test User',
            'role_code' => $role,
            'is_active' => true,
        ]);
    }

    public function test_teacher_account_can_be_completed_then_assigned_to_a_branch(): void
    {
        $this->actingAs($this->createUser());

        $this->post(route('superadmin.users.store'), [
            'full_name' => 'Guru Baru',
            'username' => 'guru_baru',
            'email' => 'guru_baru@example.test',
            'role_code' => 'teacher',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => '1',
        ])->assertRedirect(route('superadmin.users.index'))
            ->assertSessionHas('created_teacher_user_id');

        $user = User::query()->where('username', 'guru_baru')->firstOrFail();
        $profileUrl = route('admin.teachers.create', ['user_id' => $user->id]);

        $this->get(route('superadmin.users.index'))
            ->assertOk()
            ->assertSee('Tambah profil guru')
            ->assertSee($profileUrl, false);

        $this->get($profileUrl)
            ->assertOk()
            ->assertSee('Guru Baru')
            ->assertSee('value="' . $user->id . '" selected', false);

        $this->post(route('admin.teachers.store'), [
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
        ])->assertRedirect();

        $teacher = Teacher::query()->where('user_id', $user->id)->firstOrFail();
        $assignmentUrl = route('superadmin.teacher-branch-assignments.create', [
            'teacher_id' => $teacher->id,
        ]);

        $this->get($assignmentUrl)
            ->assertOk()
            ->assertSee('Guru Baru')
            ->assertSee('value="' . $teacher->id . '" selected', false);

        $branch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'name' => 'Cabang Baru',
            'address' => null,
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ]);

        $this->post(route('superadmin.teacher-branch-assignments.store'), [
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'starts_on' => '2026-10-09',
            'ends_on' => null,
        ])->assertRedirect(route('superadmin.teacher-branch-assignments.index'));

        $this->assertDatabaseHas('teacher_branch_assignments', [
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_assignment_form_explains_when_a_teacher_profile_is_missing(): void
    {
        $this->actingAs($this->createUser());

        $this->get(route('superadmin.teacher-branch-assignments.create'))
            ->assertOk()
            ->assertSee('Buat profil guru')
            ->assertSee('Belum ada profil guru aktif');
    }
}
