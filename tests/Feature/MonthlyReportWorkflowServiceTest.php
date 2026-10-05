<?php

namespace Tests\Feature;

use App\Models\BranchAdminAssignment;
use App\Models\MonthlyReport;
use App\Models\ReportStatusEvent;
use App\Models\Teacher;
use App\Models\User;
use App\Services\MonthlyReportWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use App\Models\LearningSkill;
use App\Models\MonthlyReportSkill;

class MonthlyReportWorkflowServiceTest extends TestCase
{
    private MonthlyReportWorkflowService $service;

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

        $this->service = app(MonthlyReportWorkflowService::class);
    }

    protected function tearDown(): void
    {
        try {
            foreach (
                [
                    'monthly_report_skills',
                    'learning_skills',
                    'report_status_events',
                    'monthly_reports',
                    'branch_admin_assignments',
                    'teachers',
                    'users',
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
        string $status = 'draft',
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
    /**
     * Menambahkan skill aktif dengan deskripsi valid ke laporan.
     */
    private function attachActiveSkillToReport(
        MonthlyReport $report,
        string $description = 'Siswa menunjukkan perkembangan yang baik.'
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
            'description' => $description,
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

    public function test_teacher_owner_can_submit_draft_report(): void
    {
        $user = $this->createUser('teacher');
        $teacher = $this->createTeacher($user);
        $report = $this->createReport($teacher);

        // Laporan harus memiliki minimal satu skill aktif.
        $this->attachActiveSkillToReport($report);

        $result = $this->service->submit($report, $user);

        $this->assertSame('submitted', $result->status);
        $this->assertNotNull($result->submitted_at);

        $this->assertDatabaseHas('report_status_events', [
            'report_id' => $report->id,
            'actor_user_id' => $user->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
        ]);
    }

    public function test_teacher_owner_can_resubmit_revision_report(): void
    {
        $user = $this->createUser('teacher');
        $teacher = $this->createTeacher($user);
        $report = $this->createReport($teacher, 'revision');

        // Laporan revisi juga harus memenuhi validasi skill.
        $this->attachActiveSkillToReport($report);

        $result = $this->service->submit($report, $user);

        $this->assertSame('submitted', $result->status);

        $this->assertDatabaseHas('report_status_events', [
            'report_id' => $report->id,
            'from_status' => 'revision',
            'to_status' => 'submitted',
        ]);
    }

    public function test_non_owner_teacher_cannot_submit_report(): void
    {
        $ownerUser = $this->createUser('teacher');
        $ownerTeacher = $this->createTeacher($ownerUser);

        $otherUser = $this->createUser('teacher');
        $this->createTeacher($otherUser);

        $report = $this->createReport($ownerTeacher);

        try {
            $this->service->submit($report, $otherUser);
            $this->fail('Guru yang bukan pemilik seharusnya ditolak.');
        } catch (AuthorizationException) {
            $this->assertSame('draft', $report->fresh()->status);
        }
    }

    public function test_branch_admin_can_approve_report(): void
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

        $result = $this->service->approve($report, $admin);

        $this->assertSame('approved', $result->status);
        $this->assertSame($admin->id, $result->approved_by_user_id);
        $this->assertNotNull($result->approved_at);

        $this->assertIsArray($result->approved_snapshot);
        $this->assertSame(
            $report->id,
            $result->approved_snapshot['report_id']
        );

        $this->assertDatabaseHas('report_status_events', [
            'report_id' => $report->id,
            'actor_user_id' => $admin->id,
            'from_status' => 'submitted',
            'to_status' => 'approved',
        ]);
    }

    public function test_admin_from_another_branch_cannot_approve_report(): void
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

        try {
            $this->service->approve($report, $admin);
            $this->fail('Admin dari cabang lain seharusnya ditolak.');
        } catch (AuthorizationException) {
            $this->assertSame('submitted', $report->fresh()->status);
        }
    }

    public function test_superadmin_can_approve_report(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);
        $superadmin = $this->createUser('superadmin');

        $report = $this->createReport($teacher, 'submitted');

        $result = $this->service->approve($report, $superadmin);

        $this->assertSame('approved', $result->status);
        $this->assertSame(
            $superadmin->id,
            $result->approved_by_user_id
        );
    }

    public function test_revision_requires_a_non_empty_comment(): void
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

        try {
            $this->service->requestRevision($report, $admin, '   ');
            $this->fail('Komentar kosong seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('comment', $exception->errors());
            $this->assertSame('submitted', $report->fresh()->status);
        }
    }

    public function test_admin_can_request_revision_with_comment(): void
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

        $result = $this->service->requestRevision(
            $report,
            $admin,
            'Mohon lengkapi perkembangan siswa.'
        );

        $this->assertSame('revision', $result->status);

        $this->assertDatabaseHas('report_status_events', [
            'report_id' => $report->id,
            'actor_user_id' => $admin->id,
            'from_status' => 'submitted',
            'to_status' => 'revision',
            'comment' => 'Mohon lengkapi perkembangan siswa.',
        ]);
    }

    public function test_submit_rolls_back_when_status_event_insert_fails(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);
        $report = $this->createReport($teacher, 'draft');

        // Pastikan validasi kelengkapan laporan berhasil.
        $this->attachActiveSkillToReport($report);

        // Simulasikan kegagalan saat riwayat status dicatat.
        DB::connection('sqlite')->statement(
            <<<'SQL'
            CREATE TRIGGER fail_report_status_event_insert
            BEFORE INSERT ON report_status_events
            BEGIN
                SELECT RAISE(
                    ABORT,
                    'Simulated status event insert failure'
                );
            END;
            SQL
        );

        try {
            $this->service->submit($report, $teacherUser);

            $this->fail(
                'Pengajuan seharusnya gagal ketika pencatatan riwayat gagal.'
            );
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString(
                'Simulated status event insert failure',
                $exception->getMessage()
            );
        }

        // Transaksi harus membatalkan perubahan status.
        $this->assertSame('draft', $report->fresh()->status);

        // Tidak boleh ada riwayat status yang berhasil tersimpan.
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public function test_submit_fails_when_development_summary_is_empty(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $report = $this->createReport($teacher, 'draft');

        $report->development_summary = '   ';
        $report->save();

        try {
            $this->service->submit($report, $teacherUser);
            $this->fail('Laporan tanpa ringkasan perkembangan seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'development_summary',
                $exception->errors()
            );
        }

        $this->assertSame('draft', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public function test_submit_fails_when_parent_challenges_summary_is_empty(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $report = $this->createReport($teacher, 'draft');

        $report->parent_challenges_summary = null;
        $report->save();

        try {
            $this->service->submit($report, $teacherUser);
            $this->fail('Laporan tanpa ringkasan tantangan seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'parent_challenges_summary',
                $exception->errors()
            );
        }

        $this->assertSame('draft', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public function test_report_can_be_linked_to_learning_skills(): void
    {
        $user = $this->createUser('teacher');
        $teacher = $this->createTeacher($user);
        $report = $this->createReport($teacher);

        $skillId = (string) Str::uuid();

        LearningSkill::query()->forceCreate([
            'id' => $skillId,
            'code' => 'LISTENING',
            'name' => 'Listening',
            'display_order' => 1,
            'is_active' => true,
        ]);

        MonthlyReportSkill::query()->forceCreate([
            'report_id' => $report->id,
            'skill_id' => $skillId,
            'trend' => 'improving',
            'description' => 'Siswa mulai memahami instruksi sederhana.',
        ]);

        $report->refresh();

        $this->assertCount(1, $report->reportSkills);
        $this->assertCount(1, $report->skills);

        $this->assertSame(
            'LISTENING',
            $report->skills->first()->code
        );

        $this->assertSame(
            'improving',
            $report->skills->first()->pivot->trend
        );

        $this->assertSame(
            'Siswa mulai memahami instruksi sederhana.',
            $report->skills->first()->pivot->description
        );
    }

    public function test_approval_snapshot_contains_report_skills(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $admin = $this->createUser('superadmin');
        $report = $this->createReport($teacher, 'submitted');

        $skillId = (string) Str::uuid();

        LearningSkill::query()->forceCreate([
            'id' => $skillId,
            'code' => 'LISTENING',
            'name' => 'Listening',
            'display_order' => 1,
            'is_active' => true,
        ]);

        MonthlyReportSkill::query()->forceCreate([
            'report_id' => $report->id,
            'skill_id' => $skillId,
            'trend' => 'improving',
            'description' => 'Siswa mulai memahami instruksi sederhana.',
        ]);

        $result = $this->service->approve($report, $admin);

        $this->assertSame('approved', $result->status);

        $this->assertCount(1, $result->approved_snapshot['skills']);

        $this->assertSame(
            $skillId,
            $result->approved_snapshot['skills'][0]['skill_id']
        );

        $this->assertSame(
            'improving',
            $result->approved_snapshot['skills'][0]['trend']
        );

        $this->assertSame(
            'Siswa mulai memahami instruksi sederhana.',
            $result->approved_snapshot['skills'][0]['description']
        );
    }

    public function test_submit_fails_when_report_has_no_skills(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);
        $report = $this->createReport($teacher, 'draft');

        try {
            $this->service->submit($report, $teacherUser);
            $this->fail('Laporan tanpa skill seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('skills', $exception->errors());
        }

        $this->assertSame('draft', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public function test_submit_fails_when_report_has_inactive_skill(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);
        $report = $this->createReport($teacher, 'draft');

        $skillId = (string) Str::uuid();

        LearningSkill::query()->forceCreate([
            'id' => $skillId,
            'code' => 'INACTIVE_SKILL',
            'name' => 'Inactive Skill',
            'display_order' => 1,
            'is_active' => false,
        ]);

        MonthlyReportSkill::query()->forceCreate([
            'report_id' => $report->id,
            'skill_id' => $skillId,
            'trend' => 'improving',
            'description' => 'Deskripsi skill untuk pengujian.',
        ]);

        try {
            $this->service->submit($report, $teacherUser);
            $this->fail('Skill tidak aktif seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('skills', $exception->errors());
        }

        $this->assertSame('draft', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public function test_submit_fails_when_skill_description_is_blank(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);
        $report = $this->createReport($teacher, 'draft');

        $skillId = (string) Str::uuid();

        LearningSkill::query()->forceCreate([
            'id' => $skillId,
            'code' => 'LISTENING',
            'name' => 'Listening',
            'display_order' => 1,
            'is_active' => true,
        ]);

        MonthlyReportSkill::query()->forceCreate([
            'report_id' => $report->id,
            'skill_id' => $skillId,
            'trend' => 'improving',
            'description' => '   ',
        ]);

        try {
            $this->service->submit($report, $teacherUser);
            $this->fail('Deskripsi skill kosong seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('skills', $exception->errors());
        }

        $this->assertSame('draft', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public function test_revision_rejects_comment_longer_than_255_characters(): void
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

        try {
            $this->service->requestRevision(
                $report,
                $admin,
                str_repeat('A', 256)
            );

            $this->fail(
                'Komentar revisi lebih dari 255 karakter seharusnya ditolak.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'comment',
                $exception->errors()
            );
        }

        $this->assertSame(
            'submitted',
            $report->fresh()->status
        );

        $this->assertDatabaseCount('report_status_events', 0);
    }
    public function test_inactive_teacher_cannot_submit_report(): void
    {
        $user = $this->createUser('teacher');
        $teacher = $this->createTeacher($user);
        $report = $this->createReport($teacher);

        $this->attachActiveSkillToReport($report);

        $user->is_active = false;
        $user->save();

        try {
            $this->service->submit($report, $user);

            $this->fail(
                'Guru dengan akun tidak aktif seharusnya ditolak.'
            );
        } catch (AuthorizationException) {
            $this->assertSame(
                'draft',
                $report->fresh()->status
            );
        }

        $this->assertDatabaseCount('report_status_events', 0);
    }
    public function test_inactive_admin_cannot_approve_report(): void
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

        $admin->is_active = false;
        $admin->save();

        try {
            $this->service->approve($report, $admin);

            $this->fail(
                'Admin dengan akun tidak aktif seharusnya ditolak.'
            );
        } catch (AuthorizationException) {
            $this->assertSame(
                'submitted',
                $report->fresh()->status
            );
        }

        $this->assertDatabaseCount('report_status_events', 0);
    }
    public function test_approved_report_cannot_be_submitted_again(): void
    {
        $user = $this->createUser('teacher');
        $teacher = $this->createTeacher($user);

        $report = $this->createReport($teacher, 'approved');

        $this->attachActiveSkillToReport($report);

        try {
            $this->service->submit($report, $user);

            $this->fail(
                'Laporan yang sudah disetujui tidak boleh diajukan kembali.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'status',
                $exception->errors()
            );
        }

        $this->assertSame(
            'approved',
            $report->fresh()->status
        );

        $this->assertDatabaseCount('report_status_events', 0);
    }

    
public function test_branch_admin_cannot_approve_before_assignment_starts(): void
{
    $teacherUser = $this->createUser('teacher');
    $teacher = $this->createTeacher($teacherUser);

    $admin = $this->createUser('admin');
    $branchId = (string) Str::uuid();

    BranchAdminAssignment::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'admin_user_id' => $admin->id,
        'branch_id' => $branchId,
        'starts_on' => now()->addDay()->toDateString(),
        'ends_on' => null,
    ]);

    $report = $this->createReport(
        $teacher,
        'submitted',
        $branchId
    );

    try {
        $this->service->approve($report, $admin);
        $this->fail('Admin belum mulai bertugas seharusnya ditolak.');
    } catch (AuthorizationException) {
        $this->assertSame('submitted', $report->fresh()->status);
    }

    $this->assertDatabaseCount('report_status_events', 0);
}

public function test_branch_admin_cannot_approve_after_assignment_ends(): void
{
    $teacherUser = $this->createUser('teacher');
    $teacher = $this->createTeacher($teacherUser);

    $admin = $this->createUser('admin');
    $branchId = (string) Str::uuid();

    BranchAdminAssignment::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'admin_user_id' => $admin->id,
        'branch_id' => $branchId,
        'starts_on' => now()->subDays(2)->toDateString(),
        'ends_on' => now()->subDay()->toDateString(),
    ]);

    $report = $this->createReport(
        $teacher,
        'submitted',
        $branchId
    );

    try {
        $this->service->approve($report, $admin);
        $this->fail('Admin yang masa tugasnya berakhir seharusnya ditolak.');
    } catch (AuthorizationException) {
        $this->assertSame('submitted', $report->fresh()->status);
    }

    $this->assertDatabaseCount('report_status_events', 0);
}

    public function test_branch_admin_can_approve_on_assignment_start_date(): void
    {
        $teacherUser = $this->createUser('teacher');
        $teacher = $this->createTeacher($teacherUser);

        $admin = $this->createUser('admin');
        $branchId = (string) Str::uuid();

        BranchAdminAssignment::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'admin_user_id' => $admin->id,
            'branch_id' => $branchId,
            'starts_on' => now()->toDateString(),
            'ends_on' => null,
        ]);

        $report = $this->createReport(
            $teacher,
            'submitted',
            $branchId
        );

        $result = $this->service->approve($report, $admin);

        $this->assertSame('approved', $result->status);
        $this->assertSame($admin->id, $result->approved_by_user_id);
    }

}
