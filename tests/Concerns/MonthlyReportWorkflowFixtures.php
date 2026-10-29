<?php

namespace Tests\Concerns;

use App\Models\LearningSkill;
use App\Models\MonthlyReport;
use App\Services\StudentProgressService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait MonthlyReportWorkflowFixtures
{
    private function createWorkflowSupportTables(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->softDeletes();
            $table->char('id', 36)->primary();
            $table->string('full_name');
            $table->date('began_on');
        });
        Schema::create('branches', function (Blueprint $table) {
            $table->softDeletes();
            $table->char('id', 36)->primary();
            $table->string('name');
            $table->string('timezone_name');
        });
        Schema::create('learning_videos', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('student_id', 36);
            $table->char('teacher_id', 36);
            $table->char('session_id', 36)->nullable();
            $table->date('video_on');
            $table->string('topic');
            $table->text('description')->nullable();
            $table->string('video_url');
        });
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->dateTime('video_target_confirmed_at')->nullable();
            $table->char('video_target_confirmed_by_user_id', 36)->nullable();
        });
    }

    private function addWorkflowReferences(MonthlyReport $report): void
    {
        DB::table('students')->insert(['id' => $report->student_id, 'full_name' => 'Siswa Workflow', 'began_on' => $report->report_month->copy()->startOfMonth()->toDateString()]);
        DB::table('branches')->updateOrInsert(['id' => $report->branch_id], ['name' => 'Cabang Workflow', 'timezone_name' => 'Asia/Makassar']);
        if ($report->status === 'submitted') {
            $this->seedCompleteWorkflowSkills($report);
        }
    }

    private function seedCompleteWorkflowSkills(MonthlyReport $report, string $description = 'Siswa menunjukkan perkembangan yang baik.'): void
    {
        foreach (StudentProgressService::CATEGORIES as $code => $label) {
            $skill = LearningSkill::firstOrCreate(['code' => $code], ['id' => (string) Str::uuid(), 'name' => $label, 'display_order' => 1, 'is_active' => true]);
            DB::table('monthly_report_skills')->updateOrInsert(['report_id' => $report->id, 'skill_id' => $skill->id], ['trend' => 'Improving', 'description' => $description]);
        }
    }
}
