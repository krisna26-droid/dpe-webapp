<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\LearningSkill;
use App\Models\LearningVideo;
use App\Models\LessonSession;
use App\Models\MonthlyReport;
use App\Models\Program;
use App\Models\ReportStatusEvent;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\MonthlyReportContentService;
use App\Services\MonthlyReportWorkflowService;
use App\Services\StudentProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonthlyReportLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superadmin;

    private Teacher $teacher;

    private Branch $branch;

    private Program $program;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-11 18:00:00', 'Asia/Makassar'));
        $this->admin = $this->user('admin', 'Admin Laporan');
        $this->superadmin = $this->user('superadmin', 'Super Admin Laporan');
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Laporan', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'English', 'class_type' => 'branch', 'is_active' => true]);
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', 'Dipta Laporan')->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        $this->student = $this->newStudent();
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-10-01']);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-10-01']);
    }

    protected function tearDown(): void
    {
        ReportStatusEvent::flushEventListeners();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name = 'User Laporan'): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'password_hash' => 'fixture-report-password', 'full_name' => $name, 'role_code' => $role, 'is_active' => true]);
    }

    private function newStudent(string $name = 'Siswa Laporan', string $join = '2026-10-09'): Student
    {
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student')->id, 'full_name' => $name, 'began_on' => $join, 'status' => 'active', 'special_notes_internal' => 'RAHASIA PROFIL']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => $join]);

        return $student;
    }

    private function report(string $month = '2026-10', bool $complete = true, ?Student $student = null): MonthlyReport
    {
        $report = app(StudentProgressService::class)->prepare($this->admin, $student ?? $this->student, CarbonImmutable::createFromFormat('!Y-m', $month), ['teacher_id' => $this->teacher->id, 'video_target' => 4]);
        if ($complete) {
            foreach (app(StudentProgressService::class)->categories() as $code => $skill) {
                DB::table('monthly_report_skills')->insert(['report_id' => $report->id, 'skill_id' => $skill->id, 'trend' => 'Improving', 'description' => 'Kemajuan '.$code]);
            }
            $report->update(['development_summary' => 'Ringkasan publik asli', 'parent_challenges_summary' => 'Challenges publik asli', 'parent_message' => 'Pesan publik asli', 'internal_teacher_note' => 'RAHASIA BULANAN']);
        }

        return $report->fresh();
    }

    private function form(MonthlyReport $report, array $extra = []): array
    {
        return $extra + ['expected_snapshot' => app(MonthlyReportContentService::class)->signature($report->fresh())];
    }

    private function url(string $action, MonthlyReport $report, string $role = 'teacher'): string
    {
        return route($role.'.monthly-reports.'.$action, $report);
    }

    private function submit(MonthlyReport $report): void
    {
        $this->actingAs($this->teacher->user)->post($this->url('submit', $report), $this->form($report))->assertSessionHasNoErrors()->assertRedirect($this->url('show', $report));
    }

    private function video(): LearningVideo
    {
        return LearningVideo::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'video_on' => '2026-10-10', 'topic' => 'Greeting asli', 'description' => 'Latihan salam asli', 'video_url' => 'https://example.com/video-asli']);
    }

    private function progressForm(MonthlyReport $report, array $extra = []): array
    {
        return $extra + ['expected_snapshot' => app(StudentProgressService::class)->signature($report->fresh()),
            'skills' => collect(StudentProgressService::CATEGORIES)->map(fn ($label) => ['trend' => 'Stable', 'description' => 'Hasil latihan '.$label])->all(),
            'development_summary' => 'Ringkasan setelah disimpan', 'parent_challenges_summary' => 'Pendampingan latihan', 'parent_message' => 'Pesan yang disimpan', 'internal_teacher_note' => 'RAHASIA BULANAN'];
    }

    public function test_teacher_admin_revision_resubmission_approval_records_real_actors_and_order(): void
    {
        $report = $this->report();
        $this->submit($report);
        $this->actingAs($this->admin)->get($this->url('show', $report, 'admin'))->assertOk()->assertSee('Minta revisi')->assertSee('Isi perkembangan, video, dan target terkunci');
        $this->post($this->url('revision', $report, 'admin'), $this->form($report, ['comment' => 'Lengkapi contoh pronunciation.', 'actor_user_id' => $this->superadmin->id]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('revision', $report->fresh()->status);
        $this->actingAs($this->teacher->user)->get($this->url('show', $report))->assertOk()->assertSee('Lengkapi contoh pronunciation.')->assertSee('Ajukan ulang laporan');
        $this->put(route('teacher.student-progress.save', $report), $this->progressForm($report, ['next' => 'monthly_report']))->assertSessionHasNoErrors()->assertRedirect($this->url('show', $report));
        $this->submit($report);
        $this->actingAs($this->admin)->post($this->url('approve', $report, 'admin'), $this->form($report, ['approved_by_user_id' => $this->superadmin->id]))->assertSessionHasNoErrors()->assertRedirect();
        $approved = $report->fresh();
        $this->assertSame('approved', $approved->status);
        $this->assertSame($this->admin->id, $approved->approved_by_user_id);
        $this->assertCount(8, $approved->approved_snapshot['public_content']['skills']);
        $this->assertSame(['submitted', 'revision', 'submitted', 'approved'], $report->statusEvents()->orderBy('id')->pluck('to_status')->all());
        $this->assertSame([$this->teacher->user_id, $this->admin->id, $this->teacher->user_id, $this->admin->id], $report->statusEvents()->orderBy('id')->pluck('actor_user_id')->all());
        $this->get($this->url('show', $report, 'admin'))->assertViewHas('history', fn ($history) => $history->pluck('to_status')->all() === ['approved', 'submitted', 'revision', 'submitted']);
    }

    public function test_confirmation_and_cancel_do_not_change_the_database(): void
    {
        $report = $this->report();
        $this->actingAs($this->teacher->user)->get(route('teacher.monthly-reports.confirm', [$report, 'submit']))->assertOk()->assertSee('Ya, ajukan laporan')->assertSee('Batal');
        $this->get($this->url('show', $report))->assertOk();
        $this->assertSame('draft', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 0);
        $this->submit($report);
        foreach (['approve', 'revision'] as $action) {
            $this->actingAs($this->admin)->get(route('admin.monthly-reports.confirm', [$report, $action]))->assertOk()->assertSee('Batal');
        }
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 1);
    }

    public static function categories(): array
    {
        return array_map(fn ($code) => [$code], array_keys(StudentProgressService::CATEGORIES));
    }

    #[DataProvider('categories')]
    public function test_each_of_the_eight_categories_is_required(string $code): void
    {
        $report = $this->report();
        $skill = app(StudentProgressService::class)->categories()->get($code);
        DB::table('monthly_report_skills')->where('report_id', $report->id)->where('skill_id', $skill->id)->delete();
        $this->actingAs($this->teacher->user)->postJson($this->url('submit', $report), $this->form($report))->assertUnprocessable()->assertJsonValidationErrors('skills.'.$code);
        $this->assertSame('draft', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public static function badContent(): array
    {
        return [['development_summary', '   '], ['parent_challenges_summary', null], ['parent_message', ''], ['development_summary', 8001], ['internal_teacher_note', 8001], ['trend', 'A'], ['trend', 'improving'], ['description', '   '], ['description', 4001]];
    }

    #[DataProvider('badContent')]
    public function test_submission_revalidates_content_instead_of_trusting_legacy_rows(string $field, mixed $value): void
    {
        $report = $this->report();
        $value = is_int($value) ? str_repeat('x', $value) : $value;
        $error = $field;
        if (in_array($field, ['trend', 'description'], true)) {
            DB::table('monthly_report_skills')->where('report_id', $report->id)->where('skill_id', app(StudentProgressService::class)->categories()->get('speaking')->id)->update([$field => $value]);
            $error = 'skills.speaking';
        } else {
            $report->update([$field => $value]);
        }
        $this->actingAs($this->teacher->user)->postJson(route('teacher.reports.submit', $report), $this->form($report))->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public static function brokenMaster(): array
    {
        return [['inactive'], ['missing'], ['same_master']];
    }

    #[DataProvider('brokenMaster')]
    public function test_missing_inactive_or_reused_master_category_blocks_submission(string $case): void
    {
        $report = $this->report();
        $categories = app(StudentProgressService::class)->categories();
        $skill = $categories->get('speaking');
        if ($case === 'inactive') {
            $skill->update(['is_active' => false]);
        } elseif ($case === 'missing') {
            $skill->update(['code' => 'archived', 'name' => 'Archived skill']);
        } else {
            $categories->get('listening')->update(['code' => 'archived', 'name' => 'Archived skill']);
            $skill->update(['name' => 'Listening']);
        }
        $this->actingAs($this->teacher->user)->postJson($this->url('submit', $report), $this->form($report))->assertUnprocessable()->assertJsonValidationErrors('skills');
        $this->assertSame('draft', $report->fresh()->status);
    }

    public static function invalidPeriod(): array
    {
        return [['future_month'], ['before_join'], ['future_join'], ['target_unconfirmed'], ['target_negative']];
    }

    #[DataProvider('invalidPeriod')]
    public function test_future_period_invalid_join_and_unconfirmed_target_cannot_be_submitted(string $case): void
    {
        $report = $this->report($case === 'future_month' ? '2026-11' : '2026-10');
        $field = 'period';
        if ($case === 'before_join') {
            $this->student->update(['began_on' => '2026-11-01']);
        } elseif ($case === 'future_join') {
            $this->student->update(['began_on' => '2026-10-20']);
        } elseif ($case === 'target_unconfirmed') {
            $report->update(['video_target_confirmed_at' => null]);
            $field = 'video_target';
        } elseif ($case === 'target_negative') {
            $report->update(['video_target' => -1]);
            $field = 'video_target';
        }
        $this->actingAs($this->teacher->user)->postJson($this->url('submit', $report), $this->form($report))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_confirmed_zero_video_target_and_late_report_are_allowed_without_attendance(): void
    {
        $report = $this->report();
        $report->update(['video_target' => 0, 'due_on' => '2026-10-10']);
        $this->assertTrue(app(MonthlyReportContentService::class)->readiness($report->fresh())['ready']);
        $this->submit($report);
        $this->actingAs($this->admin)->post($this->url('approve', $report, 'admin'), $this->form($report))->assertSessionHasNoErrors();
        $this->assertSame(0, $report->fresh()->approved_snapshot['public_content']['video_target']);
    }

    public function test_video_shortage_is_information_and_not_a_submission_or_approval_block(): void
    {
        $report = $this->report();
        $this->actingAs($this->teacher->user)->get($this->url('show', $report))->assertOk()->assertSee('Target belum tercapai. Kekurangan video tidak menghalangi pengajuan atau persetujuan.');
        $this->submit($report);
        $this->actingAs($this->admin)->post($this->url('approve', $report, 'admin'), $this->form($report))->assertSessionHasNoErrors();
        $this->assertSame([], $report->fresh()->approved_snapshot['public_content']['videos']);
    }

    public static function versions(): array
    {
        return [['missing'], ['array'], ['bad_string']];
    }

    #[DataProvider('versions')]
    public function test_http_workflow_requires_a_well_formed_snapshot(string $case): void
    {
        $report = $this->report();
        $form = $case === 'missing' ? [] : ['expected_snapshot' => $case === 'array' ? ['invalid'] : 'stale'];
        $this->actingAs($this->teacher->user)->postJson($this->url('submit', $report), $form)->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        $this->postJson(route('teacher.reports.submit', $report), $form)->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        $this->submit($report);
        $this->actingAs($this->admin)->postJson($this->url('approve', $report, 'admin'), $form)->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        $this->postJson(route('admin.reports.approve', $report), $form)->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        $this->postJson($this->url('revision', $report, 'admin'), $form + ['comment' => 'Perbaiki'])->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        $this->postJson(route('admin.reports.revision', $report), $form + ['comment' => 'Perbaiki'])->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        $this->assertDatabaseCount('report_status_events', 1);
    }

    public static function staleChanges(): array
    {
        return [['skill'], ['message'], ['target'], ['due'], ['student'], ['teacher'], ['branch'], ['video'], ['video_teacher'], ['master']];
    }

    #[DataProvider('staleChanges')]
    public function test_submission_rejects_a_preview_after_reviewed_data_changes(string $case): void
    {
        $report = $this->report();
        $video = $this->video();
        $other = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', 'Pembuat video lain')->id, 'whatsapp_number' => '081234567891', 'is_active' => true]);
        if ($case === 'video_teacher') {
            $video->update(['teacher_id' => $other->id]);
        }
        $old = $this->form($report);
        switch ($case) {
            case 'skill': DB::table('monthly_report_skills')->where('report_id', $report->id)->update(['description' => 'Berubah']);
                break;
            case 'message': $report->update(['parent_message' => 'Berubah']);
                break;
            case 'target': $report->update(['video_target' => 6]);
                break;
            case 'due': $report->update(['due_on' => '2026-10-30']);
                break;
            case 'student': $this->student->update(['full_name' => 'Nama siswa berubah']);
                break;
            case 'teacher': $this->teacher->user->update(['full_name' => 'Nama teacher berubah']);
                break;
            case 'branch': $this->branch->update(['name' => 'Nama cabang berubah']);
                break;
            case 'video': $video->update(['video_url' => 'https://example.com/berubah']);
                break;
            case 'video_teacher': $other->user->update(['full_name' => 'Pembuat video berubah']);
                break;
            case 'master': LearningSkill::first()->update(['is_active' => false]);
                break;
        }
        $this->actingAs($this->teacher->user)->postJson($this->url('submit', $report), $old)->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        $this->assertSame('draft', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public function test_resubmitted_same_content_does_not_accept_an_old_admin_approval_form(): void
    {
        $report = $this->report();
        $this->submit($report);
        $old = $this->form($report);
        $this->actingAs($this->admin)->post($this->url('revision', $report, 'admin'), $this->form($report, ['comment' => 'Periksa kembali.']))->assertSessionHasNoErrors();
        $this->submit($report);
        $this->actingAs($this->admin)->postJson($this->url('approve', $report, 'admin'), $old)->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 3);
    }

    public function test_stale_form_retains_old_version_disables_actions_and_offers_reload(): void
    {
        $report = $this->report();
        $old = $this->form($report);
        $report->update(['parent_message' => 'Pesan berubah']);
        $confirm = route('teacher.monthly-reports.confirm', [$report, 'submit']);
        $this->actingAs($this->teacher->user)->from($confirm)->post($this->url('submit', $report), $old)->assertSessionHasErrors('expected_snapshot')->assertRedirect($confirm);
        $response = $this->get($confirm)->assertOk()->assertSee('Muat data terbaru');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//form[@data-monthly-action="submit"]//button[@type="submit" and @disabled]')->length);
        $this->assertSame($old['expected_snapshot'], $xpath->query('//input[@name="expected_snapshot"]')->item(0)->getAttribute('value'));
    }

    public static function revisionReasons(): array
    {
        return [[''], ['   '], [256], [['invalid']]];
    }

    #[DataProvider('revisionReasons')]
    public function test_revision_requires_a_reason_and_preserves_submitted_report_when_invalid(mixed $comment): void
    {
        $report = $this->report();
        $this->submit($report);
        $comment = is_int($comment) ? str_repeat('x', $comment) : $comment;
        $this->actingAs($this->admin)->postJson($this->url('revision', $report, 'admin'), $this->form($report, ['comment' => $comment]))->assertUnprocessable()->assertJsonValidationErrors('comment');
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 1);
    }

    public function test_old_incomplete_submission_can_be_revised_but_cannot_be_approved(): void
    {
        $report = $this->report(complete: false);
        $report->update(['status' => 'submitted']);
        $this->actingAs($this->admin)->postJson($this->url('approve', $report, 'admin'), $this->form($report))->assertUnprocessable()->assertJsonValidationErrors(['skills', 'parent_message']);
        $this->post($this->url('revision', $report, 'admin'), $this->form($report, ['comment' => 'Lengkapi seluruh kategori.']))->assertSessionHasNoErrors();
        $this->assertSame('revision', $report->fresh()->status);
        $this->assertNull($report->fresh()->approved_snapshot);
    }

    public static function unauthorizedActors(): array
    {
        return [['other_teacher'], ['other_admin'], ['expired_admin'], ['future_admin'], ['inactive_user'], ['inactive_profile'], ['student']];
    }

    #[DataProvider('unauthorizedActors')]
    public function test_unrelated_and_inactive_accounts_cannot_read_or_mutate_private_reports(string $case): void
    {
        $report = $this->report();
        $this->submit($report);
        $actor = match ($case) {
            'inactive_user', 'inactive_profile' => $this->teacher->user,
            'expired_admin', 'future_admin' => $this->admin,
            'student' => $this->student->portalUser,
            default => $this->user($case === 'other_admin' ? 'admin' : 'teacher'),
        };
        if ($case === 'other_teacher') {
            Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $actor->id, 'whatsapp_number' => '081234567892', 'is_active' => true]);
        } elseif ($case === 'expired_admin') {
            BranchAdminAssignment::where('admin_user_id', $actor->id)->update(['ends_on' => '2026-10-10']);
        } elseif ($case === 'future_admin') {
            BranchAdminAssignment::where('admin_user_id', $actor->id)->update(['starts_on' => '2026-10-12']);
        } elseif ($case === 'inactive_user') {
            $actor->update(['is_active' => false]);
        } elseif ($case === 'inactive_profile') {
            $this->teacher->update(['is_active' => false]);
        }
        $role = $actor->role_code === 'admin' ? 'admin' : 'teacher';
        $this->actingAs($actor)->get($this->url('show', $report, $role))->assertForbidden()->assertDontSee('RAHASIA BULANAN');
        $action = $role === 'admin' ? 'revision' : 'submit';
        $this->postJson($this->url($action, $report, $role), $this->form($report, ['comment' => 'Perbaiki']))->assertForbidden();
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 1);
    }

    public function test_guest_is_redirected_and_superadmin_can_review_any_branch(): void
    {
        $report = $this->report();
        $this->get($this->url('show', $report))->assertRedirect(route('login'));
        $this->submit($report);
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->delete();
        $this->actingAs($this->superadmin)->get($this->url('show', $report, 'superadmin'))->assertOk()->assertSee('Setujui laporan');
        $this->post($this->url('approve', $report, 'superadmin'), $this->form($report))->assertSessionHasNoErrors();
        $this->assertSame($this->superadmin->id, $report->fresh()->approved_by_user_id);
    }

    public function test_duplicate_posts_and_illegal_transitions_never_duplicate_events(): void
    {
        $report = $this->report();
        $old = $this->form($report);
        $this->submit($report);
        $this->actingAs($this->teacher->user)->postJson($this->url('submit', $report), $old)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->actingAs($this->admin)->post($this->url('approve', $report, 'admin'), $this->form($report))->assertSessionHasNoErrors();
        foreach (['approve', 'revision'] as $action) {
            $this->postJson($this->url($action, $report, 'admin'), $this->form($report, ['comment' => 'Perbaiki']))->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->assertDatabaseCount('report_status_events', 2);
        $this->assertSame('approved', $report->fresh()->status);
    }

    public function test_submitted_and_approved_progress_video_and_target_are_locked(): void
    {
        $report = $this->report();
        $video = $this->video();
        $this->submit($report);
        foreach (['submitted', 'approved'] as $status) {
            if ($status === 'approved') {
                $this->actingAs($this->admin)->post($this->url('approve', $report, 'admin'), $this->form($report))->assertSessionHasNoErrors();
            }
            $this->actingAs($this->teacher->user)->putJson(route('teacher.student-progress.save', $report), $this->progressForm($report))->assertUnprocessable()->assertJsonValidationErrors('status');
            $this->deleteJson(route('teacher.student-progress.videos.delete', [$report, $video]), ['expected_snapshot' => app(StudentProgressService::class)->signature($report->fresh())])->assertUnprocessable()->assertJsonValidationErrors('status');
            $this->actingAs($this->admin)->putJson(route('admin.student-progress.target', $report), ['video_target' => 0, 'expected_snapshot' => app(StudentProgressService::class)->signature($report->fresh())])->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->assertSame('Ringkasan publik asli', $report->fresh()->development_summary);
        $this->assertDatabaseHas('learning_videos', ['id' => $video->id]);
        $this->assertSame(4, $report->fresh()->video_target);
    }

    public function test_approval_freezes_reviewed_content_and_separates_internal_notes(): void
    {
        $report = $this->report();
        $video = $this->video();
        $this->submit($report);
        $this->actingAs($this->admin)->post($this->url('approve', $report, 'admin'), $this->form($report))->assertSessionHasNoErrors();
        $snapshot = $report->fresh()->approved_snapshot;
        $this->assertSame(2, $snapshot['snapshot_version']);
        $this->assertArrayNotHasKey('internal_teacher_note', $snapshot['public_content']);
        $this->assertStringNotContainsString('RAHASIA', json_encode($snapshot['public_content']));
        $report->update(['development_summary' => 'Ringkasan diubah setelah approval', 'internal_teacher_note' => 'INTERNAL DIUBAH']);
        $this->student->update(['full_name' => 'NAMA SISWA DIUBAH']);
        $this->branch->update(['name' => 'CABANG DIUBAH']);
        $this->teacher->user->update(['full_name' => 'TEACHER DIUBAH']);
        DB::table('monthly_report_skills')->where('report_id', $report->id)->update(['description' => 'PENILAIAN DIUBAH']);
        $video->update(['video_url' => 'https://example.com/video-diubah', 'topic' => 'VIDEO DIUBAH']);
        $response = $this->get($this->url('show', $report, 'admin'))->assertOk()->assertSee('Siswa Laporan')->assertSee('Cabang Laporan')->assertSee('Dipta Laporan')->assertSee('https://example.com/video-asli')->assertSee('Ringkasan publik asli')->assertSee('Kemajuan speaking')->assertSee('RAHASIA BULANAN')->assertDontSee('Ringkasan diubah setelah approval')->assertDontSee('PENILAIAN DIUBAH')->assertDontSee('INTERNAL DIUBAH')->assertDontSee('https://example.com/video-diubah');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $public = $xpath->query('//*[@data-monthly-public]')->item(0);
        $this->assertStringNotContainsString('RAHASIA', $public->textContent);
        $this->assertSame(8, $xpath->query('//*[@data-monthly-public]//section[contains(@class,"dpe-monthly-skill")]')->length);
        $this->assertSame(0, $xpath->query('//form[@data-monthly-action]')->length);
    }

    public static function oldSnapshots(): array
    {
        return [['old'], ['missing'], ['unsafe_video'], ['malformed_video']];
    }

    #[DataProvider('oldSnapshots')]
    public function test_legacy_approved_records_render_readonly_and_never_expose_unsafe_links(string $case): void
    {
        $report = $this->report();
        $snapshot = ['development_summary' => 'Ancienne synthese', 'parent_challenges_summary' => 'Latihan', 'parent_message' => 'Pesan', 'skills' => [['skill_id' => LearningSkill::first()->id, 'trend' => 'Stable', 'description' => 'Deskripsi lama']], 'internal_teacher_note' => 'RAHASIA LAMA'];
        if ($case === 'unsafe_video' || $case === 'malformed_video') {
            $snapshot['videos'] = [['topic' => 'Video lama', 'video_url' => $case === 'unsafe_video' ? 'javascript:alert(1)' : ['bad']]];
        }
        $report->update(['status' => 'approved', 'approved_snapshot' => $case === 'missing' ? null : $snapshot]);
        $response = $this->actingAs($this->admin)->get($this->url('show', $report, 'admin'))->assertOk()->assertDontSee('href="javascript:', false)->assertDontSee('Ajukan laporan')->assertDontSee('Setujui laporan');
        if ($case === 'old') {
            $response->assertSee('Belum tersimpan')->assertSee('Ancienne synthese');
        } elseif ($case === 'missing') {
            $response->assertSee('Salinan persetujuan lama belum tersedia.');
        } else {
            $response->assertSee('Tautan tidak tersedia');
        }
    }

    public function test_save_and_review_redirects_only_after_valid_save_and_does_not_submit(): void
    {
        $report = $this->report(complete: false);
        foreach (['teacher' => $this->teacher->user, 'admin' => $this->admin, 'superadmin' => $this->superadmin] as $role => $actor) {
            $this->actingAs($actor)->get(route($role.'.student-progress.edit', $report))->assertOk()->assertSee('Simpan &amp; periksa laporan', false);
            $this->put(route($role.'.student-progress.save', $report), $this->progressForm($report, ['next' => 'monthly_report']))->assertSessionHasNoErrors()->assertRedirect($this->url('show', $report, $role));
        }
        $this->assertSame('draft', $report->fresh()->status);
        $this->assertDatabaseCount('report_status_events', 0);
        $bad = $this->progressForm($report, ['next' => 'monthly_report', 'parent_message' => str_repeat('x', 8001)]);
        $edit = route('superadmin.student-progress.edit', $report);
        $this->from($edit)->put(route('superadmin.student-progress.save', $report), $bad)->assertSessionHasErrors('parent_message')->assertRedirect($edit);
        $this->assertSame('Pesan yang disimpan', $report->fresh()->parent_message);
    }

    public function test_teacher_list_is_owned_only_and_admin_default_queue_is_submitted_across_months(): void
    {
        $own = $this->report();
        $otherStudent = $this->newStudent('Siswa teacher lain');
        $other = $this->report(student: $otherStudent);
        $otherTeacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher')->id, 'whatsapp_number' => '081234567893', 'is_active' => true]);
        $other->update(['teacher_id' => $otherTeacher->id]);
        $this->actingAs($this->teacher->user)->get(route('teacher.monthly-reports.index'))->assertOk()->assertSee('Siswa Laporan')->assertDontSee('Siswa teacher lain');
        $this->get(route('teacher.monthly-reports.index', ['q' => 'Tidak ditemukan']))->assertViewHas('reports', fn ($reports) => $reports->isEmpty());
        $this->submit($own);
        Carbon::setTestNow(Carbon::parse('2026-11-11 18:00:00', 'Asia/Makassar'));
        $next = $this->report('2026-11');
        $this->submit($next);
        $this->actingAs($this->admin)->get(route('admin.monthly-reports.index'))->assertOk()->assertViewHas('reports', fn ($reports) => $reports->count() === 2 && $reports->every(fn ($r) => $r->status === 'submitted'));
        $this->get(route('admin.monthly-reports.index', ['month' => '2026-10', 'status' => 'all', 'branch_id' => $this->branch->id]))->assertViewHas('reports', fn ($reports) => $reports->count() === 2);
        $this->get(route('admin.monthly-reports.index', ['month' => '2026-13']))->assertSessionHasErrors('month');
    }

    public function test_assigned_student_without_period_gets_preparation_guidance_and_no_automatic_report(): void
    {
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'starts_on' => '2026-10-09', 'is_report_owner' => true]);
        $this->actingAs($this->teacher->user)->get(route('teacher.monthly-reports.index'))->assertOk()->assertSee('Admin')->assertSee('periode')->assertSee('Buka Perkembangan &amp; Pesan', false);
        $this->get(route('teacher.student-progress.index'))->assertOk()->assertSee($this->student->full_name)->assertSee('Menunggu Admin menyiapkan periode');
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_due_date_is_inclusive_in_branch_timezone(): void
    {
        $report = $this->report();
        $report->update(['due_on' => '2026-10-11']);
        $this->actingAs($this->teacher->user)->get(route('teacher.monthly-reports.index'))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->first()['overdue'] === false);
        $report->update(['due_on' => '2026-10-10']);
        $this->get(route('teacher.monthly-reports.index'))->assertViewHas('rows', fn ($rows) => $rows->first()['overdue'] === true);
    }

    public function test_admin_approval_and_revision_reject_changed_reviewed_content(): void
    {
        $report = $this->report();
        $this->submit($report);
        $old = $this->form($report, ['comment' => 'Perbaiki latihan.']);
        $report->update(['parent_message' => 'Koreksi setelah form dibuka']);
        foreach (['approve', 'revision'] as $action) {
            $this->actingAs($this->admin)->postJson($this->url($action, $report, 'admin'), $old)->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        }
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertNull($report->fresh()->approved_snapshot);
        $this->assertDatabaseCount('report_status_events', 1);
    }

    public function test_confirmation_actions_follow_role_and_current_status(): void
    {
        $report = $this->report();
        $this->actingAs($this->teacher->user)->get(route('teacher.monthly-reports.confirm', [$report, 'approve']))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.monthly-reports.confirm', [$report, 'submit']))->assertForbidden();
        $this->get(route('admin.monthly-reports.confirm', [$report, 'approve']))->assertRedirect($this->url('show', $report, 'admin'))->assertSessionHasErrors('status');
        $this->submit($report);
        $this->actingAs($this->teacher->user)->get(route('teacher.monthly-reports.confirm', [$report, 'submit']))->assertRedirect($this->url('show', $report))->assertSessionHasErrors('status');
        $this->actingAs($this->superadmin)->get($this->url('show', $report))->assertForbidden();
        $this->assertDatabaseCount('report_status_events', 1);
    }

    public function test_incomplete_confirmation_disables_submit_and_links_to_progress_form(): void
    {
        $report = $this->report(complete: false);
        $response = $this->actingAs($this->teacher->user)->get(route('teacher.monthly-reports.confirm', [$report, 'submit']))->assertOk()->assertSee('Speaking: lengkapi')->assertSee('Teacher’s Message wajib diisi')->assertSee(route('teacher.student-progress.edit', $report), false);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//form[@data-monthly-action="submit"]//button[@type="submit" and @disabled]')->length);
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public function test_list_paginates_and_keeps_actions_in_the_last_column(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->report(complete: false, student: $this->newStudent('Peserta '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)));
        }
        $response = $this->actingAs($this->teacher->user)->get(route('teacher.monthly-reports.index'))->assertOk()->assertViewHas('reports', fn ($reports) => $reports->total() === 16 && $reports->count() === 15);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame('Aksi', trim($xpath->query('//table/thead/tr/th[last()]')->item(0)->textContent));
        $this->assertSame(15, $xpath->query('//table/tbody/tr/td[last()]/a')->length);
        $this->get(route('teacher.monthly-reports.index', ['page' => 2]))->assertViewHas('reports', fn ($reports) => $reports->count() === 1);
        $this->get(route('teacher.monthly-reports.index', ['q' => 'Peserta 15']))->assertViewHas('reports', fn ($reports) => $reports->count() === 1);
    }

    public function test_admin_filters_and_branch_choices_do_not_leak_another_branch(): void
    {
        $own = $this->report();
        $other = $this->report(student: $this->newStudent('Siswa cabang rahasia'));
        $branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang lain rahasia', 'timezone_name' => 'Asia/Jakarta', 'payment_recap_day' => 20, 'is_active' => true]);
        $other->update(['branch_id' => $branch->id, 'status' => 'submitted']);
        $own->update(['status' => 'submitted']);
        $this->actingAs($this->admin)->get(route('admin.monthly-reports.index'))->assertOk()->assertSee('Siswa Laporan')->assertDontSee('Siswa cabang rahasia')->assertDontSee('Cabang lain rahasia');
        $this->get(route('admin.monthly-reports.index', ['branch_id' => $branch->id, 'status' => 'all']))->assertViewHas('reports', fn ($reports) => $reports->isEmpty())->assertDontSee('Cabang lain rahasia');
        $this->get($this->url('show', $other, 'admin'))->assertForbidden();
        $this->postJson($this->url('approve', $other, 'admin'), $this->form($other))->assertForbidden();
    }

    public function test_public_text_and_revision_reason_are_escaped(): void
    {
        $report = $this->report();
        $report->update(['parent_message' => '<script>alert("report")</script>']);
        $this->actingAs($this->teacher->user)->get($this->url('show', $report))->assertOk()->assertSee('&lt;script&gt;alert(&quot;report&quot;)&lt;/script&gt;', false)->assertDontSee('<script>alert("report")</script>', false);
        $this->submit($report);
        $this->actingAs($this->admin)->post($this->url('revision', $report, 'admin'), $this->form($report, ['comment' => '<img src=x onerror=alert(1)>']))->assertSessionHasNoErrors();
        $this->actingAs($this->teacher->user)->get($this->url('show', $report))->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    private function lesson(array $attributes = [], ?Student $student = null): LessonSession
    {
        $session = LessonSession::create($attributes + ['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'teacher_id' => $this->teacher->id, 'teaching_location' => 'branch', 'planned_start_at' => '2026-10-10 09:00:00', 'planned_end_at' => '2026-10-10 10:00:00', 'status' => 'scheduled']);
        SessionStudent::create(['session_id' => $session->id, 'student_id' => ($student ?? $this->student)->id, 'attendance_status' => 'present', 'individual_learning_note' => 'RAHASIA SESI']);

        return $session;
    }

    public function test_attendance_summary_uses_actual_month_and_excludes_ineligible_sessions(): void
    {
        $report = $this->report();
        $this->lesson();
        $this->lesson(['planned_start_at' => '2026-09-30 09:00:00', 'planned_end_at' => '2026-09-30 10:00:00', 'actual_start_at' => '2026-10-11 09:00:00']);
        $this->lesson(['status' => 'cancelled']);
        $this->lesson(['planned_start_at' => '2026-10-12 09:00:00', 'planned_end_at' => '2026-10-12 10:00:00']);
        $this->lesson(['planned_start_at' => '2026-10-08 09:00:00', 'planned_end_at' => '2026-10-08 10:00:00']);
        $this->lesson(student: $this->newStudent('Siswa lain'));
        $old = $this->lesson();
        $this->lesson(['rescheduled_from_session_id' => $old->id]);
        $counts = app(MonthlyReportContentService::class)->attendanceSummary($report);
        $this->assertSame(3, $counts['meeting_count']);
        $this->assertSame(3, $counts['present']);
        $this->assertSame(1, $counts['rescheduled']);
        $this->actingAs($this->teacher->user)->get($this->url('show', $report))->assertOk()->assertDontSee('RAHASIA SESI')->assertDontSee('Siswa lain');
    }

    public static function rollbackActions(): array
    {
        return [['submit'], ['approve'], ['revision']];
    }

    #[DataProvider('rollbackActions')]
    public function test_failed_history_write_rolls_back_status_timestamps_and_snapshot(string $action): void
    {
        $report = $this->report();
        if ($action !== 'submit') {
            $this->submit($report);
        }
        $before = $report->fresh()->getAttributes();
        $events = ReportStatusEvent::count();
        ReportStatusEvent::creating(fn () => throw new \RuntimeException('Simulated status history failure'));
        try {
            $workflow = app(MonthlyReportWorkflowService::class);
            if ($action === 'revision') {
                $workflow->requestRevision($report, $this->admin, 'Perbaiki', $this->form($report));
            } else {
                $workflow->$action($report, $action === 'submit' ? $this->teacher->user : $this->admin, $this->form($report));
            }
            $this->fail('History failure should abort the transaction.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated status history failure', $e->getMessage());
        }
        $this->assertSame($before, $report->fresh()->getAttributes());
        $this->assertSame($events, ReportStatusEvent::count());
    }
}
