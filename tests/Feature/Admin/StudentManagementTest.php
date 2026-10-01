<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Force tests to use an isolated in-memory SQLite database.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);

        // Ensure SQLite reconnects using the updated configuration.
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        // Fail safely if the test database is not isolated.
        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new RuntimeException(
                'StudentManagementTest must use SQLite in-memory.'
            );
        }

        Schema::connection('sqlite')->create('users', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password_hash');
            $table->string('full_name');
            $table->string('role_code');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->nullable();
        });

        Schema::connection('sqlite')->create('students', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('portal_user_id', 36)->unique();
            $table->string('full_name');
            $table->string('school_name')->nullable();
            $table->string('grade_name')->nullable();
            $table->date('began_on');
            $table->string('status');
            $table->text('special_notes_internal')->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        try {
            if (
                config('database.default') === 'sqlite'
                && config('database.connections.sqlite.database') === ':memory:'
            ) {
                Schema::connection('sqlite')->dropIfExists('students');
                Schema::connection('sqlite')->dropIfExists('users');
            }
        } finally {
            parent::tearDown();
        }
    }

    private function createUser(
        string $role = 'student',
        bool $active = true
    ): User {
        $id = (string) Str::uuid();

        DB::connection('sqlite')->table('users')->insert([
            'id' => $id,
            'username' => 'user_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => bcrypt('test-password'),
            'full_name' => 'Test ' . ucfirst($role),
            'role_code' => $role,
            'is_active' => $active,
            'created_at' => now()->toDateTimeString(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function createStudent(User $portalUser): string
    {
        $id = (string) Str::uuid();

        DB::connection('sqlite')->table('students')->insert([
            'id' => $id,
            'portal_user_id' => $portalUser->id,
            'full_name' => $portalUser->full_name,
            'school_name' => 'Test School',
            'grade_name' => 'Grade 6',
            'began_on' => '2026-01-01',
            'status' => 'active',
            'special_notes_internal' => null,
            'created_at' => now()->toDateTimeString(),
        ]);

        return $id;
    }

    private function validStudentData(User $portalUser): array
    {
        return [
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Student Test',
            'school_name' => 'Example School',
            'grade_name' => 'Grade 5',
            'began_on' => '2026-09-01',
            'special_notes_internal' => 'Internal test note',
        ];
    }

    public function test_admin_can_open_student_management_pages(): void
    {
        $admin = $this->createUser('admin');
        $portalUser = $this->createUser('student');
        $studentId = $this->createStudent($portalUser);

        $this->actingAs($admin)
            ->get(route('admin.students.index'))
            ->assertOk();

        $this->get(route('admin.students.create'))
            ->assertOk();

        $this->get(route('admin.students.show', $studentId))
            ->assertOk();

        $this->get(route('admin.students.edit', $studentId))
            ->assertOk();
    }

    public function test_non_admin_cannot_open_student_management(): void
    {
        $teacher = $this->createUser('teacher');

        $this->actingAs($teacher)
            ->get(route('admin.students.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_student_profile(): void
    {
        $admin = $this->createUser('admin');
        $portalUser = $this->createUser('student');

        $this->actingAs($admin)
            ->post(
                route('admin.students.store'),
                $this->validStudentData($portalUser)
            )
            ->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('students', [
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Student Test',
            'status' => 'active',
            'school_name' => 'Example School',
        ]);
    }

    public function test_admin_can_update_student_profile(): void
    {
        $admin = $this->createUser('admin');
        $portalUser = $this->createUser('student');
        $studentId = $this->createStudent($portalUser);

        $data = $this->validStudentData($portalUser);
        $data['full_name'] = 'Updated Student';
        $data['school_name'] = 'Updated School';

        $this->actingAs($admin)
            ->put(route('admin.students.update', $studentId), $data)
            ->assertRedirect(route('admin.students.show', $studentId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('students', [
            'id' => $studentId,
            'full_name' => 'Updated Student',
            'school_name' => 'Updated School',
        ]);
    }

    public function test_admin_cannot_assign_an_inactive_student_account(): void
    {
        $admin = $this->createUser('admin');
        $inactiveStudent = $this->createUser('student', false);

        $this->actingAs($admin)
            ->from(route('admin.students.create'))
            ->post(
                route('admin.students.store'),
                $this->validStudentData($inactiveStudent)
            )
            ->assertSessionHasErrors('portal_user_id');

        $this->assertDatabaseMissing('students', [
            'portal_user_id' => $inactiveStudent->id,
        ]);
    }

    public function test_admin_cannot_reuse_an_account_assigned_to_another_student(): void
    {
        $admin = $this->createUser('admin');
        $portalUser = $this->createUser('student');

        $this->createStudent($portalUser);

        $this->actingAs($admin)
            ->from(route('admin.students.create'))
            ->post(
                route('admin.students.store'),
                $this->validStudentData($portalUser)
            )
            ->assertSessionHasErrors('portal_user_id');

        $this->assertSame(
            1,
            DB::connection('sqlite')
                ->table('students')
                ->where('portal_user_id', $portalUser->id)
                ->count()
        );
    }

    public function test_admin_cannot_assign_an_account_with_the_wrong_role(): void
    {
        $admin = $this->createUser('admin');
        $teacher = $this->createUser('teacher');

        $this->actingAs($admin)
            ->from(route('admin.students.create'))
            ->post(
                route('admin.students.store'),
                $this->validStudentData($teacher)
            )
            ->assertSessionHasErrors('portal_user_id');

        $this->assertDatabaseMissing('students', [
            'portal_user_id' => $teacher->id,
        ]);
    }
}