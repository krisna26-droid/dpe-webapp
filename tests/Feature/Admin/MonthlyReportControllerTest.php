<?php

namespace Tests\Feature\Admin;

use App\Models\BranchAdminAssignment;
use App\Models\LearningSkill;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportSkill;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyReportControllerTest extends TestCase
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

        $this->createTestSchema();
    }

    protected function tearDown(): void
    {
        try {
            foreach ([
                'monthly_report_skills',
                'learning_skills',
                'report_status_events',
                'monthly_reports',
                'branch_admin_assignments',
                'teachers',
                'users',
            ] as $table) {
                Schema::connection('sqlite')->dropIfExists($table);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function createTestSchema(): void
    {
        Schema::connection('sqlite')->create(
            'users',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('username')->nullable();
                $table->string('email')->nullable();
                $table->string('password_hash')->nullable();
                $table->string('full_name');
                $table->string('role_code');
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at')->nullable();
            }
        );

        Schema::connection('sqlite')->create(
            'teachers',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('user_id', 36);
                $table->string('whatsapp_number')->nullable();
                $table->string('photo_file_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at')->nullable();
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
            }
        );

        Schema::connection('sqlite')->create(
            'monthly_report_skills',
            function (Blueprint $table) {
                $table->char('report_id', 36);
                $table->char('skill_id', 36);
                $table->string('trend')->nullable();
                $table->text('description');
            }
        );

        Schema::connection('sqlite')->create(
            'report_status_events',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('report_id', 36);
                $table->char('actor_user_id', 36);
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->string('comment', 255)->nullable();
                $table->dateTime('occurred_at');
            }
        );

        Schema::connection('sqlite')->create(
            'learning_skills',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('code')->unique();
                $table->string('name');
                $table->unsignedSmallInteger('display_order')->default(0);
                $table->boolean('is_active')->default(true);
            }
        );
    }

    private function createUser(string $role): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $role . '_' . Str::random(8),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => 'test-password-hash',
            'full_name' => ucfirst($role) . ' Test',
            'role_code' => $role,
            'is_active' => true,
            'created_at' => now(),
        ]);
    }

    private function createTeacher(User $user): Teacher
    {
        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'is_active' => true,
            'created_at' => now(),
        ]);
    }

    private function createReport(
        Teacher $teacher,
        string $status = 'submitted',
        ?string $branchId = null
    ): MonthlyReport {
        return MonthlyReport::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'cycle_id' => (string) Str::uuid(),
            'student_id' => (string) Str::uuid(),
            'branch_id' => $branchId ?? (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'report_month' => '2026-09-01',
            'due_on' => '2026-09-30',
            'video_target' => 4,
            'status' => $status,
            'development_summary' => 'Perkembangan siswa',
            'parent_challenges_summary' => 'Tantangan belajar',
            'parent_message' => 'Pesan untuk orang tua',
            'internal_teacher_note' => 'Catatan internal guru',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignAdmin(
        User $admin,
        string $branchId
    ): void {
        BranchAdminAssignment::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'admin_user_id' => $admin->id,
            'branch_id' => $branchId,
            'starts_on' => now()->toDateString(),
            'ends_on' => null,
        ]);
    }

    private function attachActiveSkillToReport(
        MonthlyReport $report
    ): void {
        $skill = LearningSkill::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'code' => 'TEST-' . Str::upper(Str::random(8)),
            'name' => 'Test Skill',
            'display_order' => 1,
            'is_active' => true,
        ]);

        MonthlyReportSkill::query()->forceCreate([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'trend' => 'improving',
            'description' => 'Siswa menunjukkan perkembangan yang baik.',
        ]);
    }

    public function test_branch_admin_can_approve_report_through_http(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $admin = $this->createUser('admin');

        $branchId = (string) Str::uuid();

        $this->assignAdmin($admin, $branchId);

        $report = $this->createReport(
            $teacher,
            'submitted',
            $branchId
        );

        $response = $this
            ->actingAs($admin)
            ->postJson(
                route('admin.reports.approve', $report)
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Laporan berhasil disetujui.'
            )
            ->assertJsonPath(
                'data.status',
                'approved'
            );

        $this->assertSame(
            'approved',
            $report->fresh()->status
        );

        $this->assertDatabaseHas('report_status_events', [
            'report_id' => $report->id,
            'actor_user_id' => $admin->id,
            'from_status' => 'submitted',
            'to_status' => 'approved',
        ]);
    }

    public function test_admin_from_another_branch_cannot_approve_report_through_http(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $admin = $this->createUser('admin');

        $reportBranchId = (string) Str::uuid();
        $otherBranchId = (string) Str::uuid();

        $this->assignAdmin($admin, $otherBranchId);

        $report = $this->createReport(
            $teacher,
            'submitted',
            $reportBranchId
        );

        $response = $this
            ->actingAs($admin)
            ->postJson(
                route('admin.reports.approve', $report)
            );

        $response->assertForbidden();

        $this->assertSame(
            'submitted',
            $report->fresh()->status
        );

        $this->assertDatabaseCount(
            'report_status_events',
            0
        );
    }

    public function test_superadmin_can_approve_report_through_http(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $superadmin = $this->createUser('superadmin');

        $report = $this->createReport(
            $teacher,
            'submitted'
        );

        $response = $this
            ->actingAs($superadmin)
            ->postJson(
                route('admin.reports.approve', $report)
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Laporan berhasil disetujui.'
            )
            ->assertJsonPath(
                'data.status',
                'approved'
            );

        $this->assertSame(
            'approved',
            $report->fresh()->status
        );

        $this->assertSame(
            $superadmin->id,
            $report->fresh()->approved_by_user_id
        );
    }

    public function test_admin_can_request_revision_through_http(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $admin = $this->createUser('admin');

        $branchId = (string) Str::uuid();

        $this->assignAdmin($admin, $branchId);

        $report = $this->createReport(
            $teacher,
            'submitted',
            $branchId
        );

        $comment = 'Mohon lengkapi perkembangan siswa.';

        $response = $this
            ->actingAs($admin)
            ->postJson(
                route('admin.reports.revision', $report),
                [
                    'comment' => $comment,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Laporan berhasil diminta untuk direvisi.'
            )
            ->assertJsonPath(
                'data.status',
                'revision'
            );

        $this->assertSame(
            'revision',
            $report->fresh()->status
        );

        $this->assertDatabaseHas('report_status_events', [
            'report_id' => $report->id,
            'actor_user_id' => $admin->id,
            'from_status' => 'submitted',
            'to_status' => 'revision',
            'comment' => $comment,
        ]);
    }

    public function test_revision_requires_comment_through_http(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $admin = $this->createUser('admin');

        $branchId = (string) Str::uuid();

        $this->assignAdmin($admin, $branchId);

        $report = $this->createReport(
            $teacher,
            'submitted',
            $branchId
        );

        $response = $this
            ->actingAs($admin)
            ->postJson(
                route('admin.reports.revision', $report),
                []
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'comment',
            ]);

        $this->assertSame(
            'submitted',
            $report->fresh()->status
        );

        $this->assertDatabaseCount(
            'report_status_events',
            0
        );
    }

    public function test_unauthenticated_user_cannot_approve_report_through_http(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $report = $this->createReport(
            $teacher,
            'submitted'
        );

        $response = $this->postJson(
            route('admin.reports.approve', $report)
        );

        $response->assertUnauthorized();

        $this->assertSame(
            'submitted',
            $report->fresh()->status
        );
    }

    public function test_teacher_cannot_access_admin_report_workflow(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $report = $this->createReport(
            $teacher,
            'submitted'
        );

        $response = $this
            ->actingAs($teacherUser)
            ->postJson(
                route('admin.reports.approve', $report)
            );

        $response->assertForbidden();

        $this->assertSame(
            'submitted',
            $report->fresh()->status
        );
    }
}