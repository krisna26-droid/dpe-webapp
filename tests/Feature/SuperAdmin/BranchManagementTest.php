<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new RuntimeException(
                'BranchManagementTest must use SQLite in-memory.'
            );
        }

        Schema::connection('sqlite')->create(
            'users',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('username')->unique();
                $table->string('email')->nullable()->unique();
                $table->string('password_hash');
                $table->string('full_name');
                $table->string('role_code');
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at')->nullable();
            }
        );

        Schema::connection('sqlite')->create(
            'branches',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('address')->nullable();
                $table->string('timezone_name');
                $table->smallInteger('payment_recap_day');
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at')->nullable();
            }
        );
    }

    protected function tearDown(): void
    {
        try {
            if (
                config('database.default') === 'sqlite'
                && config('database.connections.sqlite.database') === ':memory:'
            ) {
                Schema::connection('sqlite')->dropIfExists('branches');
                Schema::connection('sqlite')->dropIfExists('users');
            }
        } finally {
            parent::tearDown();
        }
    }

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

    private function createBranch(
        string $code = 'DPS',
        bool $active = true
    ): Branch {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => 'Dharma Private English ' . $code,
            'address' => 'Denpasar, Bali',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => $active,
        ]);
    }

    private function validBranchData(): array
    {
        return [
            'code' => 'DPS',
            'name' => 'Dharma Private English Denpasar',
            'address' => 'Denpasar, Bali',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => '1',
        ];
    }

    public function test_superadmin_can_open_branch_index(): void
    {
        $superadmin = $this->createUser();

        $this->actingAs($superadmin)
            ->get(route('superadmin.branches.index'))
            ->assertOk();
    }

    public function test_non_superadmin_cannot_open_branch_index(): void
    {
        foreach (['admin', 'teacher', 'student'] as $role) {
            $user = $this->createUser($role);

            $this->actingAs($user)
                ->get(route('superadmin.branches.index'))
                ->assertForbidden();
        }
    }

    public function test_inactive_superadmin_cannot_access_branch_management(): void
    {
        $superadmin = $this->createUser('superadmin', false);

        $this->actingAs($superadmin)
            ->get(route('superadmin.branches.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_open_branch_create_page(): void
    {
        $superadmin = $this->createUser();

        $this->actingAs($superadmin)
            ->get(route('superadmin.branches.create'))
            ->assertOk();
    }

    public function test_superadmin_can_create_branch(): void
    {
        $superadmin = $this->createUser();

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.branches.store'),
                $this->validBranchData()
            )
            ->assertRedirect(route('superadmin.branches.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('branches', [
            'code' => 'DPS',
            'name' => 'Dharma Private English Denpasar',
            'address' => 'Denpasar, Bali',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => 1,
        ]);
    }

    public function test_duplicate_branch_code_is_rejected(): void
    {
        $superadmin = $this->createUser();

        $this->createBranch('DPS');

        $this->actingAs($superadmin)
            ->from('/superadmin/branches/create')
            ->post(
                route('superadmin.branches.store'),
                $this->validBranchData()
            )
            ->assertSessionHasErrors('code');
    }

    public function test_required_branch_fields_are_validated(): void
    {
        $superadmin = $this->createUser();

        $this->actingAs($superadmin)
            ->from('/superadmin/branches/create')
            ->post(
                route('superadmin.branches.store'),
                []
            )
            ->assertSessionHasErrors([
                'code',
                'name',
                'timezone_name',
                'payment_recap_day',
            ]);
    }

    public function test_payment_recap_day_must_be_between_one_and_thirty_one(): void
    {
        $superadmin = $this->createUser();

        $data = $this->validBranchData();

        $data['payment_recap_day'] = 0;

        $this->actingAs($superadmin)
            ->from('/superadmin/branches/create')
            ->post(
                route('superadmin.branches.store'),
                $data
            )
            ->assertSessionHasErrors('payment_recap_day');

        $data['payment_recap_day'] = 32;

        $this->actingAs($superadmin)
            ->from('/superadmin/branches/create')
            ->post(
                route('superadmin.branches.store'),
                $data
            )
            ->assertSessionHasErrors('payment_recap_day');
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $superadmin = $this->createUser();

        $data = $this->validBranchData();
        $data['timezone_name'] = 'Invalid/Timezone';

        $this->actingAs($superadmin)
            ->from('/superadmin/branches/create')
            ->post(
                route('superadmin.branches.store'),
                $data
            )
            ->assertSessionHasErrors('timezone_name');
    }

    public function test_new_branch_is_active_by_default(): void
    {
        $superadmin = $this->createUser();

        $data = $this->validBranchData();
        unset($data['is_active']);

        $this->actingAs($superadmin)
            ->post(
                route('superadmin.branches.store'),
                $data
            )
            ->assertRedirect(route('superadmin.branches.index'));

        $this->assertDatabaseHas('branches', [
            'code' => 'DPS',
            'is_active' => 1,
        ]);
    }

    public function test_superadmin_can_open_branch_show_page(): void
    {
        $superadmin = $this->createUser();
        $branch = $this->createBranch();

        $this->actingAs($superadmin)
            ->get(route('superadmin.branches.show', $branch))
            ->assertOk();
    }

    public function test_superadmin_can_open_branch_edit_page(): void
    {
        $superadmin = $this->createUser();
        $branch = $this->createBranch();

        $this->actingAs($superadmin)
            ->get(route('superadmin.branches.edit', $branch))
            ->assertOk();
    }

    public function test_superadmin_can_update_branch(): void
    {
        $superadmin = $this->createUser();
        $branch = $this->createBranch();

        $data = [
            'code' => 'DPS',
            'name' => 'Updated Branch Name',
            'address' => 'Updated Address',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => '1',
        ];

        $this->actingAs($superadmin)
            ->put(
                route('superadmin.branches.update', $branch),
                $data
            )
            ->assertRedirect(
                route('superadmin.branches.show', $branch)
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'code' => 'DPS',
            'name' => 'Updated Branch Name',
            'address' => 'Updated Address',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => 1,
        ]);
    }

    public function test_duplicate_code_of_another_branch_is_rejected_on_update(): void
    {
        $superadmin = $this->createUser();

        $this->createBranch('DPS');
        $branch = $this->createBranch('TAB');

        $data = [
            'code' => 'DPS',
            'name' => 'Updated Branch Name',
            'address' => null,
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => '1',
        ];

        $this->actingAs($superadmin)
            ->from('/superadmin/branches/' . $branch->id . '/edit')
            ->put(
                route('superadmin.branches.update', $branch),
                $data
            )
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'code' => 'TAB',
        ]);
    }

    public function test_admin_cannot_update_branch(): void
    {
        $admin = $this->createUser('admin');
        $branch = $this->createBranch();

        $data = $this->validBranchData();
        $data['name'] = 'Hacked Branch Name';

        $this->actingAs($admin)
            ->put(
                route('superadmin.branches.update', $branch),
                $data
            )
            ->assertForbidden();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Dharma Private English DPS',
        ]);
    }

    public function test_superadmin_can_deactivate_branch(): void
    {
        $superadmin = $this->createUser();
        $branch = $this->createBranch();

        $this->actingAs($superadmin)
            ->patch(
                route('superadmin.branches.status', $branch)
            )
            ->assertRedirect(
                route('superadmin.branches.show', $branch)
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'is_active' => 0,
        ]);
    }

    public function test_superadmin_can_reactivate_branch(): void
    {
        $superadmin = $this->createUser();

        $branch = $this->createBranch(
            code: 'DPS',
            active: false
        );

        $this->actingAs($superadmin)
            ->patch(
                route('superadmin.branches.status', $branch)
            )
            ->assertRedirect(
                route('superadmin.branches.show', $branch)
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'is_active' => 1,
        ]);
    }
}