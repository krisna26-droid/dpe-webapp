<?php

namespace Tests\Feature\Admin;

use App\Models\ReportCycle;
use App\Models\User;
use App\Services\ReportCycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportCycleManagementTest extends TestCase
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

        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('full_name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role_code');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->string('id', 36)->primary();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('name');
        });

        Schema::create('branch_admin_assignments', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('branch_id', 36);
            $table->string('admin_user_id', 36);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('student_id', 36);
            $table->string('branch_id', 36);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
        });

        Schema::create('report_cycles', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('student_id', 36);
            $table->unsignedInteger('cycle_number');
            $table->date('start_month');
            $table->date('end_month');
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

        DB::table('students')->insert([
            ['id' => 'student-a'],
            ['id' => 'student-b'],
        ]);

        DB::table('branches')->insert([
            'id' => 'branch-default',
            'name' => 'Branch Default',
        ]);

        DB::table('student_enrollments')->insert([
            'id' => 'enrollment-student-a',
            'student_id' => 'student-a',
            'branch_id' => 'branch-default',
            'starts_on' => '2026-01-01',
            'ends_on' => null,
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    private function createUser(string $role): User
    {
        $user = User::forceCreate([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'full_name' => ucfirst($role) . ' Test',
            'username' => $role . '-' . uniqid(),
            'email' => $role . '-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'role_code' => $role,
            'is_active' => true,
        ]);

        if ($role === 'admin') {
            DB::table('branch_admin_assignments')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'branch_id' => 'branch-default',
                'admin_user_id' => $user->id,
                'starts_on' => '2026-01-01',
                'ends_on' => null,
            ]);
        }

        return $user;
    }

    private function validCycleData(array $overrides = []): array
    {
        return array_merge([
            'student_id' => 'student-a',
            'cycle_number' => 1,
            'start_month' => '2026-01-01',
            'end_month' => '2026-03-01',
            'share_due_on' => null,
        ], $overrides);
    }

    public function test_admin_can_list_report_cycles(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->getJson(route('admin.report-cycles.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_superadmin_can_list_report_cycles(): void
    {
        $superadmin = $this->createUser('superadmin');

        $this->actingAs($superadmin)
            ->getJson(route('admin.report-cycles.index'))
            ->assertOk();
    }

    public function test_teacher_cannot_access_report_cycles(): void
    {
        $teacher = $this->createUser('teacher');

        $this->actingAs($teacher)
            ->getJson(route('admin.report-cycles.index'))
            ->assertForbidden();

        $this->postJson(
            route('admin.report-cycles.store'),
            $this->validCycleData()
        )->assertForbidden();
    }

    public function test_admin_can_create_report_cycle(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->postJson(
                route('admin.report-cycles.store'),
                $this->validCycleData()
            )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Siklus laporan berhasil dibuat.'
            )
            ->assertJsonPath('data.student_id', 'student-a');

        $this->assertDatabaseHas('report_cycles', [
            'student_id' => 'student-a',
            'cycle_number' => 1,
            'start_month' => '2026-01-01 00:00:00',
            'end_month' => '2026-03-01 00:00:00',
        ]);
    }

    public function test_superadmin_can_update_report_cycle(): void
    {
        $superadmin = $this->createUser('superadmin');

        $cycle = app(ReportCycleService::class)->create(
            $this->validCycleData()
        );

        $this->actingAs($superadmin)
            ->putJson(
                route('admin.report-cycles.update', $cycle),
                [
                    'cycle_number' => 1,
                    'start_month' => '2026-02-01',
                    'end_month' => '2026-04-01',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Siklus laporan berhasil diperbarui.'
            );

        $this->assertDatabaseHas('report_cycles', [
            'id' => $cycle->id,
            'start_month' => '2026-02-01 00:00:00',
            'end_month' => '2026-04-01 00:00:00',
        ]);
    }

    public function test_create_rejects_overlapping_cycle(): void
    {
        $admin = $this->createUser('admin');

        app(ReportCycleService::class)->create(
            $this->validCycleData()
        );

        $this->actingAs($admin)
            ->postJson(
                route('admin.report-cycles.store'),
                $this->validCycleData([
                    'cycle_number' => 2,
                    'start_month' => '2026-03-01',
                    'end_month' => '2026-05-01',
                ])
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_month');
    }

    public function test_create_rejects_unknown_student(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->postJson(
                route('admin.report-cycles.store'),
                $this->validCycleData([
                    'student_id' => 'student-unknown',
                ])
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }

    public function test_unauthenticated_user_cannot_access_report_cycles(): void
    {
        $this->getJson(route('admin.report-cycles.index'))
            ->assertUnauthorized();
    }
    public function test_teacher_cannot_update_report_cycle(): void
    {
        $teacher = $this->createUser('teacher');

        $cycle = app(ReportCycleService::class)->create(
            $this->validCycleData()
        );

        $this->actingAs($teacher)
            ->putJson(
                route('admin.report-cycles.update', $cycle),
                [
                    'cycle_number' => 1,
                    'start_month' => '2026-02-01',
                    'end_month' => '2026-04-01',
                ]
            )
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_or_update_report_cycle(): void
    {
        $cycle = app(ReportCycleService::class)->create(
            $this->validCycleData()
        );

        $this->postJson(
            route('admin.report-cycles.store'),
            $this->validCycleData([
                'student_id' => 'student-b',
            ])
        )->assertUnauthorized();

        $this->putJson(
            route('admin.report-cycles.update', $cycle),
            [
                'cycle_number' => 1,
                'start_month' => '2026-02-01',
                'end_month' => '2026-04-01',
            ]
        )->assertUnauthorized();
    }

    public function test_admin_cannot_create_report_cycle_with_missing_required_fields(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->postJson(
                route('admin.report-cycles.store'),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'student_id',
                'cycle_number',
                'start_month',
                'end_month',
            ]);
    }
    public function test_it_normalizes_dates_to_the_first_day_of_each_month(): void
    {
        $cycle = app(ReportCycleService::class)->create([
            'student_id' => 'student-a',
            'cycle_number' => 1,
            'start_month' => '2026-01-15',
            'end_month' => '2026-03-20',
            'share_due_on' => '2026-04-15',
        ]);

        $this->assertSame(
            '2026-01-01',
            substr((string) $cycle->start_month, 0, 10)
        );

        $this->assertSame(
            '2026-03-01',
            substr((string) $cycle->end_month, 0, 10)
        );

        $this->assertSame(
            '2026-04-15',
            substr((string) $cycle->share_due_on, 0, 10)
        );
    }
    public function test_admin_cannot_create_report_cycle_for_student_from_another_branch(): void
    {
        $admin = $this->createUser('admin');

        DB::table('branches')->insert([
            'id' => 'branch-other',
            'name' => 'Branch Other',
        ]);

        DB::table('student_enrollments')->insert([
            'id' => 'enrollment-student',
            'student_id' => 'student-b',
            'branch_id' => 'branch-other',
            'starts_on' => '2026-01-01',
            'ends_on' => null,
        ]);

        $this->actingAs($admin)
            ->postJson(
                route('admin.report-cycles.store'),
                $this->validCycleData([
                    'student_id' => 'student-b',
                ])
            )
            ->assertForbidden();
    }

    public function test_admin_can_create_report_cycle_for_student_from_assigned_branch(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->postJson(
                route('admin.report-cycles.store'),
                $this->validCycleData()
            )
            ->assertCreated()
            ->assertJsonPath('data.student_id', 'student-a');

        $this->assertDatabaseHas('report_cycles', [
            'student_id' => 'student-a',
            'cycle_number' => 1,
        ]);
    }

    public function test_superadmin_can_create_report_cycle_for_student_from_any_branch(): void
    {
        $superadmin = $this->createUser('superadmin');

        DB::table('branches')->insert([
            'id' => 'branch-other',
            'name' => 'Branch Other',
        ]);

        DB::table('student_enrollments')->insert([
            'id' => 'enrollment-student-b',
            'student_id' => 'student-b',
            'branch_id' => 'branch-other',
            'starts_on' => '2026-01-01',
            'ends_on' => null,
        ]);

        $this->actingAs($superadmin)
            ->postJson(
                route('admin.report-cycles.store'),
                $this->validCycleData([
                    'student_id' => 'student-b',
                ])
            )
            ->assertCreated()
            ->assertJsonPath('data.student_id', 'student-b');

        $this->assertDatabaseHas('report_cycles', [
            'student_id' => 'student-b',
            'cycle_number' => 1,
        ]);
    }

    public function test_admin_only_sees_report_cycles_for_students_from_assigned_branch(): void
    {
        $admin = $this->createUser('admin');

        DB::table('branches')->insert([
            'id' => 'branch-other',
            'name' => 'Branch Other',
        ]);

        DB::table('student_enrollments')->insert([
            'id' => 'enrollment-student-b',
            'student_id' => 'student-b',
            'branch_id' => 'branch-other',
            'starts_on' => '2026-01-01',
            'ends_on' => null,
        ]);

        // Buat cycle untuk kedua student.
        app(ReportCycleService::class)->create(
            $this->validCycleData([
                'student_id' => 'student-a',
            ])
        );
        app(ReportCycleService::class)->create(
            $this->validCycleData([
                'student_id' => 'student-b',
            ])
        );

        $response = $this->actingAs($admin)
            ->getJson(route('admin.report-cycles.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', 'student-a');
    }

    public function test_superadmin_sees_report_cycles_from_all_branches(): void
    {
        $superadmin = $this->createUser('superadmin');

        DB::table('branches')->insert([
            'id' => 'branch-other',
            'name' => 'Branch Other',
        ]);

        DB::table('student_enrollments')->insert([
            'id' => 'enrollment-student-b',
            'student_id' => 'student-b',
            'branch_id' => 'branch-other',
            'starts_on' => '2026-01-01',
            'ends_on' => null,
        ]);

        app(ReportCycleService::class)->create(
            $this->validCycleData(['student_id' => 'student-a'])
        );
        app(ReportCycleService::class)->create(
            $this->validCycleData(['student_id' => 'student-b'])
        );

        $response = $this->actingAs($superadmin)
            ->getJson(route('admin.report-cycles.index'));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.student_id', 'student-a')
            ->assertJsonPath('data.1.student_id', 'student-b');
    }

    public function test_admin_cannot_update_report_cycle_for_student_from_another_branch(): void
    {
        $admin = $this->createUser('admin');

        DB::table('branches')->insert([
            'id' => 'branch-other',
            'name' => 'Branch Other',
        ]);

        DB::table('student_enrollments')->insert([
            'id' => 'enrollment-student-b',
            'student_id' => 'student-b',
            'branch_id' => 'branch-other',
            'starts_on' => '2026-01-01',
            'ends_on' => null,
        ]);

        $reportCycle = app(ReportCycleService::class)->create(
            $this->validCycleData(['student_id' => 'student-b'])
        );

        $this->actingAs($admin)
            ->putJson(
                route('admin.report-cycles.update', $reportCycle->id),
                [
                    'cycle_number' => 2,
                    'start_month' => '2026-02-01',
                    'end_month' => '2026-04-01',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('report_cycles', [
            'id' => $reportCycle->id,
            'cycle_number' => 1,
        ]);
    }

    public function test_admin_can_update_report_cycle_for_student_from_assigned_branch(): void
    {
        $admin = $this->createUser('admin');

        $reportCycle = app(ReportCycleService::class)->create(
            $this->validCycleData()
        );

        $this->actingAs($admin)
            ->putJson(
                route('admin.report-cycles.update', $reportCycle->id),
                [
                    'cycle_number' => 2,
                    'start_month' => '2026-02-01',
                    'end_month' => '2026-04-01',
                ]
            )
            ->assertOk()
            ->assertJsonPath('data.cycle_number', 2);

        $this->assertDatabaseHas('report_cycles', [
            'id' => $reportCycle->id,
            'cycle_number' => 2,
        ]);
    }

    public function test_superadmin_can_update_report_cycle_for_student_from_any_branch(): void
    {
        $superadmin = $this->createUser('superadmin');

        DB::table('branches')->insert([
            'id' => 'branch-other',
            'name' => 'Branch Other',
        ]);

        DB::table('student_enrollments')->insert([
            'id' => 'enrollment-student-b',
            'student_id' => 'student-b',
            'branch_id' => 'branch-other',
            'starts_on' => '2026-01-01',
            'ends_on' => null,
        ]);

        $reportCycle = app(ReportCycleService::class)->create(
            $this->validCycleData(['student_id' => 'student-b'])
        );

        $this->actingAs($superadmin)
            ->putJson(
                route('admin.report-cycles.update', $reportCycle->id),
                [
                    'cycle_number' => 2,
                    'start_month' => '2026-02-01',
                    'end_month' => '2026-04-01',
                ]
            )
            ->assertOk()
            ->assertJsonPath('data.student_id', 'student-b')
            ->assertJsonPath('data.cycle_number', 2);

        $this->assertDatabaseHas('report_cycles', [
            'id' => $reportCycle->id,
            'cycle_number' => 2,
        ]);
    }

    public function test_admin_cannot_update_report_cycle_for_student_with_future_enrollment(): void
    {
        $admin = $this->createUser('admin');

        DB::table('branches')->insert([
            'id' => 'branch-future',
            'name' => 'Branch Future',
        ]);

        DB::table('student_enrollments')->insert([
            'id' => 'enrollment-student-b-future',
            'student_id' => 'student-b',
            'branch_id' => 'branch-future',
            'starts_on' => '2027-01-01',
            'ends_on' => null,
        ]);

        $reportCycle = app(ReportCycleService::class)->create(
            $this->validCycleData(['student_id' => 'student-b'])
        );

        $this->actingAs($admin)
            ->putJson(
                route('admin.report-cycles.update', $reportCycle->id),
                [
                    'cycle_number' => 2,
                    'start_month' => '2026-02-01',
                    'end_month' => '2026-04-01',
                ]
            )
            ->assertForbidden();
    }

    public function test_admin_cannot_update_report_cycle_for_student_with_expired_enrollment(): void
    {
        $admin = $this->createUser('admin');

        DB::table('branches')->insert([
            'id' => 'branch-expired',
            'name' => 'Branch Expired',
        ]);

        DB::table('student_enrollments')->insert([
            'id' => 'enrollment-student-b-expired',
            'student_id' => 'student-b',
            'branch_id' => 'branch-expired',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-01-02',
        ]);

        $reportCycle = app(ReportCycleService::class)->create(
            $this->validCycleData(['student_id' => 'student-b'])
        );

        $this->actingAs($admin)
            ->putJson(
                route('admin.report-cycles.update', $reportCycle->id),
                [
                    'cycle_number' => 2,
                    'start_month' => '2026-02-01',
                    'end_month' => '2026-04-01',
                ]
            )
            ->assertForbidden();
    }

    public function test_admin_can_update_report_cycle_when_enrollment_starts_today(): void
    {
        $admin = $this->createUser('admin');

        DB::table('student_enrollments')
            ->where('student_id', 'student-a')
            ->update(['starts_on' => now()->toDateString()]);

        $reportCycle = app(ReportCycleService::class)->create(
            $this->validCycleData()
        );

        $this->actingAs($admin)
            ->putJson(
                route('admin.report-cycles.update', $reportCycle->id),
                [
                    'cycle_number' => 2,
                    'start_month' => '2026-02-01',
                    'end_month' => '2026-04-01',
                ]
            )
            ->assertOk()
            ->assertJsonPath('data.cycle_number', 2);

        $this->assertDatabaseHas('report_cycles', [
            'id' => $reportCycle->id,
            'cycle_number' => 2,
        ]);
    }

    public function test_admin_can_update_report_cycle_when_enrollment_ends_today(): void
    {
        $admin = $this->createUser('admin');

        DB::table('student_enrollments')
            ->where('student_id', 'student-a')
            ->update(['ends_on' => now()->toDateString()]);

        $reportCycle = app(ReportCycleService::class)->create(
            $this->validCycleData()
        );

        $this->actingAs($admin)
            ->putJson(
                route('admin.report-cycles.update', $reportCycle->id),
                [
                    'cycle_number' => 2,
                    'start_month' => '2026-02-01',
                    'end_month' => '2026-04-01',
                ]
            )
            ->assertOk()
            ->assertJsonPath('data.cycle_number', 2);

        $this->assertDatabaseHas('report_cycles', [
            'id' => $reportCycle->id,
            'cycle_number' => 2,
        ]);
    }
}
