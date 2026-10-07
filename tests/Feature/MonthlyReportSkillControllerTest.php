<?php

namespace Tests\Feature;

use App\Http\Controllers\MonthlyReportSkillController;
use App\Models\Branch;
use App\Models\LearningSkill;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportSkill;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\MonthlyReportSkillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlyReportSkillControllerTest extends TestCase
{
    use RefreshDatabase;

    private MonthlyReportSkillService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MonthlyReportSkillService::class);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'user-' . Str::lower(Str::random(10)),
            'email' => 'user-' . Str::lower(Str::random(10)) . '@example.com',
            'password_hash' => bcrypt('secret'),
            'full_name' => 'Test User',
            'role_code' => 'teacher',
            'is_active' => true,
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

    private function makeValidatedRequest(array $data): \App\Http\Requests\MonthlyReportSkillStoreRequest
    {
        $request = new \App\Http\Requests\MonthlyReportSkillStoreRequest();

        $request->merge($data);
        $request->setUserResolver(fn() => null);
        $request->setContainer(app());
        $request->setValidator(Validator::make($data, $request->rules()));

        return $request;
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

    public function test_store_returns_created_report_skill(): void
    {
        $report = $this->makeMonthlyReport();
        $skill = $this->makeLearningSkill();

        $request = $this->makeValidatedRequest([
            'report_id' => $report->id,
            'skill_id' => $skill->id,
            'trend' => 'improving',
            'description' => 'Student menunjukkan perkembangan.',
        ]);

        $controller = new MonthlyReportSkillController(
            $this->service
        );

        $response = $controller->store($request);

        $this->assertSame(
            201,
            $response->getStatusCode()
        );

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

    public function test_show_returns_report_skill(): void
    {
        $reportSkill = $this->makeReportSkill();

        $controller = new MonthlyReportSkillController(
            $this->service
        );

        $response = $controller->show(
            $reportSkill->report_id,
            $reportSkill->skill_id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            $reportSkill->report_id,
            $response->getData(true)['report_id']
        );

        $this->assertSame(
            $reportSkill->skill_id,
            $response->getData(true)['skill_id']
        );
    }

    public function test_by_report_returns_report_skills(): void
    {
        $report = $this->makeMonthlyReport();

        $skillOne = $this->makeLearningSkill();
        $skillTwo = $this->makeLearningSkill();

        $this->makeReportSkill($report, $skillOne);
        $this->makeReportSkill($report, $skillTwo);

        $controller = new MonthlyReportSkillController(
            $this->service
        );

        $response = $controller->byReport(
            $report->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $data = $response->getData(true);

        $this->assertCount(2, $data);
    }

    public function test_by_skill_returns_report_skills(): void
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

        $controller = new MonthlyReportSkillController(
            $this->service
        );

        $response = $controller->bySkill(
            $skill->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $data = $response->getData(true);

        $this->assertCount(2, $data);
    }

    public function test_update_returns_updated_report_skill(): void
    {
        $reportSkill = $this->makeReportSkill();

        $request = $this->makeValidatedRequest([
            'report_id' => $reportSkill->report_id,
            'skill_id' => $reportSkill->skill_id,
            'trend' => 'stable',
            'description' => 'Updated description.',
        ]);

        $controller = new MonthlyReportSkillController(
            $this->service
        );

        $response = $controller->update(
            $request,
            $reportSkill->report_id,
            $reportSkill->skill_id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

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

    public function test_destroy_deletes_report_skill(): void
    {
        $reportSkill = $this->makeReportSkill();

        $controller = new MonthlyReportSkillController(
            $this->service
        );

        $response = $controller->destroy(
            $reportSkill->report_id,
            $reportSkill->skill_id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertDatabaseMissing(
            'monthly_report_skills',
            [
                'report_id' => $reportSkill->report_id,
                'skill_id' => $reportSkill->skill_id,
            ]
        );
    }
}
