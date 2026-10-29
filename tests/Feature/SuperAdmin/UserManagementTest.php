<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use App\Http\Middleware\RoleMiddleware;

class UserManagementTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;





    private function createUser(
        string $role = 'superadmin',
        bool $active = true
    ): User {
        $id = (string) Str::uuid();

        DB::connection('sqlite')->table('users')->insert([
            'id' => $id,
            'username' => 'user_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => Hash::make('test-password'),
            'full_name' => 'Test ' . ucfirst($role),
            'role_code' => $role,
            'is_active' => $active,
            'created_at' => now()->toDateTimeString(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function validUserData(): array
    {
        return [
            'username' => 'new_teacher',
            'email' => 'teacher@example.test',
            'full_name' => 'Test Teacher',
            'role_code' => 'teacher',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'is_active' => '1',
        ];
    }

    public function test_superadmin_can_create_user(): void
    {
        $superadmin = $this->createUser();

        $data = $this->validUserData();

        $this->actingAs($superadmin)
            ->post(route('superadmin.users.store'), $data)
            ->assertRedirect(route('superadmin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'username' => 'new_teacher',
            'email' => 'teacher@example.test',
            'full_name' => 'Test Teacher',
            'role_code' => 'teacher',
            'is_active' => 1,
        ]);

        $createdUser = User::query()
            ->where('username', 'new_teacher')
            ->firstOrFail();

        $this->assertNotSame(
            'StrongPass123!',
            $createdUser->password_hash
        );

        $this->assertTrue(
            Hash::check('StrongPass123!', $createdUser->password_hash)
        );
    }

    public function test_superadmin_cannot_create_another_superadmin_using_user_form(): void
    {
        $superadmin = $this->createUser();

        $data = $this->validUserData();
        $data['role_code'] = 'superadmin';

        $this->actingAs($superadmin)
            ->from('/superadmin/users/create')
            ->post(route('superadmin.users.store'), $data)
            ->assertSessionHasErrors('role_code');

        $this->assertDatabaseMissing('users', [
            'username' => 'new_teacher',
        ]);
    }

    public function test_duplicate_username_is_rejected(): void
    {
        $superadmin = $this->createUser();

        $existingUser = $this->createUser('teacher');

        $data = $this->validUserData();
        $data['username'] = $existingUser->username;

        $this->actingAs($superadmin)
            ->from('/superadmin/users/create')
            ->post(route('superadmin.users.store'), $data)
            ->assertSessionHasErrors('username');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $superadmin = $this->createUser();

        $existingUser = $this->createUser('teacher');

        $data = $this->validUserData();
        $data['email'] = $existingUser->email;

        $this->actingAs($superadmin)
            ->from('/superadmin/users/create')
            ->post(route('superadmin.users.store'), $data)
            ->assertSessionHasErrors('email');
    }

    public function test_superadmin_can_update_user(): void
    {
        $superadmin = $this->createUser();
        $targetUser = $this->createUser('teacher');

        $originalPasswordHash = $targetUser->password_hash;

        $data = [
            'username' => 'teacher_updated',
            'email' => 'updated@example.test',
            'full_name' => 'Updated Teacher',
            'role_code' => 'teacher',
            'is_active' => '1',
        ];

        $this->actingAs($superadmin)
            ->put(
                route('superadmin.users.update', $targetUser),
                $data
            )
            ->assertRedirect(
                route('superadmin.users.show', $targetUser)
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'username' => 'teacher_updated',
            'email' => 'updated@example.test',
            'full_name' => 'Updated Teacher',
            'role_code' => 'teacher',
            'is_active' => 1,
        ]);

        $this->assertSame(
            $originalPasswordHash,
            $targetUser->fresh()->password_hash
        );
    }

    public function test_student_profile_cannot_be_changed_to_teacher_role(): void
    {
        $superadmin = $this->createUser();
        $targetUser = $this->createUser('student');

        DB::table('students')->insert([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $targetUser->id, 'full_name'=>'Siswa uji', 'began_on'=>today(), 'status'=>'active',
        ]);

        $data = [
            'username' => $targetUser->username,
            'email' => $targetUser->email,
            'full_name' => $targetUser->full_name,
            'role_code' => 'teacher',
            'is_active' => '1',
        ];

        $this->actingAs($superadmin)
            ->put(route('superadmin.users.update', $targetUser), $data)
            ->assertSessionHasErrors('role_code');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'role_code' => 'student',
        ]);
    }

    public function test_teacher_profile_cannot_be_changed_to_student_role(): void
    {
        $superadmin = $this->createUser();
        $targetUser = $this->createUser('teacher');

        DB::table('teachers')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $targetUser->id, 'whatsapp_number'=>'081234567890',
        ]);

        $data = [
            'username' => $targetUser->username,
            'email' => $targetUser->email,
            'full_name' => $targetUser->full_name,
            'role_code' => 'student',
            'is_active' => '1',
        ];

        $this->actingAs($superadmin)
            ->put(route('superadmin.users.update', $targetUser), $data)
            ->assertSessionHasErrors('role_code');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'role_code' => 'teacher',
        ]);
    }

    public function test_last_active_superadmin_cannot_be_deactivated_by_update(): void
    {
        $superadmin = $this->createUser();

        $data = [
            'username' => $superadmin->username,
            'email' => $superadmin->email,
            'full_name' => $superadmin->full_name,
            'role_code' => 'admin',
            'is_active' => '0',
        ];

        $this->actingAs($superadmin)
            ->put(route('superadmin.users.update', $superadmin), $data)
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $superadmin->id,
            'role_code' => 'superadmin',
            'is_active' => 1,
        ]);
    }

    public function test_superadmin_can_change_user_password(): void
    {
        $superadmin = $this->createUser();
        $targetUser = $this->createUser('teacher');

        $data = [
            'username' => $targetUser->username,
            'email' => $targetUser->email,
            'full_name' => $targetUser->full_name,
            'role_code' => 'teacher',
            'is_active' => '1',
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ];

        $this->actingAs($superadmin)
            ->put(
                route('superadmin.users.update', $targetUser),
                $data
            )
            ->assertRedirect(
                route('superadmin.users.show', $targetUser)
            )
            ->assertSessionHas('success');

        $updatedUser = $targetUser->fresh();

        $this->assertTrue(
            Hash::check(
                'NewStrongPass123!',
                $updatedUser->password_hash
            )
        );
    }

    public function test_non_superadmin_cannot_access_user_management(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->get(route('superadmin.users.index'))
            ->assertForbidden();

        $this->post(
            route('superadmin.users.store'),
            $this->validUserData()
        )->assertForbidden();
    }

    public function test_superadmin_cannot_delete_own_account(): void
    {
        $actor=$this->createUser();
        $this->actingAs($actor)->delete(route('superadmin.users.destroy',$actor))->assertSessionHasErrors('delete');
        $this->assertNotNull(User::find($actor->id));
    }

    public function test_superadmin_can_delete_another_user(): void
    {
        $actor=$this->createUser();$target=$this->createUser('teacher');
        $this->actingAs($actor)->delete(route('superadmin.users.destroy',$target))->assertRedirect(route('superadmin.users.index'))->assertSessionHas('success');
        $this->assertNull(User::find($target->id));$this->assertSoftDeleted('users',['id'=>$target->id]);
    }

    public function test_inactive_actor_cannot_delete_last_superadmin(): void
    {
        $actor=$this->createUser(active:false);$target=$this->createUser();
        $this->withoutMiddleware(RoleMiddleware::class);
        $this->actingAs($actor)->delete(route('superadmin.users.destroy',$target))->assertForbidden();
        $this->assertNotNull(User::find($target->id));
    }
}
