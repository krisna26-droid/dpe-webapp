<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Branch;
use App\Models\LearningSkill;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportSkill;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyReportSkillHttpTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeBranch(): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BRANCH-' . Str::upper(Str::random(8)),
            'name' => 'Test Branch',
            'timezone_name' => 'Asia/Jakarta',
            'payment_recap_day' => 15,
            'is_active' => true,
        ]);
    }

    private function makeStudent(): Student
    {
        $user = $this->makeUser();

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $user->id,
            'full_name' => 'Test Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 1',
            'began_on' => now()->subYear()->toDateString(),
            'status' => 'active',
        ]);
    }

    private function makeTeacher(): Teacher
    {
        $user = $this->makeUser();

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);
    }

    private function makeReportCycle(?Student $student = null): ReportCycle
    {
        $student ??= $this->makeStudent();

        $cycle = new ReportCycle();

        $cycle->forceFill([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'cycle_number' => 1,
            'start_month' => now()->startOfMonth()->subMonth()->toDateString(),
            'end_month' => now()->startOfMonth()->addMonth()->subDay()->toDateString(),
            'share_due_on' => now()->startOfMonth()->addDays(10)->toDateString(),
        ]);

        $cycle->save();

        return $cycle;
    }

    private function makeLearningSkill(): LearningSkill
    {
        return LearningSkill::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'SKILL-' . Str::upper(Str::random(8)),
            'name' => 'Test Learning Skill',
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    private function makeMonthlyReport(): MonthlyReport
    {
        $student = $this->makeStudent();
        $branch = $this->makeBranch();
        $teacher = $this->makeTeacher();
        $cycle = $this->makeReportCycle($student);

        $report = new MonthlyReport();

        $report->forceFill([
            'id' => (string) Str::uuid(),
            'cycle_id' => $cycle->id,
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'report_month' => now()->startOfMonth()->toDateString(),
            'due_on' => now()->startOfMonth()->addDays(10)->toDateString(),
            'video_target' => 2,
            'status' => 'draft',
            'development_summary' => null,
            'parent_challenges_summary' => null,
            'parent_message' => null,
            'internal_teacher_note' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'approved_by_user_id' => null,
            'approved_snapshot' => null,
        ]);

        $report->save();

        return $report;
    }

    private function makeReportSkill(
        ?MonthlyReport $report = null,
        ?LearningSkill $skill = null
    ): MonthlyReportSkill {
        $report ??= $this->makeMonthlyReport();
        $skill ??= $this->makeLearningSkill();

        return MonthlyReportSkill::query()->create([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'trend' => 'improving',
            'description' => 'Initial description.',
        ]);
    }

    public function test_guest_cannot_access_monthly_report_skills(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $response = $this->get(
            route(
                'superadmin.monthly-report-skills.show',
                [
                    'reportId' => $report->id,
                    'skillId' => $skill->id,
                ]
            )
        );

        $response->assertRedirect();
    }

    public function test_non_superadmin_cannot_access_monthly_report_skills(): void
    {
        $user = $this->makeUser('teacher');

        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'superadmin.monthly-report-skills.show',
                    [
                        'reportId' => $report->id,
                        'skillId' => $skill->id,
                    ]
                )
            );

        $response->assertForbidden();
    }

    public function test_superadmin_can_store_monthly_report_skill(): void
    {
        $user = $this->makeUser();

        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $response = $this
            ->actingAs($user)
            ->post(
                route('superadmin.monthly-report-skills.store'),
                [
                    'report_id' => $report->id,
                    'skill_id' => $skill->id,
                    'trend' => 'improving',
                    'description' => 'Student menunjukkan perkembangan.',
                ]
            );

        $response->assertCreated();

        $this->assertDatabaseHas(
            'monthly_report_skills',
            [
                'report_id' => $report->id,
                'skill_id' => $skill->id,
                'trend' => 'improving',
                'description' => 'Student menunjukkan perkembangan.',
            ]
        );
    }

    public function test_superadmin_can_show_monthly_report_skill(): void
    {
        $user = $this->makeUser();

        $reportSkill = $this->makeReportSkill();

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'superadmin.monthly-report-skills.show',
                    [
                        'reportId' => $reportSkill->report_id,
                        'skillId' => $reportSkill->skill_id,
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertJson([
                'report_id' => $reportSkill->report_id,
                'skill_id' => $reportSkill->skill_id,
            ]);
    }

    public function test_superadmin_can_get_report_skills_by_report(): void
    {
        $user = $this->makeUser();

        $report = $this->makeMonthlyReport();

        $skillOne = $this->makeLearningSkill();
        $skillTwo = $this->makeLearningSkill();

        $this->makeReportSkill($report, $skillOne);
        $this->makeReportSkill($report, $skillTwo);

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'superadmin.monthly-reports.skills',
                    [
                        'reportId' => $report->id,
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_superadmin_can_get_report_skills_by_skill(): void
    {
        $user = $this->makeUser();

        $skill = $this->makeLearningSkill();

        $reportOne = $this->makeMonthlyReport();
        $reportTwo = $this->makeMonthlyReport();

        $this->makeReportSkill($reportOne, $skill);
        $this->makeReportSkill($reportTwo, $skill);

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'superadmin.learning-skills.monthly-reports',
                    [
                        'skillId' => $skill->id,
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_superadmin_can_update_monthly_report_skill(): void
    {
        $user = $this->makeUser();

        $reportSkill = $this->makeReportSkill();

        $response = $this
            ->actingAs($user)
            ->put(
                route(
                    'superadmin.monthly-report-skills.update',
                    [
                        'reportId' => $reportSkill->report_id,
                        'skillId' => $reportSkill->skill_id,
                    ]
                ),
                [
                    'report_id' => $reportSkill->report_id,
                    'skill_id' => $reportSkill->skill_id,
                    'trend' => 'stable',
                    'description' => 'Updated description.',
                ]
            );

        $response->assertOk();

        $this->assertDatabaseHas(
            'monthly_report_skills',
            [
                'report_id' => $reportSkill->report_id,
                'skill_id' => $reportSkill->skill_id,
                'trend' => 'stable',
                'description' => 'Updated description.',
            ]
        );
    }

    public function test_superadmin_can_delete_monthly_report_skill(): void
    {
        $user = $this->makeUser();

        $reportSkill = $this->makeReportSkill();

        $response = $this
            ->actingAs($user)
            ->delete(
                route(
                    'superadmin.monthly-report-skills.destroy',
                    [
                        'reportId' => $reportSkill->report_id,
                        'skillId' => $reportSkill->skill_id,
                    ]
                )
            );

        $response->assertOk();

        $this->assertDatabaseMissing(
            'monthly_report_skills',
            [
                'report_id' => $reportSkill->report_id,
                'skill_id' => $reportSkill->skill_id,
            ]
        );
    }
}
