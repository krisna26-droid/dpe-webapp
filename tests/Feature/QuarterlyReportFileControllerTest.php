<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\QuarterlyReportFile;
use App\Models\ReportCycle;
use App\Models\User;
use App\Services\QuarterlyReportFileService;
use App\Services\ReportCycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuarterlyReportFileControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('students', function (Blueprint $table) {
            $table->string('id', 36)->primary();
        });

        Schema::create('report_cycles', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('student_id', 36);
            $table->unsignedInteger('cycle_number');
            $table->date('start_month');
            $table->date('end_month')->check(
                'end_month >= start_month'
            );
            $table->date('share_due_on')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['student_id', 'cycle_number'],
                'uq_report_cycles_number'
            );

            $table->unique(
                ['student_id', 'start_month'],
                'uq_report_cycles_start'
            );

            $table->unique(
                ['id', 'student_id'],
                'uq_report_cycles_id_student'
            );

            $table->foreign('student_id')
                ->references('id')
                ->on('students');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('username');
            $table->string('email');
            $table->string('password_hash');
            $table->string('full_name');
            $table->string('role_code');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('file_assets', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('storage_key');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('quarterly_report_files', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('cycle_id', 36);
            $table->string('file_id', 36);
            $table->unsignedInteger('version_number');
            $table->string('generated_by_user_id', 36);
            $table->timestamp('generated_at')->useCurrent();
            $table->text('source_snapshot_hash')->nullable();

            $table->unique(
                ['cycle_id', 'version_number'],
                'uq_quarterly_report_version'
            );

            $table->unique(
                'file_id',
                'uq_quarterly_report_file'
            );

            $table->foreign('cycle_id')
                ->references('id')
                ->on('report_cycles')
                ->cascadeOnDelete();

            $table->foreign('file_id')
                ->references('id')
                ->on('file_assets');

            $table->foreign('generated_by_user_id')
                ->references('id')
                ->on('users');
        });

        DB::table('students')->insert([
            ['id' => 'student-a'],
            ['id' => 'student-b'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    private function makeUser(
        string $roleCode = 'superadmin',
        bool $isActive = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);
    }

    private function authenticateAsSuperadmin(): User
    {
        $user = $this->makeUser('superadmin');

        $this->actingAs($user);

        return $user;
    }

    private function makeFileAsset(): FileAsset
    {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'quarterly-reports/' . Str::uuid() . '.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'created_at' => now(),
        ]);
    }

    private function makeReportCycle(
        string $studentId = 'student-a'
    ): ReportCycle {
        return app(ReportCycleService::class)->create([
            'student_id' => $studentId,
            'cycle_number' => 1,
            'start_month' => '2026-01-01',
            'end_month' => '2026-03-01',
        ]);
    }

    private function createQuarterlyReportFile(
        ReportCycle $cycle,
        FileAsset $file,
        User $user,
        int $version = 1,
        ?string $generatedAt = null
    ): QuarterlyReportFile {
        $data = [
            'cycle_id' => $cycle->id,
            'file_id' => $file->id,
            'version_number' => $version,
            'generated_by_user_id' => $user->id,
        ];

        if ($generatedAt !== null) {
            $data['generated_at'] = $generatedAt;
        }

        return app(QuarterlyReportFileService::class)->create($data);
    }

    public function test_store_creates_quarterly_report_file(): void
    {
        $this->authenticateAsSuperadmin();

        $user = User::query()->first();
        $file = $this->makeFileAsset();
        $cycle = $this->makeReportCycle();

        $response = $this->postJson('/superadmin/quarterly-report-files', [
            'cycle_id' => $cycle->id,
            'file_id' => $file->id,
            'version_number' => 1,
            'generated_by_user_id' => $user->id,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath(
                'message',
                'Quarterly report file created successfully.'
            )
            ->assertJsonPath(
                'data.cycle_id',
                $cycle->id
            )
            ->assertJsonPath(
                'data.file_id',
                $file->id
            )
            ->assertJsonPath(
                'data.version_number',
                1
            )
            ->assertJsonPath(
                'data.generated_by_user_id',
                $user->id
            );

        $this->assertDatabaseHas('quarterly_report_files', [
            'cycle_id' => $cycle->id,
            'file_id' => $file->id,
            'version_number' => 1,
            'generated_by_user_id' => $user->id,
        ]);
    }

    public function test_show_returns_quarterly_report_file(): void
    {
        $this->authenticateAsSuperadmin();

        $user = User::query()->first();
        $file = $this->makeFileAsset();
        $cycle = $this->makeReportCycle();

        $quarterlyReportFile = $this->createQuarterlyReportFile(
            $cycle,
            $file,
            $user
        );

        $response = $this->getJson(
            '/superadmin/quarterly-report-files/' . $quarterlyReportFile->id
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $quarterlyReportFile->id
            )
            ->assertJsonPath(
                'data.cycle_id',
                $cycle->id
            )
            ->assertJsonPath(
                'data.file_id',
                $file->id
            );
    }

    public function test_by_cycle_returns_files_ordered_by_version_descending(): void
    {
        $this->authenticateAsSuperadmin();

        $user = User::query()->first();
        $cycle = $this->makeReportCycle();

        $fileOne = $this->makeFileAsset();
        $fileTwo = $this->makeFileAsset();

        $versionOne = $this->createQuarterlyReportFile(
            $cycle,
            $fileOne,
            $user,
            1
        );

        $versionTwo = $this->createQuarterlyReportFile(
            $cycle,
            $fileTwo,
            $user,
            2
        );

        $response = $this->getJson(
            '/superadmin/cycles/' . $cycle->id . '/quarterly-report-files'
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.0.id',
                $versionTwo->id
            )
            ->assertJsonPath(
                'data.0.version_number',
                2
            )
            ->assertJsonPath(
                'data.1.id',
                $versionOne->id
            )
            ->assertJsonPath(
                'data.1.version_number',
                1
            );
    }

    public function test_by_file_returns_quarterly_report_file(): void
    {
        $this->authenticateAsSuperadmin();

        $user = User::query()->first();
        $file = $this->makeFileAsset();
        $cycle = $this->makeReportCycle();

        $quarterlyReportFile = $this->createQuarterlyReportFile(
            $cycle,
            $file,
            $user
        );

        $response = $this->getJson(
            '/superadmin/files/' . $file->id . '/quarterly-report-files'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $quarterlyReportFile->id
            )
            ->assertJsonPath(
                'data.file_id',
                $file->id
            );
    }

    public function test_by_generator_returns_generated_files(): void
    {
        $this->authenticateAsSuperadmin();

        $user = User::query()->first();
        $cycle = $this->makeReportCycle();

        $fileOne = $this->makeFileAsset();
        $fileTwo = $this->makeFileAsset();

        $first = $this->createQuarterlyReportFile(
            $cycle,
            $fileOne,
            $user,
            1,
            '2026-10-01 10:00:00'
        );

        $second = $this->createQuarterlyReportFile(
            $cycle,
            $fileTwo,
            $user,
            2,
            '2026-10-02 10:00:00'
        );

        $response = $this->getJson(
            '/superadmin/users/' . $user->id . '/quarterly-report-files'
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.0.id',
                $second->id
            )
            ->assertJsonPath(
                'data.1.id',
                $first->id
            );
    }

    public function test_show_returns_not_found_for_unknown_id(): void
    {
        $this->authenticateAsSuperadmin();

        $response = $this->getJson(
            '/superadmin/quarterly-report-files/' . Str::uuid()
        );

        $response->assertNotFound();
    }
}
