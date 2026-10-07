<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

        Schema::connection('sqlite')->create('branches', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name');
        });

        Schema::connection('sqlite')->create('branch_admin_assignments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('branch_id', 36);
            $table->char('admin_user_id', 36);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
        });

        Schema::connection('sqlite')->create('student_enrollments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('student_id', 36);
            $table->char('branch_id', 36);
            $table->char('program_id', 36);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->tinyInteger('active_student_key')->nullable();
        });

        Schema::connection('sqlite')->create('programs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('class_type');
            $table->smallInteger('monthly_video_target_override')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::connection('sqlite')->create('students', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('portal_user_id', 36)->unique();
            $table->string('full_name');
            $table->string('school_name')->nullable();
            $table->string('grade_name')->nullable();

            // Kolom untuk relasi foto siswa.
            $table->char('photo_file_id', 36)->nullable();

            $table->date('began_on');
            $table->string('status');
            $table->text('special_notes_internal')->nullable();
            $table->dateTime('created_at')->nullable();
        });

        Schema::connection('sqlite')->create('file_assets', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('storage_key', 500)->unique();
            $table->text('original_name');
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256_hex', 255)->nullable();
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
                Schema::connection('sqlite')->dropIfExists('file_assets');
                Schema::connection('sqlite')->dropIfExists('student_enrollments');
                Schema::connection('sqlite')->dropIfExists('programs');
                Schema::connection('sqlite')->dropIfExists('branch_admin_assignments');
                Schema::connection('sqlite')->dropIfExists('branches');
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

    private function createProgram(): string
    {
        $id = (string) Str::uuid();

        DB::connection('sqlite')->table('programs')->insert([
            'id' => $id,
            'code' => 'TEST-' . Str::upper(Str::random(8)),
            'name' => 'Test Program',
            'class_type' => 'regular',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ]);

        return $id;
    }

    private function assignStudentToBranch(
        string $studentId,
        string $branchId,
        string $startsOn = '2026-01-01',
        ?string $endsOn = null
    ): void {
        $programId = $this->createProgram();

        DB::connection('sqlite')->table('student_enrollments')->insert([
            'id' => (string) Str::uuid(),
            'student_id' => $studentId,
            'branch_id' => $branchId,
            'program_id' => $programId,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'active_student_key' => null,
        ]);
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

        $branch = $this->createBranch('Branch Admin');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $portalUser = $this->createUser('student');

        $studentId = $this->createStudent($portalUser);

        $this->assignStudentToBranch(
            $studentId,
            $branch
        );

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
    public function test_admin_cannot_open_student_from_another_branch(): void
    {
        $admin = $this->createUser('admin');

        $adminBranch = $this->createBranch('Branch Admin');
        $otherBranch = $this->createBranch('Branch Other');

        $this->assignAdminToBranch(
            $admin->id,
            $adminBranch
        );

        $studentUser = $this->createUser('student');
        $studentId = $this->createStudent($studentUser);

        $this->assignStudentToBranch(
            $studentId,
            $otherBranch
        );

        $this->actingAs($admin)
            ->get(route('admin.students.show', $studentId))
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

    public function test_admin_can_upload_student_photo_when_creating_profile(): void
    {
        $admin = $this->createUser('admin');
        $portalUser = $this->createUser('student');
        Storage::fake('local');

        $this->actingAs($admin)
            ->post(route('admin.students.store'), array_merge(
                $this->validStudentData($portalUser),
                [
                    'photo' => UploadedFile::fake()->image('student.jpg'),
                ]
            ))
            ->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('success');

        $student = DB::connection('sqlite')->table('students')
            ->where('portal_user_id', $portalUser->id)
            ->first();

        $this->assertNotNull($student->photo_file_id);

        $asset = DB::connection('sqlite')->table('file_assets')
            ->where('id', $student->photo_file_id)
            ->first();

        $this->assertNotNull($asset);
        $this->assertSame('student.jpg', $asset->original_name);

        Storage::disk('local')->assertExists($asset->storage_key);
    }

    public function test_admin_can_update_student_profile(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Admin');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $portalUser = $this->createUser('student');

        $studentId = $this->createStudent($portalUser);

        $this->assignStudentToBranch(
            $studentId,
            $branch
        );

        $data = $this->validStudentData($portalUser);

        $data['full_name'] = 'Updated Student';
        $data['school_name'] = 'Updated School';

        $this->actingAs($admin)
            ->put(
                route('admin.students.update', $studentId),
                $data
            )
            ->assertRedirect(route('admin.students.show', $studentId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('students', [
            'id' => $studentId,
            'full_name' => 'Updated Student',
            'school_name' => 'Updated School',
        ]);
    }

    public function test_admin_can_replace_student_photo_and_old_file_is_removed(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Admin');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $portalUser = $this->createUser('student');

        $studentId = $this->createStudent($portalUser);

        $this->assignStudentToBranch(
            $studentId,
            $branch
        );

        Storage::fake('local');

        $oldPhotoId = (string) Str::uuid();

        $oldStorageKey = 'student-photos/' . $oldPhotoId . '.jpg';

        Storage::disk('local')->put(
            $oldStorageKey,
            'old photo'
        );

        DB::connection('sqlite')->table('file_assets')->insert([
            'id' => $oldPhotoId,
            'storage_key' => $oldStorageKey,
            'original_name' => 'old-student.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 9,
            'sha256_hex' => hash('sha256', 'old photo'),
            'created_at' => now()->toDateTimeString(),
        ]);

        DB::connection('sqlite')->table('students')
            ->where('id', $studentId)
            ->update([
                'photo_file_id' => $oldPhotoId,
            ]);

        $this->actingAs($admin)
            ->put(
                route('admin.students.update', $studentId),
                array_merge(
                    $this->validStudentData($portalUser),
                    [
                        'photo' => UploadedFile::fake()->image(
                            'updated-student.jpg'
                        ),
                    ]
                )
            )
            ->assertRedirect(route('admin.students.show', $studentId))
            ->assertSessionHas('success');

        $student = DB::connection('sqlite')
            ->table('students')
            ->where('id', $studentId)
            ->first();

        $this->assertNotSame(
            $oldPhotoId,
            $student->photo_file_id
        );

        $this->assertDatabaseMissing(
            'file_assets',
            ['id' => $oldPhotoId]
        );

        Storage::disk('local')
            ->assertMissing($oldStorageKey);

        $newAsset = DB::connection('sqlite')
            ->table('file_assets')
            ->where('id', $student->photo_file_id)
            ->first();

        $this->assertNotNull($newAsset);

        $this->assertSame(
            'updated-student.jpg',
            $newAsset->original_name
        );

        Storage::disk('local')
            ->assertExists($newAsset->storage_key);
    }

    public function test_admin_cannot_upload_pdf_as_student_photo(): void
    {
        $admin = $this->createUser('admin');
        $portalUser = $this->createUser('student');

        Storage::fake('local');

        $this->actingAs($admin)
            ->from(route('admin.students.create'))
            ->post(
                route('admin.students.store'),
                array_merge(
                    $this->validStudentData($portalUser),
                    [
                        'photo' => UploadedFile::fake()->create(
                            'document.pdf',
                            20,
                            'application/pdf'
                        ),
                    ]
                )
            )
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('students', [
            'portal_user_id' => $portalUser->id,
        ]);

        $this->assertSame(
            0,
            DB::connection('sqlite')->table('file_assets')->count()
        );
    }

    public function test_admin_cannot_upload_non_image_as_student_photo(): void
    {
        $admin = $this->createUser('admin');
        $portalUser = $this->createUser('student');

        Storage::fake('local');

        $this->actingAs($admin)
            ->from(route('admin.students.create'))
            ->post(
                route('admin.students.store'),
                array_merge(
                    $this->validStudentData($portalUser),
                    [
                        'photo' => UploadedFile::fake()->create(
                            'notes.txt',
                            20,
                            'text/plain'
                        ),
                    ]
                )
            )
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('students', [
            'portal_user_id' => $portalUser->id,
        ]);

        $this->assertSame(
            0,
            DB::connection('sqlite')->table('file_assets')->count()
        );
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

    public function test_admin_can_open_student_from_same_branch(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Admin');
        $this->assignAdminToBranch($admin->id, $branch);

        $studentUser = $this->createUser('student');
        $studentId = $this->createStudent($studentUser);

        $this->assignStudentToBranch($studentId, $branch);

        $this->actingAs($admin)
            ->get(route('admin.students.show', $studentId))
            ->assertOk();
    }

    public function test_superadmin_can_open_student_from_any_branch(): void
    {
        $superadmin = $this->createUser('superadmin');

        $this->assertSame('superadmin', $superadmin->role_code);
        $this->assertTrue((bool) $superadmin->is_active);

        $otherBranch = $this->createBranch('Branch Other');

        $studentUser = $this->createUser('student');
        $studentId = $this->createStudent($studentUser);

        $this->assignStudentToBranch($studentId, $otherBranch);

        $this->assertTrue(
            \Illuminate\Support\Facades\Gate::forUser($superadmin)
                ->allows(
                    'view',
                    \App\Models\Student::query()->findOrFail($studentId)
                )
        );

        $this->actingAs($superadmin)
            ->get(route('admin.students.show', $studentId))
            ->assertOk();
    }

    public function test_admin_cannot_update_student_from_another_branch(): void
    {
        $admin = $this->createUser('admin');

        $adminBranch = $this->createBranch('Branch Admin');
        $otherBranch = $this->createBranch('Branch Other');

        $this->assignAdminToBranch($admin->id, $adminBranch);

        $studentUser = $this->createUser('student');
        $studentId = $this->createStudent($studentUser);

        $this->assignStudentToBranch($studentId, $otherBranch);

        $data = $this->validStudentData($studentUser);
        $data['full_name'] = 'Should Not Update';

        $this->actingAs($admin)
            ->put(
                route('admin.students.update', $studentId),
                $data
            )
            ->assertForbidden();

        $this->assertDatabaseHas('students', [
            'id' => $studentId,
            'full_name' => $studentUser->full_name,
        ]);
    }

    public function test_admin_only_sees_students_from_assigned_branch(): void
    {
        $admin = $this->createUser('admin');

        $adminBranch = $this->createBranch('Branch Admin');
        $otherBranch = $this->createBranch('Branch Other');

        $this->assignAdminToBranch(
            $admin->id,
            $adminBranch
        );

        $adminBranchStudentUser = $this->createUser('student');
        $adminBranchStudent = $this->createStudent($adminBranchStudentUser);

        $this->assignStudentToBranch(
            $adminBranchStudent,
            $adminBranch
        );

        $otherBranchStudentUser = $this->createUser('student');
        $otherBranchStudent = $this->createStudent($otherBranchStudentUser);

        $this->assignStudentToBranch(
            $otherBranchStudent,
            $otherBranch
        );

        $response = $this->actingAs($admin)
            ->get(route('admin.students.index'));

        $response
            ->assertOk()
            ->assertSee($adminBranchStudentUser->name)
            ->assertDontSee($otherBranchStudentUser->name);
    }

    public function test_superadmin_sees_students_from_all_branches(): void
    {
        $superadmin = $this->createUser('superadmin');

        $branchA = $this->createBranch('Branch A');
        $branchB = $this->createBranch('Branch B');

        $studentUserA = $this->createUser('student');
        $studentA = $this->createStudent($studentUserA);

        $this->assignStudentToBranch(
            $studentA,
            $branchA
        );

        $studentUserB = $this->createUser('student');
        $studentB = $this->createStudent($studentUserB);

        $this->assignStudentToBranch(
            $studentB,
            $branchB
        );

        $response = $this->actingAs($superadmin)
            ->get(route('admin.students.index'));

        $response
            ->assertOk()
            ->assertSee($studentUserA->name)
            ->assertSee($studentUserB->name);
    }

    public function test_admin_cannot_open_student_with_future_branch_enrollment(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Test');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $studentUser = $this->createUser('student');

        $student = $this->createStudent($studentUser);

        $this->assignStudentToBranch(
            $student,
            $branch,
            '2027-01-01'
        );

        $this->actingAs($admin)
            ->get(route('admin.students.show', $student))
            ->assertForbidden();
    }

    public function test_admin_cannot_open_student_with_expired_branch_enrollment(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Test');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $studentUser = $this->createUser('student');

        $student = $this->createStudent($studentUser);

        $this->assignStudentToBranch(
            $student,
            $branch,
            '2026-01-01',
            '2026-01-02'
        );

        $this->actingAs($admin)
            ->get(route('admin.students.show', $student))
            ->assertForbidden();
    }

    public function test_admin_can_open_student_when_branch_enrollment_starts_today(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Test');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $studentUser = $this->createUser('student');

        $student = $this->createStudent($studentUser);

        $this->assignStudentToBranch(
            $student,
            $branch,
            now()->toDateString()
        );

        $this->actingAs($admin)
            ->get(route('admin.students.show', $student))
            ->assertOk();
    }

    public function test_admin_can_open_student_when_branch_enrollment_ends_today(): void
    {
        $admin = $this->createUser('admin');

        $branch = $this->createBranch('Branch Test');

        $this->assignAdminToBranch(
            $admin->id,
            $branch
        );

        $studentUser = $this->createUser('student');

        $student = $this->createStudent($studentUser);

        $this->assignStudentToBranch(
            $student,
            $branch,
            '2026-01-01',
            now()->toDateString()
        );

        $this->actingAs($admin)
            ->get(route('admin.students.show', $student))
            ->assertOk();
    }
}