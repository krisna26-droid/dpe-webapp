<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\QuarterlyReportFile;
use App\Models\ReportCycle;
use App\Models\User;
use App\Services\ReportCycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuarterlyReportFileModelTest extends TestCase
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
        return app(ReportCycleService::class)->create([
            'student_id' => $this->createStudentId(),
            'cycle_number' => 1,
            'start_month' => '2026-01-01',
            'end_month' => '2026-03-01',
        ]);
    }

    private function createStudentId(): string
    {
        $studentId = (string) Str::uuid();

        DB::table('students')->insert([
            'id' => $studentId,
        ]);

        return $studentId;
    }

    private function createQuarterlyReportFile(
        ReportCycle $cycle,
        FileAsset $file,
        User $user,
        int $version = 1
    ): QuarterlyReportFile {
        return QuarterlyReportFile::query()->create([
            'id' => (string) Str::uuid(),
            'cycle_id' => $cycle->id,
            'file_id' => $file->id,
            'version_number' => $version,
            'generated_by_user_id' => $user->id,
            'generated_at' => '2026-10-01 10:00:00',
            'source_snapshot_hash' => 'snapshot-hash-001',
        ]);
    }

    public function test_quarterly_report_file_uses_expected_table(): void
    {
        $model = new QuarterlyReportFile();

        $this->assertSame(
            'quarterly_report_files',
            $model->getTable()
        );
    }

    public function test_quarterly_report_file_does_not_use_incrementing_id(): void
    {
        $model = new QuarterlyReportFile();

        $this->assertFalse($model->getIncrementing());
        $this->assertSame('string', $model->getKeyType());
    }

    public function test_quarterly_report_file_has_expected_fillable_fields(): void
    {
        $model = new QuarterlyReportFile();

        $this->assertEqualsCanonicalizing(
            [
                'id',
                'cycle_id',
                'file_id',
                'version_number',
                'generated_by_user_id',
                'generated_at',
                'source_snapshot_hash',
            ],
            $model->getFillable()
        );
    }

    public function test_quarterly_report_file_does_not_use_timestamps(): void
    {
        $model = new QuarterlyReportFile();

        $this->assertFalse($model->usesTimestamps());
    }

    public function test_quarterly_report_file_casts_version_and_generated_at(): void
    {
        $model = new QuarterlyReportFile();

        $this->assertSame(
            'integer',
            $model->getCasts()['version_number']
        );

        $this->assertSame(
            'datetime',
            $model->getCasts()['generated_at']
        );
    }

    public function test_quarterly_report_file_belongs_to_report_cycle(): void
    {
        $user = $this->createUser();
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();

        $reportFile = $this->createQuarterlyReportFile(
            $cycle,
            $file,
            $user
        );

        $this->assertTrue(
            $reportFile->cycle()->getRelated() instanceof ReportCycle
        );

        $this->assertSame(
            $cycle->id,
            $reportFile->cycle->id
        );
    }

    public function test_quarterly_report_file_belongs_to_file_asset(): void
    {
        $user = $this->createUser();
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();

        $reportFile = $this->createQuarterlyReportFile(
            $cycle,
            $file,
            $user
        );

        $this->assertTrue(
            $reportFile->file()->getRelated() instanceof FileAsset
        );

        $this->assertSame(
            $file->id,
            $reportFile->file->id
        );
    }

    public function test_quarterly_report_file_belongs_to_generated_by_user(): void
    {
        $user = $this->createUser();
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();

        $reportFile = $this->createQuarterlyReportFile(
            $cycle,
            $file,
            $user
        );

        $this->assertTrue(
            $reportFile->generatedBy()->getRelated() instanceof User
        );

        $this->assertSame(
            $user->id,
            $reportFile->generatedBy->id
        );
    }

    public function test_report_cycle_has_many_quarterly_report_files(): void
    {
        $user = $this->createUser();
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();

        $reportFile = $this->createQuarterlyReportFile(
            $cycle,
            $file,
            $user
        );

        $this->assertTrue(
            $cycle->quarterlyReportFiles()->exists()
        );

        $this->assertTrue(
            $cycle->quarterlyReportFiles->contains(
                $reportFile->id
            )
        );
    }

    public function test_file_asset_has_many_quarterly_report_files(): void
    {
        $user = $this->createUser();
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();

        $reportFile = $this->createQuarterlyReportFile(
            $cycle,
            $file,
            $user
        );

        $this->assertTrue(
            $file->quarterlyReportFiles()->exists()
        );

        $this->assertTrue(
            $file->quarterlyReportFiles->contains(
                $reportFile->id
            )
        );
    }

    public function test_user_has_many_generated_quarterly_report_files(): void
    {
        $user = $this->createUser();
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();

        $reportFile = $this->createQuarterlyReportFile(
            $cycle,
            $file,
            $user
        );

        $this->assertTrue(
            $user->generatedQuarterlyReportFiles()->exists()
        );

        $this->assertTrue(
            $user->generatedQuarterlyReportFiles->contains(
                $reportFile->id
            )
        );
    }

    public function test_quarterly_report_file_can_store_nullable_snapshot_hash(): void
    {
        $user = $this->createUser();
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();

        $reportFile = QuarterlyReportFile::query()->create([
            'id' => (string) Str::uuid(),
            'cycle_id' => $cycle->id,
            'file_id' => $file->id,
            'version_number' => 1,
            'generated_by_user_id' => $user->id,
            'generated_at' => '2026-10-01 10:00:00',
            'source_snapshot_hash' => null,
        ]);

        $this->assertNull(
            $reportFile->source_snapshot_hash
        );
    }

    public function test_quarterly_report_file_stores_version_number(): void
    {
        $user = $this->createUser();
        $cycle = $this->createReportCycle();
        $file = $this->createFileAsset();

        $reportFile = $this->createQuarterlyReportFile(
            $cycle,
            $file,
            $user,
            1
        );

        $this->assertSame(1, $reportFile->version_number);
    }

    public function test_quarterly_report_file_has_unique_version_per_cycle(): void
    {
        $user = $this->createUser();
        $cycle = $this->createReportCycle();

        $fileA = $this->createFileAsset();
        $fileB = $this->createFileAsset();

        $this->createQuarterlyReportFile(
            $cycle,
            $fileA,
            $user,
            1
        );

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->createQuarterlyReportFile(
            $cycle,
            $fileB,
            $user,
            1
        );
    }

    public function test_quarterly_report_file_cannot_reuse_file_asset(): void
    {
        $user = $this->createUser();
        $cycleA = $this->createReportCycle();
        $cycleB = $this->createReportCycle();

        $file = $this->createFileAsset();

        $this->createQuarterlyReportFile(
            $cycleA,
            $file,
            $user,
            1
        );

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->createQuarterlyReportFile(
            $cycleB,
            $file,
            $user,
            1
        );
    }
}
