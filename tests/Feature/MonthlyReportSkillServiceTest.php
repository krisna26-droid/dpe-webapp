<?php

namespace Tests\Feature;

use App\Models\LearningSkill;
use App\Models\Branch;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportSkill;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\MonthlyReportSkillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyReportSkillServiceTest extends TestCase
{
    use RefreshDatabase;

    private MonthlyReportSkillService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MonthlyReportSkillService::class);
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
        $studentUser = $this->makeUser('student');
        $student = Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $studentUser->id,
            'full_name' => 'Test Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 5',
            'began_on' => '2026-09-01',
            'status' => 'active',
        ]);

        $branch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'name' => 'Test Branch',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ]);

        $teacherUser = $this->makeUser('teacher');
        $teacher = Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $teacherUser->id,
            'whatsapp_number' => '081234567890',
            'is_active' => true,
        ]);

        $cycle = ReportCycle::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'cycle_number' => 1,
            'start_month' => now()->startOfMonth()->toDateString(),
            'end_month' => now()->startOfMonth()->addMonths(2)->toDateString(),
        ]);

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

    private function makeUser(string $roleCode): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => true,
        ]);
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

    public function test_can_create_monthly_report_skill(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $result = $this->service->create([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'trend' => 'improving',
            'description' => 'Student menunjukkan perkembangan yang baik.',
        ]);

        $this->assertInstanceOf(
            MonthlyReportSkill::class,
            $result
        );

        $this->assertSame(
            $report->id,
            $result->report_id
        );

        $this->assertSame(
            $skill->id,
            $result->skill_id
        );

        $this->assertSame(
            'improving',
            $result->trend
        );

        $this->assertSame(
            'Student menunjukkan perkembangan yang baik.',
            $result->description
        );
    }

    public function test_can_create_monthly_report_skill_without_trend(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $result = $this->service->create([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'description' => 'Description without trend.',
        ]);

        $this->assertNull($result->trend);

        $this->assertSame(
            'Description without trend.',
            $result->description
        );
    }

    public function test_can_find_monthly_report_skill_by_composite_key(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $created = $this->makeReportSkill(
            $report,
            $skill
        );

        $result = $this->service->find(
            $report->id,
            $skill->id
        );

        $this->assertTrue(
            $result->is($created)
        );
    }

    public function test_find_fails_when_composite_key_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->find(
            (string) Str::uuid(),
            (string) Str::uuid()
        );
    }

    public function test_can_get_monthly_report_skills_by_report(): void
    {
        $report = $this->makeMonthlyReport();

        $skillOne = $this->makeLearningSkill();
        $skillTwo = $this->makeLearningSkill();

        $this->makeReportSkill($report, $skillOne);
        $this->makeReportSkill($report, $skillTwo);

        $otherReport = $this->makeMonthlyReport();
        $otherSkill = $this->makeLearningSkill();

        $this->makeReportSkill(
            $otherReport,
            $otherSkill
        );

        $results = $this->service->getByReport($report);

        $this->assertCount(2, $results);

        $this->assertTrue(
            $results->every(
                fn(MonthlyReportSkill $item) =>
                $item->report_id === $report->id
            )
        );

        $this->assertTrue(
            $results->every(
                fn(MonthlyReportSkill $item) =>
                $item->relationLoaded('skill')
            )
        );
    }

    public function test_can_get_monthly_report_skills_by_skill(): void
    {
        $skill = $this->makeLearningSkill();

        $reportOne = $this->makeMonthlyReport();
        $reportTwo = $this->makeMonthlyReport();

        $this->makeReportSkill(
            $reportOne,
            $skill
        );

        $this->makeReportSkill(
            $reportTwo,
            $skill
        );

        $otherReport = $this->makeMonthlyReport();
        $otherSkill = $this->makeLearningSkill();

        $this->makeReportSkill(
            $otherReport,
            $otherSkill
        );

        $results = $this->service->getBySkill($skill);

        $this->assertCount(2, $results);

        $this->assertTrue(
            $results->every(
                fn(MonthlyReportSkill $item) =>
                $item->skill_id === $skill->id
            )
        );

        $this->assertTrue(
            $results->every(
                fn(MonthlyReportSkill $item) =>
                $item->relationLoaded('report')
            )
        );
    }

    public function test_can_update_monthly_report_skill(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $this->makeReportSkill(
            $report,
            $skill
        );

        $result = $this->service->update(
            $report->id,
            $skill->id,
            [
                'trend' => 'stable',
                'description' => 'Updated description.',
            ]
        );

        $this->assertSame(
            'stable',
            $result->trend
        );

        $this->assertSame(
            'Updated description.',
            $result->description
        );

        $this->assertDatabaseHas(
            'monthly_report_skills',
            [
                'report_id' => $report->id,
                'skill_id' => $skill->id,
                'trend' => 'stable',
                'description' => 'Updated description.',
            ]
        );
    }

    public function test_can_delete_monthly_report_skill(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $this->makeReportSkill(
            $report,
            $skill
        );

        $this->service->delete(
            $report->id,
            $skill->id
        );

        $this->assertDatabaseMissing(
            'monthly_report_skills',
            [
                'report_id' => $report->id,
                'skill_id' => $skill->id,
            ]
        );
    }
}
