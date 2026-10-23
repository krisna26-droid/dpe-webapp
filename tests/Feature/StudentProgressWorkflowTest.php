<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LearningSkill;
use App\Models\LearningVideo;
use App\Models\LessonSession;
use App\Models\MonthlyProgressAudit;
use App\Models\MonthlyReport;
use App\Models\Program;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\SystemSetting;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\LearningVideoService;
use App\Services\MonthlyReportService;
use App\Services\MonthlyReportSkillService;
use App\Services\StudentProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentProgressWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $admin;

    private User $superadmin;

    private Teacher $teacher;

    private Branch $branch;

    private Program $program;

    private Student $student;

    private StudentProgressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-11 18:00:00', 'Asia/Makassar'));
        $this->actor = $this->user('teacher');
        $this->admin = $this->user('admin');
        $this->superadmin = $this->user('superadmin');
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Progres', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'English Progres', 'class_type' => 'regular', 'is_active' => true]);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-10-01']);
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-10-01']);
        $this->student = $this->newStudent();
        SystemSetting::create(['id' => 1, 'organization_name' => 'DPE Test', 'default_report_due_day' => 31, 'default_monthly_video_target' => 4]);
        $this->service = app(StudentProgressService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'password_hash' => 'fixture-hash', 'full_name' => ucfirst($role).' Progres', 'role_code' => $role, 'is_active' => true]);
    }

    private function newStudent(string $name = 'Siswa Progres', string $join = '2026-10-05'): Student
    {
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student')->id, 'full_name' => $name, 'began_on' => $join, 'status' => 'active', 'special_notes_internal' => 'RAHASIA PROFIL']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => $join]);

        return $student;
    }

    private function report(?Student $student = null, string $month = '2026-10'): MonthlyReport
    {
        return $this->service->prepare($this->admin, $student ?? $this->student, CarbonImmutable::createFromFormat('!Y-m', $month), ['teacher_id' => $this->teacher->id, 'video_target' => 2]);
    }

    private function url(string $action, MonthlyReport $report, string $role = 'teacher', ?LearningVideo $video = null): string
    {
        return route($role.'.student-progress.'.$action, $video ? [$report, $video] : $report);
    }

    private function payload(MonthlyReport $report, array $extra = []): array
    {
        return $extra + ['expected_snapshot' => $this->service->signature($report->fresh()),
            'skills' => collect(StudentProgressService::CATEGORIES)->map(fn ($name) => ['trend' => 'Improving', 'description' => 'Perkembangan '.$name])->all(),
            'development_summary' => 'Ringkasan publik', 'parent_challenges_summary' => 'Latihan pendampingan publik',
            'parent_message' => 'Pesan untuk orang tua', 'internal_teacher_note' => 'RAHASIA BULANAN'];
    }

    private function videoPayload(MonthlyReport $report, array $extra = []): array
    {
        return $extra + ['expected_snapshot' => $this->service->signature($report->fresh()), 'video_on' => '2026-10-06',
            'topic' => 'Greeting practice', 'description' => 'Latihan salam', 'video_url' => 'https://example.com/learning-video', 'session_id' => null];
    }

    public function test_admin_prepares_partial_first_month_with_manual_target_and_correct_three_month_cycle(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.student-progress.preparation', ['student' => $this->student, 'month' => '2026-10']))->assertOk()
            ->assertSee('Siswa Progres')->assertSee('05 Oct 2026')->assertSee('Kembali ke daftar siswa');
        $response->assertViewHas('firstPartialMonth', true);
        $this->post(route('admin.student-progress.prepare', $this->student), ['month' => '2026-10', 'teacher_id' => $this->teacher->id])->assertSessionHasErrors('video_target');
        $this->assertDatabaseCount('monthly_reports', 0);
        $this->post(route('admin.student-progress.prepare', $this->student), ['month' => '2026-10', 'teacher_id' => $this->teacher->id, 'video_target' => 0])->assertRedirect();
        $report = MonthlyReport::firstOrFail();
        $this->assertSame(0, $report->video_target);
        $this->assertSame($this->admin->id, $report->video_target_confirmed_by_user_id);
        $this->assertTrue($this->service->targetReady($report));
        $this->assertSame('2026-10-01', $report->cycle->start_month->toDateString());
        $this->assertSame('2026-12-01', $report->cycle->end_month->toDateString());
        $this->assertDatabaseCount('monthly_reports', 1);
        $this->assertDatabaseCount('monthly_progress_audits', 1);
    }

    public function test_prepare_is_idempotent_and_does_not_overwrite_existing_teacher_target_or_cycle(): void
    {
        $report = $this->report();
        $again = $this->service->prepare($this->admin, $this->student, CarbonImmutable::parse('2026-10-01'), ['teacher_id' => 'invalid', 'video_target' => 9]);
        $this->assertSame($report->id, $again->id);
        $this->assertSame(2, $again->video_target);
        $this->assertSame($this->teacher->id, $again->teacher_id);
        $this->assertDatabaseCount('report_cycles', 1);
        $this->assertDatabaseCount('monthly_progress_audits', 1);
    }

    public function test_next_month_default_is_snapshotted_and_setting_changes_preserve_existing_periods(): void
    {
        $first = $this->report();
        $second = $this->service->prepare($this->admin, $this->student, CarbonImmutable::parse('2026-11-01'), ['teacher_id' => $this->teacher->id]);
        $this->assertSame(4, $second->video_target);
        SystemSetting::first()->update(['default_monthly_video_target' => 7]);
        $third = $this->service->prepare($this->admin, $this->student, CarbonImmutable::parse('2026-12-01'), ['teacher_id' => $this->teacher->id]);
        $this->assertSame(2, $first->fresh()->video_target);
        $this->assertSame(4, $second->fresh()->video_target);
        $this->assertSame(7, $third->video_target);
        $this->assertSame($first->cycle_id, $third->cycle_id);
        $this->assertSame('2026-11-30', $second->due_on->toDateString());
    }

    public function test_join_on_day_one_uses_default_and_no_first_partial_target_warning(): void
    {
        $student = $this->newStudent('Mulai Awal', '2026-10-01');
        $report = $this->service->prepare($this->admin, $student, CarbonImmutable::parse('2026-10-01'), ['teacher_id' => $this->teacher->id]);
        $this->assertSame(4, $report->video_target);
        $this->assertTrue($this->service->targetReady($report));
    }

    public function test_teacher_cannot_prepare_period_or_change_target(): void
    {
        $report = $this->report();
        $this->actingAs($this->actor)->get(route('teacher.student-progress.preparation', ['student' => $this->student, 'month' => '2026-10']))->assertForbidden();
        $this->post(route('teacher.student-progress.prepare', $this->student), ['month' => '2026-10', 'teacher_id' => $this->teacher->id, 'video_target' => 8])->assertForbidden();
        $this->put($this->url('target', $report), ['video_target' => 8, 'expected_snapshot' => $this->service->signature($report)])->assertForbidden();
        $this->assertSame(2, $report->fresh()->video_target);
    }

    public function test_teacher_sees_prepared_student_even_without_separate_student_assignment_and_real_form_has_eight_categories(): void
    {
        $report = $this->report();
        $other = $this->newStudent('Siswa Tidak Ditugaskan');
        $this->actingAs($this->actor)->get(route('teacher.student-progress.index', ['month' => '2026-10']))->assertOk()->assertSee('Siswa Progres')->assertDontSee($other->full_name);
        $response = $this->get($this->url('edit', $report))->assertOk()->assertSee('Kembali ke daftar siswa')->assertSee('Simpan draf & tambah video')->assertSee('Catatan internal bulanan')->assertDontSee('RAHASIA PROFIL');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(8, $xpath->query('//fieldset[contains(@class,"dpe-progress-skill")]')->length);
        $this->assertSame(8, $xpath->query('//select[starts-with(@name,"skills[")]')->length);
        $this->assertSame(1, $xpath->query('//textarea[@name="internal_teacher_note"]')->length);
        $this->assertDatabaseCount('monthly_report_skills', 0);
    }

    public static function rolesDenied(): array
    {
        return [['student'], ['other-teacher'], ['other-admin'], ['inactive-teacher']];
    }

    #[DataProvider('rolesDenied')]
    public function test_unrelated_and_inactive_accounts_cannot_read_or_write_period(string $type): void
    {
        $report = $this->report();
        $role = $type === 'student' ? 'student' : ($type === 'other-admin' ? 'admin' : 'teacher');
        $actor = $type === 'inactive-teacher' ? $this->actor : $this->user($role);
        if ($type === 'inactive-teacher') {
            $actor->update(['is_active' => false]);
        }
        $this->actingAs($actor)->get($this->url('edit', $report))->assertForbidden();
        $this->put($this->url('save', $report), $this->payload($report))->assertForbidden();
        $this->assertNull($report->fresh()->parent_message);
    }

    public function test_inactive_teacher_profile_cannot_access_even_when_user_is_active(): void
    {
        $report = $this->report();
        $this->teacher->update(['is_active' => false]);
        $this->actingAs($this->actor)->get($this->url('edit', $report))->assertForbidden();
    }

    public function test_admin_branch_scope_and_superadmin_global_scope_apply_to_index_and_target(): void
    {
        $report = $this->report();
        $otherAdmin = $this->user('admin');
        $this->actingAs($otherAdmin)->get(route('admin.student-progress.index', ['month' => '2026-10']))->assertOk()->assertDontSee('Siswa Progres');
        $this->put($this->url('target', $report, 'admin'), ['video_target' => 5, 'expected_snapshot' => $this->service->signature($report)])->assertForbidden();
        $this->actingAs($this->superadmin)->get($this->url('edit', $report, 'superadmin'))->assertOk()->assertSee('Siswa Progres');
        $this->put($this->url('target', $report, 'superadmin'), ['video_target' => 5, 'expected_snapshot' => $this->service->signature($report)])->assertRedirect();
        $this->assertSame(5, $report->fresh()->video_target);
    }

    public function test_public_content_and_internal_content_are_saved_separately_without_attendance_or_message_side_effects(): void
    {
        $report = $this->report();
        $this->actingAs($this->actor)->put($this->url('save', $report), $this->payload($report, ['status' => 'approved', 'teacher_id' => 'forged', 'video_target' => 100]))
            ->assertRedirect($this->url('edit', $report));
        $report->refresh();
        $this->assertSame('draft', $report->status);
        $this->assertSame(2, $report->video_target);
        $this->assertSame('RAHASIA BULANAN', $report->internal_teacher_note);
        $this->assertSame('Pesan untuk orang tua', $report->parent_message);
        $this->assertSame('Latihan pendampingan publik', $report->parent_challenges_summary);
        $this->assertSame(8, $report->reportSkills()->count());
        $this->assertDatabaseCount('teacher_messages', 0);
        $this->assertDatabaseCount('student_challenges', 0);
        $this->assertDatabaseCount('session_students', 0);
        $audit = MonthlyProgressAudit::latest('id')->first();
        $this->assertSame($this->actor->id, $audit->actor_user_id);
        $this->assertSame('progress_saved', $audit->event_type);
        $this->assertSame(8, count($audit->after_snapshot['skills']));
        $this->assertNull($audit->before_snapshot['report']['parent_message']);
    }

    public function test_draft_allows_empty_categories_and_zero_text_is_preserved_without_creating_blank_skills(): void
    {
        $report = $this->report();
        $blank = collect(StudentProgressService::CATEGORIES)->map(fn () => ['trend' => null, 'description' => null])->all();
        $blank['speaking'] = ['trend' => 'Stable', 'description' => '0'];
        $this->actingAs($this->actor)->put($this->url('save', $report), $this->payload($report, ['skills' => $blank, 'internal_teacher_note' => '0', 'parent_message' => null, 'parent_challenges_summary' => null]))->assertRedirect();
        $this->assertSame(1, $report->reportSkills()->count());
        $this->assertSame('0', $report->reportSkills()->first()->description);
        $this->assertSame('0', $report->fresh()->internal_teacher_note);
        $this->assertNull($report->parent_message);
    }

    public static function videoNavigationRoles(): array
    {
        return [['teacher'], ['admin'], ['superadmin']];
    }

    #[DataProvider('videoNavigationRoles')]
    public function test_save_then_add_video_keeps_all_skills_and_messages_after_cancelling(string $role): void
    {
        $report = $this->report();
        $user = match ($role) { 'admin' => $this->admin, 'superadmin' => $this->superadmin, default => $this->actor };
        $this->actingAs($user);
        $payload = $this->payload($report, ['next' => 'video_create']);
        $this->put($this->url('save', $report, $role), $payload)
            ->assertRedirect($this->url('videos.create', $report, $role))->assertSessionHasNoErrors();
        $this->get($this->url('videos.create', $report, $role))->assertOk()
            ->assertViewHas('expectedSnapshot', $this->service->signature($report->fresh()));
        $back = $this->get($this->url('edit', $report, $role))->assertOk();
        foreach (StudentProgressService::CATEGORIES as $code => $label) {
            $back->assertSee('Perkembangan '.$label);
            $this->assertTrue($report->reportSkills()->where('trend', 'Improving')->where('description', 'Perkembangan '.$label)->exists());
        }
        foreach (array_keys(StudentProgressService::CONTENT) as $field) {
            $this->assertSame($payload[$field], $report->fresh()->$field);
        }
        $dom = new \DOMDocument;
        @$dom->loadHTML($back->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(8, $xpath->query('//select[starts-with(@name,"skills[")]/option[@selected and @value="Improving"]')->length);
        $form = $xpath->query('//form[contains(@class,"dpe-progress-form")]')->item(0);
        $button = $xpath->query('//button[@name="next" and @value="video_create"]')->item(0);
        $this->assertNotNull($button);
        $this->assertSame($form->getAttribute('id'), $button->getAttribute('form'));
        $this->assertSame('draft', $report->fresh()->status);
        $this->assertSame(8, $report->reportSkills()->count());
        $this->assertSame(1, MonthlyProgressAudit::where('report_id', $report->id)->where('event_type', 'progress_saved')->count());
        $this->assertDatabaseCount('learning_videos', 0);
    }

    public function test_partial_valid_draft_can_continue_to_video_without_completing_all_categories(): void
    {
        $report = $this->report();
        $skills = collect(StudentProgressService::CATEGORIES)->map(fn () => ['trend' => null, 'description' => null])->all();
        $skills['speaking'] = ['trend' => 'Stable', 'description' => '0'];
        $this->actingAs($this->actor)->put($this->url('save', $report), $this->payload($report, ['skills' => $skills, 'next' => 'video_create']))
            ->assertRedirect($this->url('videos.create', $report))->assertSessionHasNoErrors();
        $this->assertSame(1, $report->reportSkills()->count());
        $this->assertSame('0', $report->reportSkills()->first()->description);
    }

    public function test_invalid_progress_stays_on_editor_with_input_before_opening_video(): void
    {
        $report = $this->report();
        $payload = $this->payload($report, ['next' => 'video_create']);
        $payload['skills']['speaking']['description'] = '';
        $this->actingAs($this->actor)->from($this->url('edit', $report))->put($this->url('save', $report), $payload)
            ->assertRedirect($this->url('edit', $report))->assertSessionHasErrors('skills.speaking.description')
            ->assertSessionHasInput('parent_message', $payload['parent_message'])
            ->assertSessionHasInput('skills.listening.description', $payload['skills']['listening']['description']);
        $this->assertSame(0, $report->reportSkills()->count());
        $this->assertDatabaseCount('learning_videos', 0);
    }

    public function test_stale_progress_cannot_continue_to_video_or_overwrite_newer_data(): void
    {
        $report = $this->report();
        $payload = $this->payload($report, ['next' => 'video_create']);
        $this->service->setTarget($this->admin, $report, ['video_target' => 3, 'expected_snapshot' => $payload['expected_snapshot']]);
        $this->actingAs($this->actor)->from($this->url('edit', $report))->put($this->url('save', $report), $payload)
            ->assertRedirect($this->url('edit', $report))->assertSessionHasErrors('expected_snapshot')
            ->assertSessionHasInput('parent_message', $payload['parent_message']);
        $this->assertSame(0, $report->reportSkills()->count());
        $this->assertSame(3, $report->fresh()->video_target);
    }

    public static function videoNavigationActions(): array
    {
        return [['edit', 'videos.edit'], ['remove', 'videos.confirm']];
    }

    #[DataProvider('videoNavigationActions')]
    public function test_edit_and_remove_video_also_save_progress_before_opening_their_forms(string $action, string $destination): void
    {
        $report = $this->report();
        $video = $this->service->saveVideo($this->actor, $report, $this->videoPayload($report));
        $this->actingAs($this->actor)->put($this->url('save', $report), $this->payload($report, ['next' => 'video_'.$action.':'.$video->id]))
            ->assertRedirect($this->url($destination, $report, video: $video))->assertSessionHasNoErrors();
        $this->get($this->url($destination, $report, video: $video))->assertOk();
        $this->get($this->url('edit', $report))->assertOk()->assertSee('Ringkasan publik')->assertSee('Perkembangan Speaking');
        $this->assertSame(8, $report->reportSkills()->count());
        $this->assertSame('Ringkasan publik', $report->fresh()->development_summary);
        $this->assertDatabaseCount('learning_videos', 1);
        $this->assertSame('Greeting practice', $video->fresh()->topic);
    }

    public function test_forged_video_navigation_cannot_save_progress_or_access_another_student_video(): void
    {
        $report = $this->report();
        $video = $this->service->saveVideo($this->actor, $report, $this->videoPayload($report));
        $video->update(['student_id' => $this->newStudent('Siswa lain')->id]);
        $this->actingAs($this->actor)->put($this->url('save', $report), $this->payload($report, ['next' => 'video_edit:'.$video->id]))->assertForbidden();
        $this->assertSame(0, $report->reportSkills()->count());
        $this->assertNull($report->fresh()->parent_message);
    }

    public static function invalidVideoNavigation(): array
    {
        return [['video_edit:invalid', 422], ['video_remove:'.'00000000-0000-4000-8000-000000000001', 404],
            ['video_unknown:'.'00000000-0000-4000-8000-000000000001', 422], [['video_create'], 422]];
    }

    #[DataProvider('invalidVideoNavigation')]
    public function test_invalid_video_destination_does_not_write_progress(mixed $next, int $status): void
    {
        $report = $this->report();
        $this->actingAs($this->actor)->putJson($this->url('save', $report), $this->payload($report, ['next' => $next]))->assertStatus($status);
        $this->assertSame(0, $report->reportSkills()->count());
        $this->assertNull($report->fresh()->parent_message);
    }

    #[DataProvider('lockedStatuses')]
    public function test_save_then_video_cannot_bypass_a_locked_report(string $status): void
    {
        $report = $this->report();
        $report->update(['status' => $status]);
        $this->actingAs($this->actor)->putJson($this->url('save', $report), $this->payload($report, ['next' => 'video_create']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame(0, $report->reportSkills()->count());
        $this->assertDatabaseCount('learning_videos', 0);
    }

    public static function invalidSkills(): array
    {
        return [['trend-only'], ['description-only'], ['unknown-trend'], ['missing-category'], ['extra-category'], ['long-description']];
    }

    #[DataProvider('invalidSkills')]
    public function test_invalid_skill_payload_rolls_back_entire_draft(string $case): void
    {
        $report = $this->report();
        $payload = $this->payload($report);
        match ($case) {
            'trend-only' => $payload['skills']['speaking']['description'] = null,
            'description-only' => $payload['skills']['speaking']['trend'] = null,
            'unknown-trend' => $payload['skills']['speaking']['trend'] = 'A',
            'missing-category' => $payload['skills'] = array_slice($payload['skills'], 1, null, true),
            'extra-category' => $payload['skills']['other'] = ['trend' => 'Stable', 'description' => 'Other'],
            'long-description' => $payload['skills']['speaking']['description'] = str_repeat('a', 4001),
        };
        $this->actingAs($this->actor)->put($this->url('save', $report), $payload)->assertSessionHasErrors();
        $this->assertDatabaseCount('monthly_report_skills', 0);
        $this->assertNull($report->fresh()->parent_message);
        $this->assertDatabaseCount('monthly_progress_audits', 1);
    }

    public function test_stale_form_cannot_overwrite_and_refresh_notice_retains_user_input(): void
    {
        $report = $this->report();
        $stale = $this->payload($report);
        $this->service->setTarget($this->admin, $report, ['video_target' => 3, 'expected_snapshot' => $stale['expected_snapshot']]);
        $editUrl = $this->url('edit', $report);
        $this->actingAs($this->actor)->from($editUrl)->put($this->url('save', $report), $stale)->assertRedirect($editUrl)->assertSessionHasErrors('expected_snapshot');
        $this->get($editUrl)->assertOk()->assertSee('Muat data terbaru')->assertSee('Ringkasan publik')->assertDontSee('Simpan draf perkembangan');
        $this->assertNull($report->fresh()->parent_message);
        $this->assertSame(3, $report->fresh()->video_target);
    }

    public function test_repeated_same_save_is_noop_and_revision_remains_revision(): void
    {
        $report = $this->report();
        $report->update(['status' => 'revision']);
        $this->assertTrue($this->service->save($this->actor, $report, $this->payload($report)));
        $this->assertFalse($this->service->save($this->actor, $report->fresh(), $this->payload($report)));
        $this->assertSame('revision', $report->fresh()->status);
        $this->assertDatabaseCount('monthly_progress_audits', 2);
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public static function lockedStatuses(): array
    {
        return [['submitted'], ['approved'], ['unknown']];
    }

    #[DataProvider('lockedStatuses')]
    public function test_locked_periods_reject_progress_target_video_and_legacy_mutations(string $status): void
    {
        $report = $this->report();
        $video = $this->service->saveVideo($this->actor, $report, $this->videoPayload($report));
        $report->update(['status' => $status]);
        $this->actingAs($this->actor)->get($this->url('edit', $report))->assertOk()->assertDontSee('Simpan draf perkembangan')->assertDontSee('value="video_create"', false);
        $this->put($this->url('save', $report), $this->payload($report))->assertSessionHasErrors('status');
        $this->post($this->url('videos.store', $report), $this->videoPayload($report))->assertSessionHasErrors('status');
        $this->delete($this->url('videos.delete', $report, 'teacher', $video), ['expected_snapshot' => $this->service->signature($report->fresh())])->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->put($this->url('target', $report, 'admin'), ['video_target' => 8, 'expected_snapshot' => $this->service->signature($report->fresh())])->assertSessionHasErrors('status');
        foreach (['legacy-video' => fn () => app(LearningVideoService::class)->delete($video),
            'legacy-skill' => fn () => app(MonthlyReportSkillService::class)->create(['report_id' => $report->id, 'skill_id' => $this->service->categories()->first()->id, 'trend' => 'Stable', 'description' => 'forged'])] as $run) {
            try {
                $run();
                $this->fail('Locked legacy mutation was accepted.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('status', $error->errors());
            }
        }
        $this->assertDatabaseCount('learning_videos', 1);
        $this->assertDatabaseCount('monthly_report_skills', 0);
        $this->assertSame(2, $report->fresh()->video_target);
    }

    public function test_future_period_allows_admin_target_planning_but_blocks_learning_data(): void
    {
        $report = $this->report(month: '2026-11');
        $this->actingAs($this->actor)->put($this->url('save', $report), $this->payload($report))->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->put($this->url('target', $report, 'admin'), ['video_target' => 3, 'expected_snapshot' => $this->service->signature($report->fresh())])->assertRedirect();
        $this->assertSame(3, $report->fresh()->video_target);
    }

    public function test_video_create_edit_and_confirmed_delete_preserve_attribution_and_period_scope(): void
    {
        $report = $this->report();
        $this->actingAs($this->actor)->get($this->url('videos.create', $report))->assertOk()->assertSee('Kembali ke perkembangan siswa');
        $this->post($this->url('videos.store', $report), $this->videoPayload($report, ['student_id' => 'forged', 'teacher_id' => 'forged']))->assertRedirect($this->url('edit', $report).'#learning-videos');
        $video = LearningVideo::firstOrFail();
        $this->assertSame($this->student->id, $video->student_id);
        $this->assertSame($this->teacher->id, $video->teacher_id);
        $this->get($this->url('videos.edit', $report, 'teacher', $video))->assertOk()->assertSee('Greeting practice');
        $this->put($this->url('videos.update', $report, 'teacher', $video), $this->videoPayload($report, ['topic' => 'New topic']))->assertRedirect();
        $this->assertSame('New topic', $video->fresh()->topic);
        $this->get($this->url('videos.confirm', $report, 'teacher', $video))->assertOk()->assertSee('Ya, hapus tautan')->assertSee('New topic');
        $this->assertDatabaseCount('learning_videos', 1);
        $this->delete($this->url('videos.delete', $report, 'teacher', $video), ['expected_snapshot' => $this->service->signature($report->fresh())])->assertRedirect();
        $this->assertDatabaseCount('learning_videos', 0);
        $this->assertSame('video_deleted', MonthlyProgressAudit::latest('id')->first()->event_type);
        $this->assertSame('New topic', MonthlyProgressAudit::latest('id')->first()->before_snapshot['videos'][0]['topic']);
    }

    public static function invalidVideos(): array
    {
        return [['video_url', 'javascript:alert(1)'], ['video_url', 'ftp://example.com/video'], ['video_url', '/local/video'],
            ['video_on', '2026-10-04'], ['video_on', '2026-10-12'], ['video_on', '2026-09-30'], ['video_on', '2026-11-01']];
    }

    #[DataProvider('invalidVideos')]
    public function test_invalid_video_dates_and_urls_are_rejected_without_count_changes(string $field, string $value): void
    {
        $report = $this->report();
        $this->actingAs($this->actor)->post($this->url('videos.store', $report), $this->videoPayload($report, [$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('learning_videos', 0);
        $this->assertDatabaseCount('monthly_progress_audits', 1);
    }

    public function test_duplicate_video_for_same_student_and_day_is_rejected_and_stale_video_edit_is_detected(): void
    {
        $report = $this->report();
        $video = $this->service->saveVideo($this->actor, $report, $this->videoPayload($report));
        $this->actingAs($this->actor)->post($this->url('videos.store', $report), $this->videoPayload($report))->assertSessionHasErrors('video_url');
        $stale = $this->videoPayload($report);
        $video->update(['topic' => 'Updated elsewhere']);
        $this->put($this->url('videos.update', $report, 'teacher', $video), $stale)->assertSessionHasErrors('expected_snapshot');
        $this->assertSame('Updated elsewhere', $video->fresh()->topic);
        $this->assertDatabaseCount('learning_videos', 1);
    }

    public function test_same_class_video_can_be_attached_to_each_student_and_counts_remain_per_student(): void
    {
        $first = $this->report();
        $second = $this->report($this->newStudent('Siswa Kedua'));
        $this->service->saveVideo($this->actor, $first, $this->videoPayload($first));
        $this->service->saveVideo($this->actor, $second, $this->videoPayload($second));
        $this->actingAs($this->actor)->get($this->url('edit', $first))->assertViewHas('videoCount', 1);
        $this->get($this->url('edit', $second))->assertViewHas('videoCount', 1);
        $this->assertDatabaseCount('learning_videos', 2);
    }

    public function test_optional_session_requires_real_roster_same_day_authorized_teacher_and_enrollment(): void
    {
        $report = $this->report();
        $group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'default_teacher_id' => $this->teacher->id, 'code' => Str::random(10), 'name' => 'Kelas Progres', 'is_active' => true]);
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $group->id, 'student_id' => $this->student->id, 'starts_on' => '2026-10-05']);
        $session = LessonSession::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'class_group_id' => $group->id,
            'planned_start_at' => '2026-10-06 09:00:00', 'planned_end_at' => '2026-10-06 10:00:00', 'teaching_location' => 'branch', 'status' => 'scheduled']);
        $this->actingAs($this->actor)->get($this->url('videos.create', $report))->assertOk()->assertSee('Kelas Progres');
        $this->post($this->url('videos.store', $report), $this->videoPayload($report, ['video_on' => '2026-10-07', 'session_id' => $session->id]))->assertSessionHasErrors('session_id');
        $this->post($this->url('videos.store', $report), $this->videoPayload($report, ['session_id' => $session->id]))->assertRedirect();
        $this->assertSame($session->id, LearningVideo::firstOrFail()->session_id);
        $session->update(['status' => 'cancelled']);
        $this->post($this->url('videos.store', $report), $this->videoPayload($report, ['session_id' => $session->id, 'video_url' => 'https://example.com/second']))->assertSessionHasErrors('session_id');
    }

    public function test_nested_video_routes_cannot_edit_other_students_or_other_teachers_records(): void
    {
        $report = $this->report();
        $other = $this->report($this->newStudent('Other student'));
        $video = $this->service->saveVideo($this->actor, $other, $this->videoPayload($other));
        $this->actingAs($this->actor)->get($this->url('videos.edit', $report, 'teacher', $video))->assertForbidden();
        $this->put($this->url('videos.update', $report, 'teacher', $video), $this->videoPayload($report))->assertForbidden();
        $anotherTeacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher')->id, 'whatsapp_number' => '0811', 'is_active' => true]);
        $video->update(['student_id' => $report->student_id, 'teacher_id' => $anotherTeacher->id]);
        $this->get($this->url('videos.edit', $report, 'teacher', $video))->assertForbidden();
        $this->actingAs($this->admin)->get($this->url('videos.edit', $report, 'admin', $video))->assertOk();
    }

    public function test_public_drafts_are_not_exposed_to_student_and_script_content_is_escaped_in_staff_views(): void
    {
        $report = $this->report();
        $this->service->save($this->actor, $report, $this->payload($report, ['parent_message' => '<script>alert("x")</script>']));
        $this->actingAs($this->actor)->get($this->url('edit', $report))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("x")</script>', false);
        $this->actingAs($this->student->portalUser)->get($this->url('edit', $report))->assertForbidden();
    }

    public function test_legacy_partial_first_month_needs_admin_confirmation_and_invalid_legacy_links_are_not_clickable_or_counted(): void
    {
        $report = $this->report();
        $report->update(['video_target_confirmed_at' => null, 'video_target_confirmed_by_user_id' => null]);
        LearningVideo::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'video_on' => '2026-10-06', 'topic' => 'Legacy', 'video_url' => 'javascript:alert(1)']);
        $this->actingAs($this->actor)->get($this->url('edit', $report))->assertOk()->assertSee('Target belum dikonfirmasi')->assertViewHas('videoCount', 0)->assertDontSee('href="javascript:', false);
        $this->actingAs($this->admin)->put($this->url('target', $report, 'admin'), ['video_target' => 0, 'expected_snapshot' => $this->service->signature($report->fresh())])->assertRedirect();
        $this->assertTrue($this->service->targetReady($report->fresh()));
    }

    public function test_existing_generator_accepts_join_in_middle_of_month_and_owner_assignment_starting_same_day(): void
    {
        $cycle = ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'cycle_number' => 1, 'start_month' => '2026-10-01', 'end_month' => '2026-12-01']);
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'starts_on' => '2026-10-05', 'is_report_owner' => true]);
        $reports = app(MonthlyReportService::class)->generateForCycle($cycle);
        $this->assertCount(3, $reports);
        $this->assertFalse($this->service->targetReady($reports->first()));
        $this->assertSame('2026-10-01', $reports->first()->report_month->toDateString());
        $this->assertSame($this->teacher->id, $reports->first()->teacher_id);
    }

    public function test_overlapping_existing_cycle_is_rejected_without_creating_partial_records(): void
    {
        ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'cycle_number' => 1, 'start_month' => '2026-11-01', 'end_month' => '2027-01-01']);
        $this->actingAs($this->admin)->post(route('admin.student-progress.prepare', $this->student), ['month' => '2026-10', 'teacher_id' => $this->teacher->id, 'video_target' => 2])->assertSessionHasErrors('month');
        $this->assertDatabaseCount('monthly_reports', 0);
        $this->assertDatabaseCount('report_cycles', 1);
        $this->assertDatabaseCount('monthly_progress_audits', 0);
    }

    public function test_migration_preserves_existing_skill_identity_flags_and_report_video_data(): void
    {
        $migration = require database_path('migrations/2026_10_11_070000_add_monthly_progress_support.php');
        $migration->down();
        $speaking = LearningSkill::where('code', 'speaking')->firstOrFail();
        $speaking->update(['code' => 'SPK_OLD', 'is_active' => false]);
        LearningSkill::where('id', '!=', $speaking->id)->delete();
        $custom = LearningSkill::create(['id' => (string) Str::uuid(), 'code' => 'custom_legacy', 'name' => 'Custom legacy', 'display_order' => 20, 'is_active' => false]);
        $cycle = ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'cycle_number' => 1, 'start_month' => '2026-10-01', 'end_month' => '2026-12-01']);
        $report = MonthlyReport::forceCreate(['id' => (string) Str::uuid(), 'cycle_id' => $cycle->id, 'student_id' => $this->student->id,
            'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'report_month' => '2026-10-01', 'due_on' => '2026-10-31',
            'video_target' => 3, 'status' => 'draft', 'parent_message' => 'Pesan lama', 'internal_teacher_note' => 'Catatan lama internal']);
        $video = LearningVideo::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id,
            'video_on' => '2026-10-06', 'topic' => 'Video lama', 'video_url' => 'https://example.com/old']);
        $migration->up();
        $this->assertSame($speaking->id, $this->service->categories()->get('speaking')->id);
        $this->assertSame('SPK_OLD', $speaking->fresh()->code);
        $this->assertFalse($speaking->fresh()->is_active);
        $this->assertFalse($custom->fresh()->is_active);
        $this->assertSame(3, $report->fresh()->video_target);
        $this->assertNull($report->video_target_confirmed_at);
        $this->assertSame('Pesan lama', $report->parent_message);
        $this->assertSame('Catatan lama internal', $report->internal_teacher_note);
        $this->assertSame('Video lama', $video->fresh()->topic);
        $this->assertDatabaseCount('learning_skills', 9);
        $this->assertDatabaseCount('monthly_progress_audits', 0);
        $this->assertCount(8, $this->service->categories()->filter());
    }

    public function test_branch_teacher_and_ambiguous_enrollment_cannot_be_used_to_prepare_wrong_period(): void
    {
        $teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher')->id, 'whatsapp_number' => '0811', 'is_active' => true]);
        $this->actingAs($this->admin)->post(route('admin.student-progress.prepare', $this->student), ['month' => '2026-10', 'teacher_id' => $teacher->id, 'video_target' => 2])->assertSessionHasErrors('teacher_id');
        $branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => 'Cabang Lain', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'branch_id' => $branch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-31']);
        $this->post(route('admin.student-progress.prepare', $this->student), ['month' => '2026-10', 'teacher_id' => $this->teacher->id, 'video_target' => 2])->assertSessionHasErrors('month');
        $this->assertDatabaseCount('monthly_reports', 0);
        $this->assertDatabaseCount('report_cycles', 0);
    }

    public function test_expired_admin_assignment_cannot_read_historical_period(): void
    {
        $report = $this->report();
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-10-10']);
        $this->actingAs($this->admin)->get($this->url('edit', $report, 'admin'))->assertForbidden();
        $this->get(route('admin.student-progress.index', ['month' => '2026-10']))->assertOk()->assertDontSee('Siswa Progres');
    }

    public function test_inactive_category_and_malformed_snapshot_cannot_partially_save(): void
    {
        $report = $this->report();
        $this->service->categories()->get('reading')->update(['is_active' => false]);
        $this->actingAs($this->actor)->put($this->url('save', $report), $this->payload($report))->assertSessionHasErrors('skills');
        $this->put($this->url('save', $report), $this->payload($report, ['expected_snapshot' => ['invalid']]))->assertSessionHasErrors('expected_snapshot');
        $this->assertDatabaseCount('monthly_report_skills', 0);
        $this->assertNull($report->fresh()->parent_message);
    }

    public function test_legacy_period_before_join_is_readonly_for_progress_and_target(): void
    {
        $report = $this->report();
        $this->student->update(['began_on' => '2026-11-05']);
        $this->actingAs($this->actor)->put($this->url('save', $report), $this->payload($report))->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->put($this->url('target', $report, 'admin'), ['video_target' => 8, 'expected_snapshot' => $this->service->signature($report->fresh())])->assertSessionHasErrors('status');
        $this->assertSame(2, $report->fresh()->video_target);
    }

    public function test_tables_keep_actions_in_last_column_and_pagination_retains_month_filter(): void
    {
        $report = $this->report();
        $video = $this->service->saveVideo($this->actor, $report, $this->videoPayload($report));
        for ($i = 0; $i < 16; $i++) {
            $this->newStudent('Siswa Tambahan '.$i);
        }
        $response = $this->actingAs($this->admin)->get(route('admin.student-progress.index', ['month' => '2026-10', 'page' => 2]))->assertOk();
        $response->assertViewHas('students', fn ($rows) => $rows->total() === 17 && $rows->currentPage() === 2 && str_contains($rows->url(1), 'month=2026-10'));
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame('Aksi', trim($xpath->query('//table[contains(@class,"dpe-progress-list-table")]/thead/tr/th[last()]')->item(0)->textContent));
        $this->assertSame(0, $xpath->query('//table[contains(@class,"dpe-progress-list-table")]/tbody/tr/td[position()!=6]/a')->length);
        $response = $this->get($this->url('edit', $report, 'admin'))->assertOk();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame('Aksi', trim($xpath->query('//table[contains(@class,"dpe-progress-video-table")]/thead/tr/th[last()]')->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//table[contains(@class,"dpe-progress-video-table")]/tbody/tr/td[last()]/div/button[@form="student-progress-form" and @name="next" and @value="video_edit:'.$video->id.'"]')->length);
        $this->assertSame(1, $xpath->query('//table[contains(@class,"dpe-progress-video-table")]/tbody/tr/td[last()]/div/button[@form="student-progress-form" and @name="next" and @value="video_remove:'.$video->id.'"]')->length);
        $this->assertSame(0, $xpath->query('//form//form')->length);
    }

    public function test_history_pagination_stays_scoped_to_report_and_preserves_all_events(): void
    {
        $report = $this->report();
        for ($target = 3; $target <= 14; $target++) {
            $this->service->setTarget($this->admin, $report, ['video_target' => $target, 'expected_snapshot' => $this->service->signature($report->fresh())]);
        }
        $other = $this->report($this->newStudent('Siswa Lain'));
        $response = $this->actingAs($this->actor)->get($this->url('edit', $report).'?history_page=2')->assertOk();
        $response->assertViewHas('history', fn ($rows) => $rows->total() === 13 && $rows->count() === 3 && $rows->every(fn ($e) => $e->report_id === $report->id));
        $this->assertDatabaseCount('monthly_progress_audits', 14);
    }

    public function test_migration_refuses_rollback_after_progress_data_is_recorded(): void
    {
        $this->report();
        $migration = require database_path('migrations/2026_10_11_070000_add_monthly_progress_support.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
