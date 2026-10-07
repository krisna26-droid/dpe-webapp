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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuarterlyReportFileServiceTest extends TestCase
{
    private QuarterlyReportFileService $service;

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

        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password_hash');
            $table->string('full_name');
            $table->string('role_code');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('file_assets', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('storage_key', 500)->unique();
            $table->text('original_name');
            $table->string('mime_type');
            $table->bigInteger('size_bytes');
            $table->string('sha256_hex')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('report_cycles', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('student_id', 36);
            $table->unsignedInteger('cycle_number');
            $table->date('start_month');
            $table->date('end_month');
            $table->date('share_due_on')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['student_id', 'cycle_number']);
            $table->foreign('student_id')->references('id')->on('students');
        });

        Schema::create('quarterly_report_files', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('cycle_id', 36);
            $table->string('file_id', 36)->unique();
            $table->integer('version_number');
            $table->string('generated_by_user_id', 36);
            $table->dateTime('generated_at')->useCurrent();
            $table->text('source_snapshot_hash')->nullable();
            $table->unique(['cycle_id', 'version_number']);
            $table->foreign('cycle_id')->references('id')->on('report_cycles')
                ->onDelete('cascade');
            $table->foreign('file_id')->references('id')->on('file_assets');
            $table->foreign('generated_by_user_id')->references('id')->on('users');
        });

        $this->service = app(QuarterlyReportFileService::class);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    private function createUser(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'superadmin_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => password_hash(
                'password',
                PASSWORD_BCRYPT
            ),
            'full_name' => 'Test Superadmin',
            'role_code' => 'superadmin',
            'is_active' => true,
        ]);
    }

    private function createFileAsset(): FileAsset
    {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'quarterly-reports/' . Str::uuid() . '.pdf',
            'original_name' => 'quarterly-report.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'sha256_hex' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
        ]);
    }

    private function createReportCycle(): ReportCycle
    {
        $studentId = (string) Str::uuid();

        DB::table('students')->insert([
            'id' => $studentId,
        ]);

        return app(ReportCycleService::class)->create([
            'student_id' => $studentId,
            'cycle_number' => 1,
            'start_month' => '2026-01-01',
            'end_month' => '2026-03-01',
        ]);
    }

    public function test_it_creates_quarterly_report_file(): void
    {
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();
        $user = $this->createUser();

        $result = $this->service->create([
            'cycle_id' => $cycle->id,
            'file_id' => $file->id,
            'version_number' => 1,
            'generated_by_user_id' => $user->id,
            'generated_at' => '2026-10-01 10:00:00',
            'source_snapshot_hash' => 'snapshot-hash-001',
        ]);

        $this->assertInstanceOf(
            QuarterlyReportFile::class,
            $result
        );

        $this->assertSame($cycle->id, $result->cycle_id);
        $this->assertSame($file->id, $result->file_id);
        $this->assertSame(1, $result->version_number);
        $this->assertSame($user->id, $result->generated_by_user_id);
        $this->assertSame(
            'snapshot-hash-001',
            $result->source_snapshot_hash
        );
    }

    public function test_it_generates_id_when_id_is_not_provided(): void
    {
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();
        $user = $this->createUser();

        $result = $this->service->create([
            'cycle_id' => $cycle->id,
            'file_id' => $file->id,
            'version_number' => 1,
            'generated_by_user_id' => $user->id,
        ]);

        $this->assertNotEmpty($result->id);
        $this->assertSame(36, strlen($result->id));
    }

    public function test_it_can_find_by_id(): void
    {
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();
        $user = $this->createUser();

        $created = $this->service->create([
            'cycle_id' => $cycle->id,
            'file_id' => $file->id,
            'version_number' => 1,
            'generated_by_user_id' => $user->id,
        ]);

        $result = $this->service->findById($created->id);

        $this->assertSame(
            $created->id,
            $result->id
        );
    }

    public function test_it_gets_files_by_cycle_ordered_by_version_descending(): void
    {
        $cycle = $this->createReportCycle();
        $user = $this->createUser();

        $fileA = $this->createFileAsset();
        $fileB = $this->createFileAsset();

        $this->service->create([
            'cycle_id' => $cycle->id,
            'file_id' => $fileA->id,
            'version_number' => 1,
            'generated_by_user_id' => $user->id,
        ]);

        $this->service->create([
            'cycle_id' => $cycle->id,
            'file_id' => $fileB->id,
            'version_number' => 2,
            'generated_by_user_id' => $user->id,
        ]);

        $result = $this->service->getByCycle($cycle);

        $this->assertCount(2, $result);
        $this->assertSame(2, $result->first()->version_number);
        $this->assertSame(1, $result->last()->version_number);
    }

    public function test_it_gets_file_by_file_asset(): void
    {
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();
        $user = $this->createUser();

        $created = $this->service->create([
            'cycle_id' => $cycle->id,
            'file_id' => $file->id,
            'version_number' => 1,
            'generated_by_user_id' => $user->id,
        ]);

        $result = $this->service->getByFile($file);

        $this->assertSame(
            $created->id,
            $result->id
        );
    }

    public function test_it_gets_files_by_generator(): void
    {
        $cycle = $this->createReportCycle();
        $user = $this->createUser();

        $fileA = $this->createFileAsset();
        $fileB = $this->createFileAsset();

        $this->service->create([
            'cycle_id' => $cycle->id,
            'file_id' => $fileA->id,
            'version_number' => 1,
            'generated_by_user_id' => $user->id,
            'generated_at' => '2026-10-01 10:00:00',
        ]);

        $this->service->create([
            'cycle_id' => $cycle->id,
            'file_id' => $fileB->id,
            'version_number' => 2,
            'generated_by_user_id' => $user->id,
            'generated_at' => '2026-10-02 10:00:00',
        ]);

        $result = $this->service->getByGenerator($user);

        $this->assertCount(2, $result);
        $this->assertSame(2, $result->first()->version_number);
    }
}
