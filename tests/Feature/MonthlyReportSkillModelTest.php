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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyReportSkillModelTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_model_uses_correct_table(): void
    {
        $model = new MonthlyReportSkill();

        $this->assertSame(
            'monthly_report_skills',
            $model->getTable()
        );
    }

    public function test_model_does_not_use_timestamps(): void
    {
        $model = new MonthlyReportSkill();

        $this->assertFalse($model->usesTimestamps());
    }

    public function test_model_can_store_report_skill_data(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $reportSkill = MonthlyReportSkill::query()->create([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'trend' => 'improving',
            'description' => 'Student menunjukkan perkembangan yang baik.',
        ]);

        $this->assertSame(
            $report->id,
            $reportSkill->report_id
        );

        $this->assertSame(
            $skill->id,
            $reportSkill->skill_id
        );

        $this->assertSame(
            'improving',
            $reportSkill->trend
        );

        $this->assertSame(
            'Student menunjukkan perkembangan yang baik.',
            $reportSkill->description
        );
    }

    public function test_trend_can_be_null(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $reportSkill = MonthlyReportSkill::query()->create([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'trend' => null,
            'description' => 'Description test.',
        ]);

        $this->assertNull($reportSkill->trend);
    }

    public function test_report_relation_returns_monthly_report(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $reportSkill = MonthlyReportSkill::query()->create([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'trend' => 'stable',
            'description' => 'Description test.',
        ]);

        $this->assertTrue(
            $reportSkill->report->is($report)
        );
    }

    public function test_skill_relation_returns_learning_skill(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $reportSkill = MonthlyReportSkill::query()->create([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'trend' => 'improving',
            'description' => 'Description test.',
        ]);

        $this->assertTrue(
            $reportSkill->skill->is($skill)
        );
    }

    public function test_same_report_and_skill_pair_cannot_be_inserted_twice(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        MonthlyReportSkill::query()->create([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'trend' => 'improving',
            'description' => 'First description.',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        MonthlyReportSkill::query()->create([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'trend' => 'stable',
            'description' => 'Second description.',
        ]);
    }

    public function test_same_report_can_have_multiple_different_skills(): void
    {
        $report = $this->makeMonthlyReport();

        $skillOne = $this->makeLearningSkill();
        $skillTwo = $this->makeLearningSkill();

        MonthlyReportSkill::query()->create([
            'report_id' => $report->id,
            'skill_id' => $skillOne->id,
            'trend' => 'improving',
            'description' => 'Skill one description.',
        ]);

        MonthlyReportSkill::query()->create([
            'report_id' => $report->id,
            'skill_id' => $skillTwo->id,
            'trend' => 'stable',
            'description' => 'Skill two description.',
        ]);

        $this->assertCount(
            2,
            MonthlyReportSkill::query()
                ->where('report_id', $report->id)
                ->get()
        );
    }

    public function test_deleting_report_deletes_related_report_skills(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        MonthlyReportSkill::query()->create([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'trend' => 'improving',
            'description' => 'Description test.',
        ]);

        $report->delete();

        $this->assertDatabaseMissing('monthly_report_skills', [
            'report_id' => $report->id,
            'skill_id' => $skill->id,
        ]);
    }
}
