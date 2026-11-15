<?php

namespace Tests\Feature;

use App\Models\LearningVideo;
use App\Models\MonthlyReport;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\SystemSetting;
use App\Services\MonthlyReportContentService;
use App\Services\StudentProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WhatsappReportFixtures;
use Tests\TestCase;

class VideoTargetPolicyTest extends TestCase
{
    use RefreshDatabase, WhatsappReportFixtures;

    private function defaultTarget(int $target = 4): void
    {
        SystemSetting::updateOrCreate(['id' => 1], [
            'organization_name' => 'DPE Test', 'default_report_due_day' => 31,
            'default_monthly_video_target' => $target,
        ]);
    }

    private function firstMonth(): MonthlyReport
    {
        $report = $this->monthly();
        $report->update(['video_target' => 4, 'video_target_confirmed_at' => null,
            'video_target_confirmed_by_user_id' => null]);

        return $report->fresh();
    }

    public static function shortVideoCounts(): array
    {
        return [[0, 'admin'], [2, 'admin'], [3, 'admin'],
            [0, 'superadmin'], [2, 'superadmin'], [3, 'superadmin']];
    }

    #[DataProvider('shortVideoCounts')]
    public function test_teacher_can_submit_below_target_without_confirmation_and_staff_can_approve(int $count, string $approver): void
    {
        $report = $this->firstMonth();
        for ($i = 0; $i < $count; $i++) {
            LearningVideo::create(['id' => (string) Str::uuid(), 'student_id' => $report->student_id,
                'teacher_id' => $report->teacher_id, 'video_on' => '2026-07-15', 'topic' => 'Latihan '.$i,
                'video_url' => 'https://example.com/learning-video/'.$i]);
        }
        $content = app(MonthlyReportContentService::class);
        $readiness = $content->readiness($report);
        $this->assertTrue($readiness['ready']);
        $this->assertSame($count, $readiness['videoCount']);
        $this->assertSame(4 - $count, $readiness['videoShortage']);

        $this->actingAs($this->teacher->user)->post(route('teacher.monthly-reports.submit', $report), [
            'expected_snapshot' => $content->signature($report),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('submitted', $report->fresh()->status);

        $actor = $approver === 'admin' ? $this->admin : $this->superadmin;
        $this->actingAs($actor)->post(route($approver.'.monthly-reports.approve', $report), [
            'expected_snapshot' => $content->signature($report->fresh()),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('approved', $report->fresh()->status);
        $this->assertSame($actor->id, $report->fresh()->approved_by_user_id);
        $this->assertSame(4, $report->fresh()->approved_snapshot['public_content']['video_target']);
        $this->assertCount($count, $report->fresh()->approved_snapshot['public_content']['videos']);
    }

    public function test_no_role_can_see_or_update_a_per_report_target(): void
    {
        $report = $this->firstMonth();
        foreach (['admin' => $this->admin, 'teacher' => $this->teacher->user, 'superadmin' => $this->superadmin] as $role => $actor) {
            $this->actingAs($actor)->get(route($role.'.student-progress.edit', $report))
                ->assertOk()->assertDontSee('name="video_target"', false)->assertDontSee('Simpan target')
                ->assertDontSee('Target belum dikonfirmasi');
            $this->put(route($role.'.student-progress.target', $report), [
                'video_target' => 0, 'expected_snapshot' => app(StudentProgressService::class)->signature($report),
            ])->assertForbidden();
        }
        $this->assertSame(4, $report->fresh()->video_target);
        $this->assertNull($report->fresh()->video_target_confirmed_by_user_id);
    }

    public static function preparingRoles(): array
    {
        return [['admin'], ['superadmin']];
    }

    #[DataProvider('preparingRoles')]
    public function test_staff_prepares_a_partial_first_month_using_system_default_only(string $role): void
    {
        $this->defaultTarget();
        $student = $this->otherStudent;
        $student->update(['began_on' => '2026-10-05']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id,
            'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-10-05']);
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id,
            'teacher_id' => $this->teacher->id, 'starts_on' => '2026-10-05', 'is_report_owner' => true]);
        $data = ['month' => '2026-10', 'teacher_id' => $this->teacher->id];

        $actor = $role === 'admin' ? $this->admin : $this->superadmin;
        $this->actingAs($actor)->get(route($role.'.student-progress.preparation', ['student' => $student, 'month' => '2026-10']))
            ->assertOk()->assertDontSee('name="video_target"', false)->assertSee('Target video default: 4');
        $this->post(route($role.'.student-progress.prepare', $student), $data + ['video_target' => 1])
            ->assertSessionHasErrors('video_target');
        $this->assertDatabaseMissing('monthly_reports', ['student_id' => $student->id]);
        $this->post(route($role.'.student-progress.prepare', $student), $data)
            ->assertRedirect()->assertSessionHasNoErrors();

        $report = MonthlyReport::where('student_id', $student->id)->sole();
        $this->assertSame(4, $report->video_target);
        $this->assertNull($report->video_target_confirmed_at);
        $this->assertTrue(app(StudentProgressService::class)->targetReady($report));
    }

    public function test_system_settings_are_the_only_target_control_and_approved_snapshots_stay_intact(): void
    {
        $this->defaultTarget();
        $report = $this->firstMonth();
        $content = app(MonthlyReportContentService::class);
        $this->actingAs($this->teacher->user)->post(route('teacher.monthly-reports.submit', $report), [
            'expected_snapshot' => $content->signature($report),
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.monthly-reports.approve', $report), [
            'expected_snapshot' => $content->signature($report->fresh()),
        ])->assertSessionHasNoErrors();
        $before = $report->fresh()->approved_snapshot;
        $settings = app(\App\Services\DashboardSettingsService::class);
        $this->actingAs($this->superadmin)->get(route('superadmin.settings.index'))->assertOk()
            ->assertSee('name="default_monthly_video_target"', false);
        $this->put(route('superadmin.settings.update'), [
            'organization_name' => 'DPE Test', 'organization_description' => 'Pengaturan uji',
            'contact_email' => 'dpe@example.test', 'contact_phone' => '081234567890',
            'default_report_due_day' => 31, 'default_monthly_video_target' => 7,
            'in_app_reminders_enabled' => 1, 'record_version' => $settings->version($settings->setting()),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(7, SystemSetting::first()->default_monthly_video_target);
        $this->assertSame(4, $report->fresh()->video_target);
        $this->assertSame($before, $report->fresh()->approved_snapshot);

        $draft = $this->student->monthlyReports()->whereDate('report_month', '2026-08-01')->firstOrFail();
        $this->actingAs($this->superadmin)->get(route('superadmin.student-progress.edit', $draft))
            ->assertOk()->assertDontSee('name="video_target"', false)->assertDontSee('Simpan target');
        $this->put(route('superadmin.student-progress.target', $draft), [
            'video_target' => 7, 'expected_snapshot' => app(StudentProgressService::class)->signature($draft),
        ])->assertForbidden();
        $this->assertSame(0, $draft->fresh()->video_target);

        $data = ['month' => '2026-10', 'teacher_id' => $this->teacher->id];
        $this->post(route('superadmin.student-progress.prepare', $this->student), $data + ['video_target' => 1])
            ->assertSessionHasErrors('video_target');
        $this->post(route('superadmin.student-progress.prepare', $this->student), $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $next = $this->student->monthlyReports()->whereDate('report_month', '2026-10-01')->sole();
        $this->assertSame(7, $next->video_target);
    }
}
