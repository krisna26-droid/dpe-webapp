<?php

namespace Tests\Feature\Teacher;

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
        string $status = 'draft'
    ): MonthlyReport {
        return MonthlyReport::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'cycle_id' => (string) Str::uuid(),
            'student_id' => (string) Str::uuid(),
            'branch_id' => (string) Str::uuid(),
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

    public function test_teacher_owner_can_submit_report_through_http(): void
    {
        $user = $this->createUser('teacher');
        $teacher = $this->createTeacher($user);

        $report = $this->createReport($teacher);

        $this->attachActiveSkillToReport($report);

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('teacher.reports.submit', $report)
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Laporan berhasil diajukan.'
            )
            ->assertJsonPath(
                'data.status',
                'submitted'
            );

        $this->assertSame(
            'submitted',
            $report->fresh()->status
        );

        $this->assertDatabaseHas('report_status_events', [
            'report_id' => $report->id,
            'actor_user_id' => $user->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
        ]);
    }

    public function test_non_owner_teacher_cannot_submit_report_through_http(): void
    {
        $ownerUser = $this->createUser('teacher');
        $ownerTeacher = $this->createTeacher($ownerUser);

        $otherUser = $this->createUser('teacher');
        $this->createTeacher($otherUser);

        $report = $this->createReport($ownerTeacher);

        $this->attachActiveSkillToReport($report);

        $response = $this
            ->actingAs($otherUser)
            ->postJson(
                route('teacher.reports.submit', $report)
            );

        $response->assertForbidden();

        $this->assertSame(
            'draft',
            $report->fresh()->status
        );

        $this->assertDatabaseCount(
            'report_status_events',
            0
        );
    }

    public function test_unauthenticated_user_cannot_submit_report_through_http(): void
    {
        $user = $this->createUser('teacher');
        $teacher = $this->createTeacher($user);

        $report = $this->createReport($teacher);

        $this->attachActiveSkillToReport($report);

        $response = $this->postJson(
            route('teacher.reports.submit', $report)
        );

        $response->assertUnauthorized();

        $this->assertSame(
            'draft',
            $report->fresh()->status
        );
    }

    public function test_teacher_cannot_submit_incomplete_report_through_http(): void
    {
        $user = $this->createUser('teacher');
        $teacher = $this->createTeacher($user);

        $report = $this->createReport($teacher);

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('teacher.reports.submit', $report)
            );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'skills',
        ]);

        $this->assertSame(
            'draft',
            $report->fresh()->status
        );

        $this->assertDatabaseCount(
            'report_status_events',
            0
        );
    }
}