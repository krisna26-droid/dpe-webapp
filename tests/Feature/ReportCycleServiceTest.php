<?php

namespace Tests\Feature;

use App\Models\ReportCycle;
use App\Services\ReportCycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportCycleServiceTest extends TestCase
{
    private ReportCycleService $service;

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

        // Cukup untuk membuktikan update siklus tetap diizinkan
        // ketika laporan bulanan sudah ada.
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('cycle_id', 36);
            $table->string('student_id', 36);
            $table->date('report_month');

            $table->unique(
                ['student_id', 'report_month'],
                'uq_monthly_reports_student_month'
            );

            $table->unique(
                ['cycle_id', 'report_month'],
                'uq_monthly_reports_cycle_month'
            );
        });

        DB::table('students')->insert([
            ['id' => 'student-a'],
            ['id' => 'student-b'],
        ]);

        $this->service = app(ReportCycleService::class);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    private function createCycle(
        string $studentId,
        int $number,
        string $start,
        string $end
    ): ReportCycle {
        return $this->service->create([
            'student_id' => $studentId,
            'cycle_number' => $number,
            'start_month' => $start,
            'end_month' => $end,
        ]);
    }

    public function test_it_creates_a_cycle_and_normalizes_months(): void
    {
        $cycle = $this->createCycle(
            'student-a',
            1,
            '2026-01-20',
            '2026-03-25'
        );

        $this->assertSame('2026-01-01', substr((string) $cycle->start_month, 0, 10));
        $this->assertSame('2026-03-01', substr((string) $cycle->end_month, 0, 10));
    }

    public function test_it_allows_adjacent_cycles(): void
    {
        $this->createCycle(
            'student-a',
            1,
            '2026-01-01',
            '2026-03-01'
        );

        $next = $this->createCycle(
            'student-a',
            2,
            '2026-04-01',
            '2026-06-01'
        );

        $this->assertSame(2, $next->cycle_number);
    }

    public function test_it_rejects_overlapping_cycles_for_the_same_student(): void
    {
        $this->createCycle(
            'student-a',
            1,
            '2026-01-01',
            '2026-03-01'
        );

        $this->expectException(ValidationException::class);

        $this->createCycle(
            'student-a',
            2,
            '2026-03-01',
            '2026-05-01'
        );
    }

    public function test_it_allows_the_same_month_for_different_students(): void
    {
        $this->createCycle(
            'student-a',
            1,
            '2026-01-01',
            '2026-03-01'
        );

        $other = $this->createCycle(
            'student-b',
            1,
            '2026-02-01',
            '2026-04-01'
        );

        $this->assertSame('student-b', $other->student_id);
    }

    public function test_it_rejects_a_non_positive_cycle_number(): void
    {
        $this->expectException(ValidationException::class);

        $this->createCycle(
            'student-a',
            0,
            '2026-01-01',
            '2026-03-01'
        );
    }

    public function test_it_rejects_an_end_month_before_start_month(): void
    {
        $this->expectException(ValidationException::class);

        $this->createCycle(
            'student-a',
            1,
            '2026-04-01',
            '2026-02-01'
        );
    }

    public function test_it_allows_updating_a_cycle_with_existing_reports(): void
    {
        $cycle = $this->createCycle(
            'student-a',
            1,
            '2026-01-01',
            '2026-03-01'
        );

        DB::table('monthly_reports')->insert([
            'id' => 'report-a',
            'cycle_id' => $cycle->id,
            'student_id' => 'student-a',
            'report_month' => '2026-02-01',
        ]);

        $updated = $this->service->update($cycle, [
            'cycle_number' => 1,
            'start_month' => '2026-02-01',
            'end_month' => '2026-04-01',
        ]);

        $this->assertSame('2026-02-01', substr((string) $updated->start_month, 0, 10));
        $this->assertSame('2026-04-01', substr((string) $updated->end_month, 0, 10));

        $this->assertDatabaseHas('monthly_reports', [
            'id' => 'report-a',
            'cycle_id' => $cycle->id,
            'report_month' => '2026-02-01',
        ]);
    }

    public function test_it_rejects_updating_into_another_cycles_range(): void
    {
        $cycleA = $this->createCycle(
            'student-a',
            1,
            '2026-01-01',
            '2026-03-01'
        );

        $this->createCycle(
            'student-a',
            2,
            '2026-05-01',
            '2026-06-01'
        );

        $this->expectException(ValidationException::class);

        $this->service->update($cycleA, [
            'cycle_number' => 1,
            'start_month' => '2026-02-01',
            'end_month' => '2026-05-01',
        ]);
    }
}
