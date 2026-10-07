<?php

namespace Tests\Feature\Admin;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Services\FileAssetService;
use Illuminate\Support\Facades\Log;
use Mockery;


class TeacherManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Gunakan database SQLite in-memory khusus untuk pengujian.
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
                'TeacherManagementTest must use SQLite in-memory.'
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
                $table->string('name');
            }
        );

        Schema::connection('sqlite')->create(
            'branch_admin_assignments',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('branch_id', 36);
                $table->char('admin_user_id', 36);
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
            }
        );

        Schema::connection('sqlite')->create(
            'teacher_branch_assignments',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('teacher_id', 36);
                $table->char('branch_id', 36);
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
            }
        );

        Schema::connection('sqlite')->create(
            'file_assets',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('storage_key')->unique();
                $table->text('original_name');
                $table->string('mime_type');
                $table->unsignedBigInteger('size_bytes');
                $table->string('sha256_hex')->nullable();
                $table->dateTime('created_at')->nullable();
            }
        );

        Schema::connection('sqlite')->create(
            'teachers',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('user_id', 36)->unique();
                $table->string('whatsapp_number');
                $table->char('photo_file_id', 36)->nullable();
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
                Schema::connection('sqlite')->dropIfExists('teacher_branch_assignments');
                Schema::connection('sqlite')->dropIfExists('branch_admin_assignments');
                Schema::connection('sqlite')->dropIfExists('branches');
                Schema::connection('sqlite')->dropIfExists('teachers');
                Schema::connection('sqlite')->dropIfExists('file_assets');
                Schema::connection('sqlite')->dropIfExists('users');
            }
        } finally {
            parent::tearDown();
        }
    }

    private function createUser(
        string $role = 'teacher',
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

    private function createTeacher(User $user): Teacher
    {
        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'photo_file_id' => null,
            'is_active' => true,
        ]);
    }

    private function createBranch(string $name = 'Branch Test'): string
    {
        $id = (string) Str::uuid();

        DB::connection('sqlite')->table('branches')->insert([
            'id' => $id,
            'name' => $name,
        ]);

        return $id;
    }

    private function assignAdminToBranch(
        string $adminUserId,
        string $branchId,
        string $startsOn = '2026-01-01',
        ?string $endsOn = null
    ): void {
        DB::connection('sqlite')->table('branch_admin_assignments')->insert([
            'id' => (string) Str::uuid(),
            'branch_id' => $branchId,
            'admin_user_id' => $adminUserId,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);
    }

    private function assignTeacherToBranch(
        string $teacherId,
        string $branchId,
        string $startsOn = '2026-01-01',
        ?string $endsOn = null
    ): void {
        DB::connection('sqlite')->table('teacher_branch_assignments')->insert([
            'id' => (string) Str::uuid(),
            'teacher_id' => $teacherId,
            'branch_id' => $branchId,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);
    }

    private function assignAdminAndTeacherToSameBranch(
        User $admin,
        Teacher $teacher
    ): void {
        $branchId = $this->createBranch();

        $this->assignAdminToBranch($admin->id, $branchId);
        $this->assignTeacherToBranch($teacher->id, $branchId);
    }

    private function validTeacherData(User $user): array
    {
        return [
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'photo_file_id' => null,
        ];
    }

    private function createFileAsset(): string
    {
        $id = (string) Str::uuid();

        DB::connection('sqlite')->table('file_assets')->insert([
            'id' => $id,
            'storage_key' => 'teacher-photos/' . $id . '.jpg',
            'original_name' => 'teacher.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'sha256_hex' => hash('sha256', 'test-image'),
            'created_at' => now()->toDateTimeString(),
        ]);

        return $id;
    }

    public function test_admin_can_open_teacher_management_pages(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);
        $this->assignAdminAndTeacherToSameBranch($admin, $teacher);

        $this->actingAs($admin)
            ->get(route('admin.teachers.index'))
            ->assertOk();

        $this->get(route('admin.teachers.create'))
            ->assertOk();

        $this->get(route('admin.teachers.show', $teacher->id))
            ->assertOk();

        $this->get(route('admin.teachers.edit', $teacher->id))
            ->assertOk();
    }

    public function test_non_admin_cannot_open_teacher_management(): void
    {
        $teacher = $this->createUser('teacher');

        $this->actingAs($teacher)
            ->get(route('admin.teachers.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_teacher_profile(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');

        $this->actingAs($admin)
            ->post(
                route('admin.teachers.store'),
                $this->validTeacherData($teacherUser)
            )
            ->assertRedirect(route('admin.teachers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', [
            'user_id' => $teacherUser->id,
            'whatsapp_number' => '081234567890',
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_update_teacher_profile(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);
        $this->assignAdminAndTeacherToSameBranch($admin, $teacher);

        $data = $this->validTeacherData($teacherUser);
        $data['whatsapp_number'] = '081298765432';

        $this->actingAs($admin)
            ->put(
                route('admin.teachers.update', $teacher->id),
                $data
            )
            ->assertRedirect(route('admin.teachers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'user_id' => $teacherUser->id,
            'whatsapp_number' => '081298765432',
        ]);
    }

    public function test_admin_cannot_assign_an_inactive_teacher_account(): void
    {
        $admin = $this->createUser('admin');
        $inactiveTeacher = $this->createUser('teacher', false);

        $this->actingAs($admin)
            ->from(route('admin.teachers.create'))
            ->post(
                route('admin.teachers.store'),
                $this->validTeacherData($inactiveTeacher)
            )
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('teachers', [
            'user_id' => $inactiveTeacher->id,
        ]);
    }

    public function test_admin_cannot_reuse_an_account_assigned_to_another_teacher(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');

        $this->createTeacher($teacherUser);

        $this->actingAs($admin)
            ->from(route('admin.teachers.create'))
            ->post(
                route('admin.teachers.store'),
                $this->validTeacherData($teacherUser)
            )
            ->assertSessionHasErrors('user_id');

        $this->assertSame(
            1,
            DB::connection('sqlite')
                ->table('teachers')
                ->where('user_id', $teacherUser->id)
                ->count()
        );
    }

    public function test_admin_cannot_assign_an_account_with_the_wrong_role(): void
    {
        $admin = $this->createUser('admin');
        $student = $this->createUser('student');

        $this->actingAs($admin)
            ->from(route('admin.teachers.create'))
            ->post(
                route('admin.teachers.store'),
                $this->validTeacherData($student)
            )
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('teachers', [
            'user_id' => $student->id,
        ]);
    }

    public function test_admin_cannot_create_teacher_without_whatsapp_number(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');

        $data = $this->validTeacherData($teacherUser);
        $data['whatsapp_number'] = '';

        $this->actingAs($admin)
            ->from(route('admin.teachers.create'))
            ->post(route('admin.teachers.store'), $data)
            ->assertSessionHasErrors('whatsapp_number');

        $this->assertDatabaseMissing('teachers', [
            'user_id' => $teacherUser->id,
        ]);
    }

    public function test_admin_can_assign_an_existing_photo_file(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');
        $photoId = $this->createFileAsset();

        $data = $this->validTeacherData($teacherUser);
        $data['photo_file_id'] = $photoId;

        $this->actingAs($admin)
            ->post(route('admin.teachers.store'), $data)
            ->assertRedirect(route('admin.teachers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', [
            'user_id' => $teacherUser->id,
            'photo_file_id' => $photoId,
        ]);
    }

    public function test_admin_cannot_assign_a_nonexistent_photo_file(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');

        $data = $this->validTeacherData($teacherUser);
        $data['photo_file_id'] = (string) Str::uuid();

        $this->actingAs($admin)
            ->from(route('admin.teachers.create'))
            ->post(route('admin.teachers.store'), $data)
            ->assertSessionHasErrors('photo_file_id');

        $this->assertDatabaseMissing('teachers', [
            'user_id' => $teacherUser->id,
        ]);
    }

    public function test_admin_can_upload_teacher_photo(): void
    {
        $admin = $this->createUser('admin');

        $teacherUser = $this->createUser('teacher');

        Storage::fake('local');

        $photo = UploadedFile::fake()->image('teacher.jpg');


        $response = $this
            ->actingAs($admin)
            ->post(
                route('admin.teachers.store'),
                [
                    'user_id' => $teacherUser->id,
                    'whatsapp_number' => '08123456789',
                    'photo' => $photo,
                ]
            );


        $response->assertRedirect(
            route('admin.teachers.index')
        );


        $teacher = Teacher::where(
            'user_id',
            $teacherUser->id
        )->first();


        $this->assertNotNull(
            $teacher->photo_file_id
        );


        $this->assertDatabaseHas(
            'file_assets',
            [
                'id' => $teacher->photo_file_id,
                'original_name' => 'teacher.jpg',
            ]
        );
    }


    public function test_admin_can_replace_teacher_photo_and_old_file_is_removed(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);
        $this->assignAdminAndTeacherToSameBranch($admin, $teacher);

        Storage::fake('local');

        $oldPhotoId = (string) Str::uuid();
        $oldStorageKey = 'teacher-photos/' . $oldPhotoId . '.jpg';

        Storage::disk('local')->put($oldStorageKey, 'old photo');

        DB::connection('sqlite')->table('file_assets')->insert([
            'id' => $oldPhotoId,
            'storage_key' => $oldStorageKey,
            'original_name' => 'old-teacher.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 9,
            'sha256_hex' => hash('sha256', 'old photo'),
            'created_at' => now()->toDateTimeString(),
        ]);

        DB::connection('sqlite')->table('teachers')
            ->where('id', $teacher->id)
            ->update(['photo_file_id' => $oldPhotoId]);

        $response = $this->actingAs($admin)->put(
            route('admin.teachers.update', $teacher->id),
            [
                'user_id' => $teacherUser->id,
                'whatsapp_number' => '081298765432',
                'photo' => UploadedFile::fake()->image('updated-teacher.jpg'),
            ]
        );

        $response->assertRedirect(route('admin.teachers.index'))
            ->assertSessionHas('success');

        $updatedTeacher = DB::connection('sqlite')->table('teachers')
            ->where('id', $teacher->id)
            ->first();

        $this->assertNotSame($oldPhotoId, $updatedTeacher->photo_file_id);

        $this->assertDatabaseMissing('file_assets', [
            'id' => $oldPhotoId,
        ]);

        Storage::disk('local')->assertMissing($oldStorageKey);

        $newAsset = DB::connection('sqlite')->table('file_assets')
            ->where('id', $updatedTeacher->photo_file_id)
            ->first();

        $this->assertNotNull($newAsset);
        $this->assertSame('updated-teacher.jpg', $newAsset->original_name);

        Storage::disk('local')->assertExists($newAsset->storage_key);
    }

    public function test_teacher_photo_is_preserved_when_no_new_photo_is_uploaded(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);
        $this->assignAdminAndTeacherToSameBranch($admin, $teacher);

        Storage::fake('local');

        $photoId = (string) Str::uuid();
        $storageKey = 'teacher-photos/' . $photoId . '.jpg';

        Storage::disk('local')->put($storageKey, 'existing photo');

        DB::connection('sqlite')->table('file_assets')->insert([
            'id' => $photoId,
            'storage_key' => $storageKey,
            'original_name' => 'teacher.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 14,
            'sha256_hex' => hash('sha256', 'existing photo'),
            'created_at' => now()->toDateTimeString(),
        ]);

        DB::connection('sqlite')->table('teachers')
            ->where('id', $teacher->id)
            ->update(['photo_file_id' => $photoId]);

        $this->actingAs($admin)->put(
            route('admin.teachers.update', $teacher->id),
            [
                'user_id' => $teacherUser->id,
                'whatsapp_number' => '081298765432',
            ]
        )->assertRedirect(route('admin.teachers.index'));

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'photo_file_id' => $photoId,
        ]);

        $this->assertDatabaseHas('file_assets', [
            'id' => $photoId,
            'storage_key' => $storageKey,
        ]);

        Storage::disk('local')->assertExists($storageKey);
    }


    public function test_new_teacher_photo_is_cleaned_up_when_create_transaction_fails(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');

        Storage::fake('local');

        // Paksa INSERT guru gagal setelah foto diproses.
        DB::connection('sqlite')->statement("
            CREATE TRIGGER fail_teacher_insert
            BEFORE INSERT ON teachers
            BEGIN
                SELECT RAISE(ABORT, 'Forced teacher insert failure');
            END
        ");

        $response = $this->actingAs($admin)->post(
            route('admin.teachers.store'),
            [
                'user_id' => $teacherUser->id,
                'whatsapp_number' => '081234567890',
                'photo' => UploadedFile::fake()->image('failed-teacher.jpg'),
            ]
        );

        $response->assertServerError();

        $this->assertDatabaseMissing('teachers', [
            'user_id' => $teacherUser->id,
        ]);

        $this->assertDatabaseCount('file_assets', 0);

        $this->assertSame(
            [],
            Storage::disk('local')->allFiles('teacher-photos')
        );
    }

    public function test_new_teacher_photo_is_cleaned_up_and_old_photo_is_preserved_when_update_fails(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);
        $this->assignAdminAndTeacherToSameBranch($admin, $teacher);

        Storage::fake('local');

        $oldPhotoId = (string) Str::uuid();
        $oldStorageKey = 'teacher-photos/' . $oldPhotoId . '.jpg';

        Storage::disk('local')->put($oldStorageKey, 'old photo');

        DB::connection('sqlite')->table('file_assets')->insert([
            'id' => $oldPhotoId,
            'storage_key' => $oldStorageKey,
            'original_name' => 'old-teacher.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 9,
            'sha256_hex' => hash('sha256', 'old photo'),
            'created_at' => now()->toDateTimeString(),
        ]);

        DB::connection('sqlite')->table('teachers')
            ->where('id', $teacher->id)
            ->update(['photo_file_id' => $oldPhotoId]);

        // Paksa UPDATE guru gagal setelah foto baru diproses.
        DB::connection('sqlite')->statement("
            CREATE TRIGGER fail_teacher_update
            BEFORE UPDATE ON teachers
            BEGIN
                SELECT RAISE(ABORT, 'Forced teacher update failure');
            END
        ");

        $response = $this->actingAs($admin)->put(
            route('admin.teachers.update', $teacher->id),
            [
                'user_id' => $teacherUser->id,
                'whatsapp_number' => '081298765432',
                'photo' => UploadedFile::fake()->image('replacement.jpg'),
            ]
        );

        $response->assertServerError();

        // Profil tetap memakai foto lama.
        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'photo_file_id' => $oldPhotoId,
            'whatsapp_number' => '081234567890',
        ]);

        // Metadata dan file foto lama tetap ada.
        $this->assertDatabaseHas('file_assets', [
            'id' => $oldPhotoId,
            'storage_key' => $oldStorageKey,
        ]);

        Storage::disk('local')->assertExists($oldStorageKey);

        // Foto baru yang dibuat selama transaksi sudah dibersihkan.
        $this->assertDatabaseCount('file_assets', 1);

        $this->assertSame(
            [$oldStorageKey],
            Storage::disk('local')->allFiles('teacher-photos')
        );
    }

    public function test_teacher_profile_update_succeeds_when_old_photo_cleanup_fails(): void
    {
        $admin = $this->createUser('admin');
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);
        $this->assignAdminAndTeacherToSameBranch($admin, $teacher);

        Storage::fake('local');

        $oldPhotoId = (string) Str::uuid();
        $oldStorageKey = 'teacher-photos/' . $oldPhotoId . '.jpg';

        Storage::disk('local')->put($oldStorageKey, 'old photo');

        DB::connection('sqlite')->table('file_assets')->insert([
            'id' => $oldPhotoId,
            'storage_key' => $oldStorageKey,
            'original_name' => 'old-teacher.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 9,
            'sha256_hex' => hash('sha256', 'old photo'),
            'created_at' => now()->toDateTimeString(),
        ]);

        DB::connection('sqlite')->table('teachers')
            ->where('id', $teacher->id)
            ->update(['photo_file_id' => $oldPhotoId]);

        $fileAssetService = Mockery::mock(
            FileAssetService::class
        )->makePartial();

        $fileAssetService->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(
                fn ($asset) => $asset->id === $oldPhotoId
            ))
            ->andThrow(new RuntimeException(
                'Simulasi kegagalan menghapus foto lama.'
            ));

        $this->app->instance(
            FileAssetService::class,
            $fileAssetService
        );

        Log::shouldReceive('error')
            ->once()
            ->with(
                'Gagal menghapus foto lama guru setelah profil diperbarui.',
                Mockery::type('array')
            );

        $this->actingAs($admin)
            ->put(route('admin.teachers.update', $teacher->id), [
                'user_id' => $teacherUser->id,
                'whatsapp_number' => '081298765432',
                'photo' => UploadedFile::fake()->image('new-teacher.jpg'),
            ])
            ->assertRedirect(route('admin.teachers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'whatsapp_number' => '081298765432',
        ]);

        $updatedTeacher = DB::connection('sqlite')->table('teachers')
            ->where('id', $teacher->id)
            ->first();

        $this->assertNotSame(
            $oldPhotoId,
            $updatedTeacher->photo_file_id
        );

        $this->assertDatabaseHas('file_assets', [
            'id' => $oldPhotoId,
            'storage_key' => $oldStorageKey,
        ]);

        Storage::disk('local')->assertExists($oldStorageKey);
    }

    public function test_admin_cannot_open_teacher_from_another_branch(): void
    {
        $admin = $this->createUser('admin');

        $adminBranch = $this->createBranch('Branch Admin');
        $otherBranch = $this->createBranch('Branch Other');

        $this->assignAdminToBranch($admin->id, $adminBranch);

        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $this->assignTeacherToBranch($teacher->id, $otherBranch);

        $this->actingAs($admin)
            ->get(route('admin.teachers.show', $teacher->id))
            ->assertForbidden();
    }

    public function test_admin_can_open_teacher_from_same_branch(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Admin');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $teacherUser = $this->createUser('teacher');

        $teacher = $this->createTeacher($teacherUser);

        $this->assignTeacherToBranch(
            $teacher->id,
            $branch
        );

        $this->actingAs($admin)
            ->get(route('admin.teachers.show', $teacher->id))
            ->assertOk();
    }

    public function test_superadmin_can_open_teacher_from_any_branch(): void
    {
        $superadmin = $this->createUser('superadmin');

        $otherBranch = $this->createBranch('Branch Other');

        $teacherUser = $this->createUser('teacher');

        $teacher = $this->createTeacher($teacherUser);

        $this->assignTeacherToBranch(
            $teacher->id,
            $otherBranch
        );

        $this->actingAs($superadmin)
            ->get(route('admin.teachers.show', $teacher->id))
            ->assertOk();
    }

    public function test_admin_cannot_update_teacher_from_another_branch(): void
    {
        $admin = $this->createUser('admin');

        $adminBranch = $this->createBranch('Branch Admin');
        $otherBranch = $this->createBranch('Branch Other');

        $this->assignAdminToBranch(
            $admin->id,
            $adminBranch
        );

        $teacherUser = $this->createUser('teacher');

        $teacher = $this->createTeacher($teacherUser);

        $this->assignTeacherToBranch(
            $teacher->id,
            $otherBranch
        );

        $data = $this->validTeacherData($teacherUser);

        $data['whatsapp_number'] = '081298765432';

        $this->actingAs($admin)
            ->put(
                route('admin.teachers.update', $teacher->id),
                $data
            )
            ->assertForbidden();

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'whatsapp_number' => '081234567890',
        ]);
    }

    public function test_admin_only_sees_teachers_from_assigned_branch(): void
    {
        $admin = $this->createUser('admin');

        $adminBranch = $this->createBranch('Branch Admin');
        $otherBranch = $this->createBranch('Branch Other');

        $this->assignAdminToBranch(
            $admin->id,
            $adminBranch
        );

        $adminBranchTeacherUser = $this->createUser('teacher');
        $adminBranchTeacher = $this->createTeacher($adminBranchTeacherUser);

        $this->assignTeacherToBranch(
            $adminBranchTeacher->id,
            $adminBranch
        );

        $otherBranchTeacherUser = $this->createUser('teacher');
        $otherBranchTeacher = $this->createTeacher($otherBranchTeacherUser);

        $this->assignTeacherToBranch(
            $otherBranchTeacher->id,
            $otherBranch
        );

        $response = $this->actingAs($admin)
            ->get(route('admin.teachers.index'));

        $response
            ->assertOk()
            ->assertSee($adminBranchTeacher->full_name)
            ->assertDontSee($otherBranchTeacher->full_name);
    }

    public function test_superadmin_sees_teachers_from_all_branches(): void
    {
        $superadmin = $this->createUser('superadmin');

        $branchA = $this->createBranch('Branch A');
        $branchB = $this->createBranch('Branch B');

        $teacherUserA = $this->createUser('teacher');
        $teacherA = $this->createTeacher($teacherUserA);

        $this->assignTeacherToBranch(
            $teacherA->id,
            $branchA
        );

        $teacherUserB = $this->createUser('teacher');
        $teacherB = $this->createTeacher($teacherUserB);

        $this->assignTeacherToBranch(
            $teacherB->id,
            $branchB
        );

        $response = $this->actingAs($superadmin)
            ->get(route('admin.teachers.index'));

        $response
            ->assertOk()
            ->assertSee($teacherA->full_name)
            ->assertSee($teacherB->full_name);
    }

    public function test_admin_cannot_open_teacher_with_future_branch_assignment(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Test');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $teacherUser = $this->createUser('teacher');

        $teacher = $this->createTeacher($teacherUser);

        $this->assignTeacherToBranch(
            $teacher->id,
            $branch,
            '2027-01-01'
        );

        $this->actingAs($admin)
            ->get(route('admin.teachers.show', $teacher->id))
            ->assertForbidden();
    }

    public function test_admin_cannot_open_teacher_with_expired_branch_assignment(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Test');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $teacherUser = $this->createUser('teacher');

        $teacher = $this->createTeacher($teacherUser);

        $this->assignTeacherToBranch(
            $teacher->id,
            $branch,
            '2026-01-01',
            '2026-01-02'
        );

        $this->actingAs($admin)
            ->get(route('admin.teachers.show', $teacher->id))
            ->assertForbidden();
    }

    public function test_admin_can_open_teacher_when_branch_assignment_starts_today(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Test');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $teacherUser = $this->createUser('teacher');

        $teacher = $this->createTeacher($teacherUser);

        $this->assignTeacherToBranch(
            $teacher->id,
            $branch,
            now()->toDateString()
        );

        $this->actingAs($admin)
            ->get(route('admin.teachers.show', $teacher->id))
            ->assertOk();
    }

    public function test_admin_can_open_teacher_when_branch_assignment_ends_today(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Test');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $teacherUser = $this->createUser('teacher');

        $teacher = $this->createTeacher($teacherUser);

        $today = now()->toDateString();

        $this->assignTeacherToBranch(
            $teacher->id,
            $branch,
            '2026-01-01',
            $today
        );

        $this->actingAs($admin)
            ->get(route('admin.teachers.show', $teacher->id))
            ->assertOk();
    }
}
