<?php

namespace Tests\Feature;

use App\Models\MonthlyReport;
use App\Models\ReportCycle;
use App\Services\MonthlyReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MonthlyReportServiceTest extends TestCase
{
    private MonthlyReportService $service;

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

        $this->createTestSchema();
        $this->service = app(MonthlyReportService::class);
    }

    protected function tearDown(): void
    {
        try {
            foreach (
                [
                    'monthly_reports',
                    'student_teacher_assignments',
                    'student_enrollments',
                    'report_cycles',
                    'system_settings',
                ] as $table
            ) {
                Schema::connection('sqlite')->dropIfExists($table);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function createTestSchema(): void
    {
        Schema::connection('sqlite')->create(
            'system_settings',
            function (Blueprint $table) {
                $table->unsignedSmallInteger('id')->primary();
                $table->string('organization_name');
                $table->unsignedSmallInteger(
                    'default_report_due_day'
                );
                $table->unsignedSmallInteger(
                    'default_monthly_video_target'
                );
                $table->dateTime('updated_at')->nullable();
            }
        );

        Schema::connection('sqlite')->create(
            'report_cycles',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('student_id', 36);
                $table->unsignedInteger('cycle_number');
                $table->date('start_month');
                $table->date('end_month');
                $table->date('share_due_on')->nullable();
                $table->dateTime('created_at')->nullable();
            }
        );

        Schema::connection('sqlite')->create(
            'student_enrollments',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('student_id', 36);
                $table->char('branch_id', 36);
                $table->char('program_id', 36);
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
            }
        );

        Schema::connection('sqlite')->create(
            'student_teacher_assignments',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('student_id', 36);
                $table->char('teacher_id', 36);
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
                $table->boolean('is_report_owner')->default(false);
            }
        );

        Schema::connection('sqlite')->create(
            'monthly_reports',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('cycle_id', 36);
                $table->char('student_id', 36);
                $table->char('branch_id', 36);
                $table->char('teacher_id', 36);
                $table->date('report_month');
                $table->date('due_on');
                $table->unsignedSmallInteger('video_target');
                $table->string('status');
                $table->text('development_summary')->nullable();
                $table->text('parent_challenges_summary')->nullable();
                $table->text('parent_message')->nullable();
                $table->text('internal_teacher_note')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->char('approved_by_user_id', 36)->nullable();
                $table->json('approved_snapshot')->nullable();
                $table->timestamps();

                $table->unique(['student_id', 'report_month']);
                $table->unique(['cycle_id', 'report_month']);
            }
        );
    }

    private function createSettings(
        int $dueDay = 31,
        int $videoTarget = 4
    ): void {
        DB::connection('sqlite')
            ->table('system_settings')
            ->insert([
                'id' => 1,
                'organization_name' => 'Dharma Private English',
                'default_report_due_day' => $dueDay,
                'default_monthly_video_target' => $videoTarget,
            ]);
    }

    private function createCycle(
        string $start = '2024-01-01',
        string $end = '2024-03-01'
    ): ReportCycle {
        $id = (string) Str::uuid();
        $studentId = (string) Str::uuid();

        DB::connection('sqlite')
            ->table('report_cycles')
            ->insert([
                'id' => $id,
                'student_id' => $studentId,
                'cycle_number' => 1,
                'start_month' => $start,
                'end_month' => $end,
                'created_at' => now()->toDateTimeString(),
            ]);

        return ReportCycle::query()->findOrFail($id);
    }

    private function createEnrollment(
        string $studentId,
        string $branchId,
        string $startsOn = '2023-01-01',
        ?string $endsOn = null
    ): void {
        DB::connection('sqlite')
            ->table('student_enrollments')
            ->insert([
                'id' => (string) Str::uuid(),
                'student_id' => $studentId,
                'branch_id' => $branchId,
                'program_id' => (string) Str::uuid(),
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);
    }

    private function createReportOwner(
        string $studentId,
        string $teacherId,
        string $startsOn = '2023-01-01',
        ?string $endsOn = null,
        bool $isOwner = true
    ): void {
        DB::connection('sqlite')
            ->table('student_teacher_assignments')
            ->insert([
                'id' => (string) Str::uuid(),
                'student_id' => $studentId,
                'teacher_id' => $teacherId,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'is_report_owner' => $isOwner,
            ]);
    }

    public function test_it_generates_monthly_reports_for_a_cycle(): void
    {
        $this->createSettings();

        $cycle = $this->createCycle(
            '2024-01-01',
            '2024-03-01'
        );

        $branchId = (string) Str::uuid();
        $teacherId = (string) Str::uuid();

        $this->createEnrollment(
            $cycle->student_id,
            $branchId
        );

        $this->createReportOwner(
            $cycle->student_id,
            $teacherId
        );

        $reports = $this->service->generateForCycle($cycle);

        $this->assertCount(3, $reports);

        $this->assertSame(
            ['2024-01-01', '2024-02-01', '2024-03-01'],
            $reports->pluck('report_month')
                ->map(fn($date) => substr((string) $date, 0, 10))
                ->all()
        );

        $this->assertSame(
            '2024-02-29',
            substr((string) $reports->get(1)->due_on, 0, 10)
        );

        $this->assertSame(
            $branchId,
            $reports->first()->branch_id
        );

        $this->assertSame(
            $teacherId,
            $reports->first()->teacher_id
        );

        $this->assertSame(
            'draft',
            $reports->first()->status
        );

        $this->assertDatabaseCount('monthly_reports', 3);
    }

    public function test_it_does_not_duplicate_existing_reports(): void
    {
        $this->createSettings();

        $cycle = $this->createCycle(
            '2024-01-01',
            '2024-02-01'
        );

        $this->createEnrollment(
            $cycle->student_id,
            (string) Str::uuid()
        );

        $this->createReportOwner(
            $cycle->student_id,
            (string) Str::uuid()
        );

        $firstRun = $this->service->generateForCycle($cycle);
        $secondRun = $this->service->generateForCycle($cycle);

        $this->assertCount(2, $firstRun);
        $this->assertCount(2, $secondRun);
        $this->assertDatabaseCount('monthly_reports', 2);
    }

    public function test_it_fails_when_no_report_owner_exists(): void
    {
        $this->createSettings();

        $cycle = $this->createCycle(
            '2024-01-01',
            '2024-01-01'
        );

        $this->createEnrollment(
            $cycle->student_id,
            (string) Str::uuid()
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Guru pemilik laporan tidak ditemukan'
        );

        try {
            $this->service->generateForCycle($cycle);
        } finally {
            $this->assertDatabaseCount('monthly_reports', 0);
        }
    }

    public function test_it_fails_when_no_enrollment_exists(): void
    {
        $this->createSettings();

        $cycle = $this->createCycle(
            '2024-01-01',
            '2024-01-01'
        );

        $this->createReportOwner(
            $cycle->student_id,
            (string) Str::uuid()
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Enrollment cabang tidak ditemukan'
        );

        try {
            $this->service->generateForCycle($cycle);
        } finally {
            $this->assertDatabaseCount('monthly_reports', 0);
        }
    }

    public function test_it_fails_when_multiple_branches_are_active_for_report_month(): void
    {
        $this->createSettings();

        $cycle = $this->createCycle(
            '2024-01-01',
            '2024-01-01'
        );

        $branchA = (string) Str::uuid();
        $branchB = (string) Str::uuid();
        $teacherId = (string) Str::uuid();

        $this->createEnrollment(
            $cycle->student_id,
            $branchA
        );

        $this->createEnrollment(
            $cycle->student_id,
            $branchB
        );

        $this->createReportOwner(
            $cycle->student_id,
            $teacherId
        );

        try {
            $this->service->generateForCycle($cycle);

            $this->fail(
                'Enrollment dari dua cabang berbeda seharusnya menimbulkan konflik.'
            );
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Enrollment cabang ambigu',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_it_fails_when_multiple_report_owners_are_active(): void
    {
        $this->createSettings();

        $cycle = $this->createCycle(
            '2024-01-01',
            '2024-01-01'
        );

        $branchId = (string) Str::uuid();

        $this->createEnrollment(
            $cycle->student_id,
            $branchId
        );

        $this->createReportOwner(
            $cycle->student_id,
            (string) Str::uuid()
        );

        $this->createReportOwner(
            $cycle->student_id,
            (string) Str::uuid()
        );

        try {
            $this->service->generateForCycle($cycle);

            $this->fail(
                'Dua guru pemilik aktif seharusnya menimbulkan konflik.'
            );
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Guru pemilik laporan ambigu',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('monthly_reports', 0);
    }

    
    public function test_it_fails_when_report_month_belongs_to_another_cycle(): void
    {
        $this->createSettings();

        // Siklus pertama mencakup Januari sampai Februari.
        $cycleA = $this->createCycle(
            '2024-01-01',
            '2024-02-01'
        );

        $branchId = (string) Str::uuid();
        $teacherId = (string) Str::uuid();

        $this->createEnrollment(
            $cycleA->student_id,
            $branchId
        );

        $this->createReportOwner(
            $cycleA->student_id,
            $teacherId
        );

        // Buat laporan untuk siklus pertama.
        $this->service->generateForCycle($cycleA);

        // Siklus kedua memakai siswa yang sama dan mencakup Februari.
        $cycleBId = (string) Str::uuid();

        DB::connection('sqlite')
            ->table('report_cycles')
            ->insert([
                'id' => $cycleBId,
                'student_id' => $cycleA->student_id,
                'cycle_number' => 2,
                'start_month' => '2024-02-01',
                'end_month' => '2024-02-01',
                'created_at' => now()->toDateTimeString(),
            ]);

        $cycleB = ReportCycle::query()->findOrFail($cycleBId);

        try {
            $this->service->generateForCycle($cycleB);

            $this->fail(
                'Siklus kedua tidak boleh mengambil laporan milik siklus pertama.'
            );
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Laporan 2024-02-01 sudah terdaftar',
                $exception->getMessage()
            );
        }

        // Dua laporan awal tetap utuh; tidak ada duplikasi Februari.
        $this->assertDatabaseCount('monthly_reports', 2);

        $this->assertTrue(
            DB::connection('sqlite')
                ->table('monthly_reports')
                ->where('cycle_id', $cycleA->id)
                ->where('student_id', $cycleA->student_id)
                ->whereDate('report_month', '2024-02-01')
                ->exists(),
            'Laporan Februari seharusnya tetap dimiliki siklus pertama.'
        );

        $this->assertFalse(
            DB::connection('sqlite')
                ->table('monthly_reports')
                ->where('cycle_id', $cycleB->id)
                ->whereDate('report_month', '2024-02-01')
                ->exists(),
            'Siklus kedua tidak boleh memiliki laporan Februari.'
        );
    }

    
    public function test_it_fails_when_cycle_end_month_is_before_start_month(): void
    {
        $this->createSettings();

        $cycle = $this->createCycle(
            '2024-03-01',
            '2024-01-01'
        );

        try {
            $this->service->generateForCycle($cycle);

            $this->fail(
                'Siklus dengan periode akhir sebelum periode awal harus ditolak.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Periode akhir siklus tidak boleh sebelum periode awal.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('monthly_reports', 0);
    }

    
    public function test_it_fails_when_report_due_day_is_invalid(): void
    {
        $this->createSettings(dueDay: 0);

        $cycle = $this->createCycle(
            '2024-01-01',
            '2024-01-01'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Tanggal tenggat laporan pada pengaturan sistem tidak valid.'
        );

        try {
            $this->service->generateForCycle($cycle);
        } finally {
            $this->assertDatabaseCount('monthly_reports', 0);
        }
    }

    public function test_it_fails_when_monthly_video_target_is_negative(): void
    {
        $this->createSettings(videoTarget: -1);

        $cycle = $this->createCycle(
            '2024-01-01',
            '2024-01-01'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Target video bulanan tidak boleh negatif.'
        );

        try {
            $this->service->generateForCycle($cycle);
        } finally {
            $this->assertDatabaseCount('monthly_reports', 0);
        }
    }
    
    public function test_it_uses_enrollment_and_teacher_assignment_valid_for_each_report_month(): void
    {
        $this->createSettings();

        $cycle = $this->createCycle(
            '2024-01-01',
            '2024-03-01'
        );

        $branchA = (string) Str::uuid();
        $branchB = (string) Str::uuid();

        $teacherA = (string) Str::uuid();
        $teacherB = (string) Str::uuid();

        // Cabang A berlaku sampai Januari.
        $this->createEnrollment(
            $cycle->student_id,
            $branchA,
            '2023-01-01',
            '2024-01-31'
        );

        // Cabang B mulai berlaku Februari.
        $this->createEnrollment(
            $cycle->student_id,
            $branchB,
            '2024-02-01'
        );

        // Guru A berlaku sampai Januari.
        $this->createReportOwner(
            $cycle->student_id,
            $teacherA,
            '2023-01-01',
            '2024-01-31'
        );

        // Guru B mulai berlaku Februari.
        $this->createReportOwner(
            $cycle->student_id,
            $teacherB,
            '2024-02-01'
        );

        $reports = $this->service->generateForCycle($cycle);

        $this->assertCount(3, $reports);

        $this->assertSame(
            $branchA,
            $reports->get(0)->branch_id
        );

        $this->assertSame(
            $teacherA,
            $reports->get(0)->teacher_id
        );

        $this->assertSame(
            $branchB,
            $reports->get(1)->branch_id
        );

        $this->assertSame(
            $teacherB,
            $reports->get(1)->teacher_id
        );

        $this->assertSame(
            $branchB,
            $reports->get(2)->branch_id
        );

        $this->assertSame(
            $teacherB,
            $reports->get(2)->teacher_id
        );

        $this->assertDatabaseCount('monthly_reports', 3);
    }
    
    public function test_it_rolls_back_all_reports_when_a_later_month_fails(): void
    {
        $this->createSettings();

        $cycle = $this->createCycle(
            '2024-01-01',
            '2024-02-01'
        );

        $branchA = (string) Str::uuid();
        $branchB = (string) Str::uuid();

        $teacherId = (string) Str::uuid();

        // Januari hanya memiliki satu cabang.
        $this->createEnrollment(
            $cycle->student_id,
            $branchA,
            '2023-01-01',
            '2024-01-31'
        );

        // Februari memiliki dua cabang aktif sehingga harus gagal.
        $this->createEnrollment(
            $cycle->student_id,
            $branchB,
            '2024-02-01'
        );

        $this->createEnrollment(
            $cycle->student_id,
            $branchA,
            '2024-02-01'
        );

        $this->createReportOwner(
            $cycle->student_id,
            $teacherId
        );

        try {
            $this->service->generateForCycle($cycle);

            $this->fail(
                'Pembuatan laporan seharusnya gagal karena cabang ambigu.'
            );
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Enrollment cabang ambigu',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('monthly_reports', 0);
    }


}
