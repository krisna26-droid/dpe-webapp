<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\FileAsset;
use App\Models\MonthlyCharge;
use App\Models\MonthlyReport;
use App\Models\PaymentProof;
use App\Models\QuarterlyReport;
use App\Models\QuarterlyReportEvent;
use App\Models\QuarterlyReportFile;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\Teacher;
use App\Models\User;
use App\Services\MonthlyReportWorkflowService;
use App\Services\QuarterlyReportContentService;
use App\Services\QuarterlyReportPdfRenderer;
use App\Services\QuarterlyReportPdfService;
use App\Services\QuarterlyReportWorkflowService;
use App\Services\ReportTemplateService;
use App\Services\StudentProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuarterlyReportLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superadmin;

    private Teacher $teacher;

    private Branch $branch;

    private Student $student;

    private ReportCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-11 18:00:00', 'Asia/Makassar'));
        Storage::fake('local');
        $this->admin = $this->user('admin', 'Admin Rapor');
        $this->superadmin = $this->user('superadmin', 'Super Admin Rapor');
        $this->teacher = $this->newTeacher('Dipta Rapor');
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Rapor', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student')->id, 'full_name' => 'Siswa Contoh Rapor', 'began_on' => '2026-07-09', 'status' => 'active', 'special_notes_internal' => 'RAHASIA PROFIL']);
        $this->cycle = ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'cycle_number' => 1, 'start_month' => '2026-07-01', 'end_month' => '2026-09-01']);
        foreach (['2026-07-01', '2026-08-01', '2026-09-01'] as $month) {
            $monthly = MonthlyReport::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'cycle_id' => $this->cycle->id,
                'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'report_month' => $month, 'due_on' => Carbon::parse($month)->endOfMonth()->toDateString(), 'status' => 'draft',
                'video_target' => 0, 'video_target_confirmed_at' => now(), 'video_target_confirmed_by_user_id' => $this->admin->id,
                'development_summary' => 'Publik bulanan', 'parent_challenges_summary' => 'CHALLENGES BULANAN', 'parent_message' => 'TEACHER MESSAGE BULANAN', 'internal_teacher_note' => 'RAHASIA BULANAN']);
            foreach (app(StudentProgressService::class)->categories() as $code => $skill) {
                DB::table('monthly_report_skills')->insert(['report_id' => $monthly->id, 'skill_id' => $skill->id, 'trend' => 'Improving', 'description' => 'BULANAN '.$code]);
            }
        }
    }

    protected function tearDown(): void
    {
        QuarterlyReportEvent::flushEventListeners();
        FileAsset::flushEventListeners();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name = 'User Rapor'): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'password_hash' => 'fixture-quarterly-password', 'full_name' => $name, 'role_code' => $role, 'is_active' => true]);
    }

    private function newTeacher(string $name): Teacher
    {
        return Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', $name)->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
    }

    private function prepare(bool $complete = true): QuarterlyReport
    {
        $report = app(QuarterlyReportWorkflowService::class)->prepare($this->admin, $this->cycle, ['teacher_id' => $this->teacher->id,
            'expected_snapshot' => app(QuarterlyReportContentService::class)->preparationSignature($this->cycle->fresh())]);

        return $complete ? app(QuarterlyReportWorkflowService::class)->save($this->teacher->user, $report, $this->form($report, $this->fields())) : $report;
    }

    private function fields(): array
    {
        return ['skills' => collect(QuarterlyReportContentService::SKILLS)->map(fn ($label) => ['description' => 'Deskripsi manual '.$label.' — progrès siswa baik.', 'score' => 'B'])->all(),
            'parent_note' => 'NOTE PUBLIK FINAL: terus berlatih di rumah.'];
    }

    private function form(QuarterlyReport $report, array $extra = []): array
    {
        return $extra + ['expected_snapshot' => app(QuarterlyReportContentService::class)->signature($report->fresh())];
    }

    private function url(string $action, QuarterlyReport $report, string $role = 'teacher'): string
    {
        return route($role.'.quarterly-reports.'.$action, $report);
    }

    private function approve(QuarterlyReport $report, bool $monthly = true): QuarterlyReport
    {
        $workflow = app(QuarterlyReportWorkflowService::class);
        $report = $workflow->transition($this->teacher->user, $report, 'submit', $this->form($report));
        $report = $workflow->transition($this->admin, $report, 'approve', $this->form($report));
        if ($monthly) {
            $this->approveMonths();
        }

        return $report->fresh();
    }

    private function approveMonths(): void
    {
        foreach ($this->cycle->monthlyReports()->get() as $monthly) {
            $workflow = app(MonthlyReportWorkflowService::class);
            if ($monthly->status === 'draft') {
                $monthly = $workflow->submit($monthly, $monthly->teacher->user);
            }
            if ($monthly->status === 'submitted') {
                $workflow->approve($monthly, $this->admin);
            }
        }
    }

    private function pay(string $month, string $status = 'approved'): MonthlyCharge
    {
        $charge = MonthlyCharge::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'charge_month' => $month, 'amount_idr' => 500000]);
        $asset = FileAsset::create(['id' => (string) Str::uuid(), 'storage_key' => 'payments/'.Str::uuid().'.png', 'original_name' => 'bukti.png', 'mime_type' => 'image/png', 'size_bytes' => 10]);
        PaymentProof::create(['id' => (string) Str::uuid(), 'charge_id' => $charge->id, 'image_file_id' => $asset->id, 'uploaded_by_user_id' => $this->admin->id,
            'status' => $status, 'reviewed_by_user_id' => $status === 'approved' ? $this->superadmin->id : null, 'reviewed_at' => $status === 'approved' ? now() : null]);

        return $charge;
    }

    public function test_teacher_and_admin_pages_expose_correct_actions_and_all_eight_manual_skills(): void
    {
        $this->actingAs($this->teacher->user)->get(route('teacher.quarterly-reports.index'))->assertOk()->assertSee('Siswa Contoh Rapor')->assertSee('Siapkan rapor');
        $this->get(route('teacher.quarterly-reports.prepare', $this->cycle))->assertOk()->assertSee('Dipta Rapor')->assertSee('Siapkan draf rapor');
        $report = $this->prepare();
        $this->get($this->url('edit', $report))->assertOk()->assertSee('skills[speaking][score]', false)->assertSee('skills[writing][description]', false)->assertSee('Simpan &amp; periksa rapor', false);
        $this->get($this->url('show', $report))->assertOk()->assertSeeInOrder(['Speaking', 'Listening', 'Reading', 'Writing', 'Vocabulary', 'Grammar', 'Pronunciation', 'Confidence/Participation', 'NOTE PUBLIK FINAL']);
        $this->actingAs($this->admin)->get($this->url('edit', $report, 'admin'))->assertOk()->assertSee('Teacher pemilik')->assertDontSee('name="skills[', false);
        $this->get($this->url('show', $report, 'admin'))->assertOk()->assertDontSee('Setujui rapor final');
    }

    public function test_confirmation_cancel_preview_are_read_only_and_approval_is_independent(): void
    {
        $report = $this->prepare();
        $before = $report->getAttributes();
        $events = QuarterlyReportEvent::count();
        $this->actingAs($this->teacher->user)->get(route('teacher.quarterly-reports.confirm', [$report, 'submit']))->assertOk()->assertSee('Ya, ajukan rapor final')->assertSee('Batal');
        $this->get($this->url('preview', $report))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($before, $report->fresh()->getAttributes());
        $this->assertSame($events, QuarterlyReportEvent::count());
        $this->assertDatabaseCount('file_assets', 0);
        $this->assertDatabaseCount('quarterly_report_files', 0);
        $approved = $this->approve($report, false);
        $this->assertSame('approved', $approved->status);
        $this->assertSame($this->admin->id, $approved->approved_by_user_id);
        $this->assertSame(['draft'], $this->cycle->monthlyReports()->distinct()->pluck('status')->all());
        $this->actingAs($this->teacher->user)->post($this->url('generate', $approved), $this->form($approved))->assertSessionHasErrors('pdf');
        $this->assertDatabaseCount('quarterly_report_files', 0);
    }

    public function test_revision_resubmission_and_real_actor_history(): void
    {
        $report = $this->prepare();
        $this->actingAs($this->teacher->user)->post($this->url('submit', $report), $this->form($report))->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($this->admin)->get(route('admin.quarterly-reports.confirm', [$report, 'revision']))->assertOk()->assertSee('Alasan');
        $this->post($this->url('revision', $report, 'admin'), $this->form($report, ['comment' => 'Tambahkan contoh writing.', 'actor_user_id' => $this->superadmin->id]))->assertSessionHasNoErrors();
        $this->assertSame('revision', $report->fresh()->status);
        $this->actingAs($this->teacher->user)->put($this->url('save', $report), $this->form($report, $this->fields() + ['next' => 'review']))->assertSessionHasNoErrors()->assertRedirect($this->url('show', $report));
        $this->post($this->url('submit', $report), $this->form($report))->assertSessionHasNoErrors();
        $this->actingAs($this->superadmin)->post($this->url('approve', $report, 'superadmin'), $this->form($report))->assertSessionHasNoErrors();
        $events = $report->events()->whereIn('event_type', ['submit', 'revision', 'approve'])->orderBy('id')->get();
        $this->assertSame(['submitted', 'revision', 'submitted', 'approved'], $events->pluck('to_status')->all());
        $this->assertSame([$this->teacher->user_id, $this->admin->id, $this->teacher->user_id, $this->superadmin->id], $events->pluck('actor_user_id')->all());
        $this->assertSame($this->superadmin->id, $report->fresh()->approved_by_user_id);
    }

    public function test_missing_months_prevent_preparation_and_do_not_hide_teacher_form_context(): void
    {
        DB::table('monthly_report_skills')->where('report_id', $this->cycle->monthlyReports()->whereDate('report_month', '2026-08-01')->value('id'))->delete();
        $this->cycle->monthlyReports()->whereDate('report_month', '2026-08-01')->delete();
        $this->actingAs($this->admin)->get(route('admin.quarterly-reports.prepare', $this->cycle))->assertOk()->assertSee('Teacher pemilik rapor final')->assertSee('ketiga periode bulanan');
        $this->post(route('admin.quarterly-reports.store', $this->cycle), ['teacher_id' => $this->teacher->id, 'expected_snapshot' => app(QuarterlyReportContentService::class)->preparationSignature($this->cycle->fresh())])->assertSessionHasErrors('monthly_periods');
        $this->assertDatabaseCount('quarterly_reports', 0);
    }

    public function test_actual_monthly_owners_are_available_without_extra_branch_assignment(): void
    {
        $other = $this->newTeacher('Teacher Kedua');
        $this->cycle->monthlyReports()->whereDate('report_month', '2026-09-01')->update(['teacher_id' => $other->id]);
        $this->assertDatabaseCount('teacher_branch_assignments', 0);
        $this->actingAs($this->admin)->get(route('admin.quarterly-reports.prepare', $this->cycle))->assertOk()->assertSee('Dipta Rapor')->assertSee('Teacher Kedua');
        $this->post(route('admin.quarterly-reports.store', $this->cycle), ['teacher_id' => $other->id, 'expected_snapshot' => app(QuarterlyReportContentService::class)->preparationSignature($this->cycle->fresh())])->assertSessionHasNoErrors();
        $report = QuarterlyReport::firstOrFail();
        $this->assertSame($other->id, $report->teacher_id);
        $this->actingAs($this->teacher->user)->get($this->url('show', $report))->assertForbidden();
        $this->get(route('teacher.quarterly-reports.index'))->assertOk()->assertSee('Dikelola Teacher pemilik');
        $this->actingAs($other->user)->get($this->url('edit', $report))->assertOk();
    }

    public function test_preparation_is_idempotent_and_never_reassigns_an_existing_owner(): void
    {
        $report = $this->prepare(false);
        $this->actingAs($this->admin)->post(route('admin.quarterly-reports.store', $this->cycle), ['teacher_id' => (string) Str::uuid(), 'expected_snapshot' => 'old'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('quarterly_reports', 1);
        $this->assertDatabaseCount('quarterly_report_events', 1);
        $this->assertSame($this->teacher->id, $report->fresh()->teacher_id);
    }

    #[DataProvider('badCycles')]
    public function test_invalid_calendar_cycles_are_blocked(string $start, string $end): void
    {
        $this->cycle->update(['start_month' => $start, 'end_month' => $end]);
        $this->actingAs($this->admin)->post(route('admin.quarterly-reports.store', $this->cycle), ['teacher_id' => $this->teacher->id, 'expected_snapshot' => app(QuarterlyReportContentService::class)->preparationSignature($this->cycle->fresh())])->assertSessionHasErrors('period');
        $this->assertDatabaseCount('quarterly_reports', 0);
    }

    public static function badCycles(): array
    {
        return [['2026-07-09', '2026-09-01'], ['2026-07-01', '2026-10-01'], ['2026-08-01', '2026-10-01'], ['2026-04-01', '2026-06-01']];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_skill_or_note_data_cannot_partially_overwrite_the_draft(string $field, mixed $value): void
    {
        $report = $this->prepare();
        $before = $report->getAttributes();
        $events = QuarterlyReportEvent::count();
        $data = $this->fields();
        data_set($data, $field, $value);
        $this->actingAs($this->teacher->user)->put($this->url('save', $report), $this->form($report, $data))->assertSessionHasErrors();
        $this->assertSame($before, $report->fresh()->getAttributes());
        $this->assertSame($events, QuarterlyReportEvent::count());
    }

    public static function invalidFields(): array
    {
        $cases = [];
        foreach (array_keys(QuarterlyReportContentService::SKILLS) as $code) {
            $cases[$code.' invalid score'] = ['skills.'.$code.'.score', 'Improving'];
            $cases[$code.' lowercase'] = ['skills.'.$code.'.score', 'a'];
            $cases[$code.' partial description'] = ['skills.'.$code.'.description', ''];
            $cases[$code.' partial score'] = ['skills.'.$code.'.score', null];
            $cases[$code.' too long'] = ['skills.'.$code.'.description', str_repeat('x', 4001)];
            $cases[$code.' not text'] = ['skills.'.$code.'.description', ['x']];
        }
        $cases['ninth skill'] = ['skills.unknown', ['description' => 'Extra', 'score' => 'A']];
        $cases['note too long'] = ['parent_note', str_repeat('n', 8001)];
        $cases['note not text'] = ['parent_note', ['secret']];

        return $cases;
    }

    public function test_empty_pairs_can_be_saved_as_draft_but_cannot_be_submitted(): void
    {
        $report = $this->prepare(false);
        $data = ['skills' => collect(QuarterlyReportContentService::SKILLS)->map(fn () => ['description' => null, 'score' => null])->all(), 'parent_note' => null];
        $this->actingAs($this->teacher->user)->put($this->url('save', $report), $this->form($report, $data))->assertSessionHasNoErrors();
        $this->post($this->url('submit', $report), $this->form($report))->assertSessionHasErrors([...array_map(fn ($code) => 'skills.'.$code, array_keys(StudentProgressService::CATEGORIES)), 'parent_note']);
        $this->assertSame('draft', $report->fresh()->status);
    }

    public function test_future_third_month_allows_draft_preview_but_blocks_submission(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10', 'Asia/Makassar'));
        $report = $this->prepare();
        $this->actingAs($this->teacher->user)->get($this->url('preview', $report))->assertOk();
        $this->post($this->url('submit', $report), $this->form($report))->assertSessionHasErrors('period_time');
        $this->assertSame('draft', $report->fresh()->status);
    }

    #[DataProvider('staleSources')]
    public function test_changed_identity_owner_template_and_monthly_source_reject_old_forms(string $change): void
    {
        $report = $this->prepare();
        $data = $this->form($report, $this->fields());
        match ($change) {
            'student' => $this->student->update(['full_name' => 'Nama diubah']),
            'join' => $this->student->update(['began_on' => '2026-07-10']),
            'teacher' => $this->teacher->user->update(['full_name' => 'Teacher diubah']),
            'branch' => $this->branch->update(['timezone_name' => 'Asia/Jakarta']),
            'monthly' => $this->cycle->monthlyReports()->first()->update(['status' => 'submitted']),
            'template' => app(ReportTemplateService::class)->save($this->superadmin, ['expected_snapshot' => app(ReportTemplateService::class)->signature(), ...array_replace(config('report-pdf.defaults'), ['manager_name' => 'Manager Baru'])]),
        };
        $this->actingAs($this->teacher->user)->put($this->url('save', $report), $data)->assertSessionHasErrors('expected_snapshot');
        $this->assertSame('Deskripsi manual Speaking — progrès siswa baik.', $report->fresh()->skills['speaking']['description']);
    }

    public static function staleSources(): array
    {
        return array_map(fn ($x) => [$x], ['student', 'join', 'teacher', 'branch', 'monthly', 'template']);
    }

    #[DataProvider('mutations')]
    public function test_every_http_mutation_requires_a_snapshot(string $action, string $role, string $status): void
    {
        $report = $this->prepare();
        if ($status === 'submitted') {
            app(QuarterlyReportWorkflowService::class)->transition($this->teacher->user, $report, 'submit', $this->form($report));
        }
        if ($status === 'approved') {
            $report = $this->approve($report);
        }
        $actor = $role === 'teacher' ? $this->teacher->user : $this->admin;
        $this->actingAs($actor);
        $data = $action === 'save' ? $this->fields() : ['comment' => 'Alasan'];
        ($action === 'save' ? $this->put($this->url($action, $report, $role), $data) : $this->post($this->url($action, $report, $role), $data))->assertSessionHasErrors('expected_snapshot');
        $this->assertSame($status, $report->fresh()->status);
    }

    public static function mutations(): array
    {
        return [['save', 'teacher', 'draft'], ['submit', 'teacher', 'draft'], ['approve', 'admin', 'submitted'], ['revision', 'admin', 'submitted'], ['reopen', 'teacher', 'approved'], ['generate', 'teacher', 'approved'], ['regenerate', 'admin', 'approved']];
    }

    #[DataProvider('deniedActors')]
    public function test_unrelated_or_inactive_actors_cannot_read_or_write_the_report(string $kind): void
    {
        $report = $this->prepare();
        $actor = match ($kind) {
            'other-teacher' => $this->newTeacher('Teacher Lain')->user,'other-admin' => $this->user('admin'),'student' => $this->student->portalUser,'inactive-user' => $this->teacher->user,'inactive-profile' => $this->teacher->user
        };
        if ($kind === 'inactive-user') {
            $actor->update(['is_active' => false]);
        }
        if ($kind === 'inactive-profile') {
            $this->teacher->update(['is_active' => false]);
        }
        $role = $actor->role_code === 'admin' ? 'admin' : 'teacher';
        $this->actingAs($actor)->get($this->url('show', $report, $role))->assertForbidden();
        $this->get($this->url('preview', $report, $role))->assertForbidden();
        $this->put($this->url('save', $report, $role), $this->form($report, $this->fields()))->assertForbidden();
        $this->post($this->url('generate', $report, $role), $this->form($report))->assertForbidden();
        $this->assertSame('draft', $report->fresh()->status);
    }

    public static function deniedActors(): array
    {
        return array_map(fn ($x) => [$x], ['other-teacher', 'other-admin', 'student', 'inactive-user', 'inactive-profile']);
    }

    public function test_guest_is_redirected_and_admin_cannot_forge_teacher_content_or_submit(): void
    {
        $report = $this->prepare();
        $this->get($this->url('show', $report))->assertRedirect(route('login'));
        $this->actingAs($this->admin)->put($this->url('save', $report, 'admin'), $this->form($report, $this->fields()))->assertForbidden();
        $this->get(route('admin.quarterly-reports.confirm', [$report, 'submit']))->assertForbidden();
        $this->actingAs($this->teacher->user)->get(route('teacher.quarterly-reports.confirm', [$report, 'approve']))->assertForbidden();
    }

    public function test_admin_can_reassign_only_to_an_actual_monthly_owner_and_teacher_cannot_reassign(): void
    {
        $report = $this->prepare();
        $other = $this->newTeacher('Pengganti Rapor');
        $this->cycle->monthlyReports()->whereDate('report_month', '2026-08-01')->update(['teacher_id' => $other->id]);
        $this->actingAs($this->teacher->user)->put($this->url('save', $report), $this->form($report, $this->fields() + ['teacher_id' => $other->id]))->assertForbidden();
        $this->actingAs($this->admin)->put($this->url('save', $report, 'admin'), $this->form($report, ['teacher_id' => $other->id, 'next' => 'review']))->assertSessionHasNoErrors();
        $this->assertSame($other->id, $report->fresh()->teacher_id);
        $this->actingAs($this->teacher->user)->get($this->url('show', $report))->assertForbidden();
        $this->actingAs($other->user)->get($this->url('edit', $report))->assertOk();
        $outsider = $this->newTeacher('Tidak Mengajar');
        $this->actingAs($this->admin)->put($this->url('save', $report, 'admin'), $this->form($report, ['teacher_id' => $outsider->id]))->assertSessionHasErrors('teacher_id');
    }

    public function test_stale_preparation_owner_choices_are_rejected(): void
    {
        $signature = app(QuarterlyReportContentService::class)->preparationSignature($this->cycle->fresh());
        $this->teacher->user->update(['full_name' => 'Nama Teacher terbaru']);
        $this->actingAs($this->admin)->post(route('admin.quarterly-reports.store', $this->cycle), ['teacher_id' => $this->teacher->id, 'expected_snapshot' => $signature])->assertSessionHasErrors('expected_snapshot');
        $this->assertDatabaseCount('quarterly_reports', 0);
    }

    public function test_double_submission_is_not_recorded_twice_and_locked_content_cannot_change(): void
    {
        $report = $this->prepare();
        $data = $this->form($report);
        $this->actingAs($this->teacher->user)->post($this->url('submit', $report), $data)->assertSessionHasNoErrors();
        $this->post($this->url('submit', $report), $data)->assertSessionHasErrors('expected_snapshot');
        $this->put($this->url('save', $report), $this->form($report, $this->fields()))->assertSessionHasErrors('status');
        $this->assertSame(1, $report->events()->where('event_type', 'submit')->count());
    }

    public function test_transition_and_audit_roll_back_together_when_event_insert_fails(): void
    {
        $report = $this->prepare();
        $count = QuarterlyReportEvent::count();
        QuarterlyReportEvent::creating(fn () => throw new \RuntimeException('Simulated event failure'));
        try {
            app(QuarterlyReportWorkflowService::class)->transition($this->teacher->user, $report, 'submit', $this->form($report));
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated event failure', $e->getMessage());
        }
        $this->assertSame('draft', $report->fresh()->status);
        $this->assertSame($count, QuarterlyReportEvent::count());
    }

    public function test_parent_readiness_requires_all_three_approved_months_final_and_validated_payments(): void
    {
        $report = $this->prepare();
        foreach (['2026-07-01', '2026-08-01', '2026-09-01'] as $month) {
            $this->pay($month);
        }
        $content = app(QuarterlyReportContentService::class);
        $this->assertFalse($content->gate($report)['ready']);
        $report = $this->approve($report, false);
        $this->assertFalse($content->gate($report)['ready']);
        $this->approveMonths();
        $this->assertTrue($content->gate($report)['ready']);
        PaymentProof::where('charge_id', MonthlyCharge::whereDate('charge_month', '2026-08-01')->value('id'))->update(['status' => 'rejected']);
        $this->assertFalse($content->gate($report)['ready']);
        $this->assertTrue($content->gate($report)['internal_pdf_ready']);
    }

    public function test_unrelated_or_ambiguous_payment_months_do_not_unlock_parent_readiness(): void
    {
        $report = $this->approve($this->prepare());
        $this->pay('2026-07-01');
        $this->pay('2026-08-01');
        $this->pay('2026-10-01');
        $this->assertFalse(app(QuarterlyReportContentService::class)->gate($report)['ready']);
        $this->pay('2026-09-30');
        $this->assertTrue(app(QuarterlyReportContentService::class)->gate($report)['ready']);
        $this->pay('2026-09-01');
        $this->assertFalse(app(QuarterlyReportContentService::class)->gate($report)['ready']);
    }

    public function test_generated_pdf_is_private_idempotent_and_contains_only_approved_public_snapshot(): void
    {
        $report = $this->approve($this->prepare());
        $pdf = app(QuarterlyReportPdfService::class);
        $file = $pdf->generate($this->teacher->user, $report, $this->form($report));
        $again = $pdf->generate($this->admin, $report, $this->form($report));
        $this->assertSame($file->id, $again->id);
        $this->assertSame(1, $report->files()->count());
        $bytes = $pdf->bytes($this->teacher->user, $report, $file);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertGreaterThan(20000, strlen($bytes));
        $this->assertSame(hash('sha256', $bytes), $file->file->sha256_hex);
        $json = json_encode($file->public_snapshot);
        foreach (['RAHASIA', 'CHALLENGES BULANAN', 'TEACHER MESSAGE BULANAN', 'BULANAN speaking', 'attendance', 'video_url', 'Improving'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $this->assertSame(array_keys(StudentProgressService::CATEGORIES), array_keys($file->public_snapshot['skills']));
        $this->actingAs($this->teacher->user)->get(route('teacher.quarterly-reports.download', [$report, $file]))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('teacher.quarterly-reports.download', [$report, $file, 'download' => 1]))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="DPE-Evaluation-v1.pdf"');
        $this->actingAs($this->student->portalUser)->get(route('teacher.quarterly-reports.download', [$report, $file]))->assertForbidden();
        $this->export('normal.pdf', $bytes);
    }

    public function test_approved_pdf_freezes_identity_teacher_template_logo_and_manual_content(): void
    {
        $report = $this->approve($this->prepare());
        $snapshot = $report->approved_snapshot;
        $this->student->update(['full_name' => 'Nama siswa diubah']);
        $this->teacher->user->update(['full_name' => 'Teacher diubah']);
        app(ReportTemplateService::class)->save($this->superadmin, ['expected_snapshot' => app(ReportTemplateService::class)->signature(), ...array_replace(config('report-pdf.defaults'), ['manager_name' => 'Manager pengganti'])]);
        $file = app(QuarterlyReportPdfService::class)->generate($this->teacher->user, $report->fresh(), $this->form($report));
        $this->assertSame($snapshot, $file->public_snapshot);
        $this->assertSame('Siswa Contoh Rapor', $file->public_snapshot['student_name']);
        $this->assertSame('Dipta Rapor', $file->public_snapshot['teacher_name']);
        $this->assertSame('Ketut Setia Dewi, S.S.', $file->public_snapshot['template']['fields']['manager_name']);
    }

    public function test_reopen_requires_reason_and_new_approval_keeps_old_file_bytes_unchanged(): void
    {
        $report = $this->approve($this->prepare());
        $pdf = app(QuarterlyReportPdfService::class);
        $first = $pdf->generate($this->teacher->user, $report, $this->form($report));
        $bytes = $pdf->bytes($this->teacher->user, $report, $first);
        $this->actingAs($this->teacher->user)->post($this->url('reopen', $report), $this->form($report))->assertSessionHasErrors('comment');
        $this->post($this->url('reopen', $report), $this->form($report, ['comment' => 'Perbaiki reading.']))->assertSessionHasNoErrors();
        $report = $report->fresh();
        $this->assertSame('draft', $report->status);
        $this->assertSame(2, $report->revision_number);
        $this->assertNull($report->approved_snapshot);
        $this->assertSame($bytes, $pdf->bytes($this->teacher->user, $report, $first));
        $data = $this->fields();
        $data['skills']['reading']['score'] = 'A';
        $report = app(QuarterlyReportWorkflowService::class)->save($this->teacher->user, $report, $this->form($report, $data));
        $report = $this->approve($report);
        $second = $pdf->generate($this->teacher->user, $report, $this->form($report));
        $this->assertSame(2, $second->version_number);
        $this->assertSame(2, $second->approval_revision);
        $this->assertSame('B', $first->public_snapshot['skills']['reading']['score']);
        $this->assertSame('A', $second->public_snapshot['skills']['reading']['score']);
        $this->assertSame($bytes, $pdf->bytes($this->teacher->user, $report, $first));
    }

    public function test_corrupted_pdf_is_rejected_and_regeneration_preserves_metadata_history(): void
    {
        $report = $this->approve($this->prepare());
        $pdf = app(QuarterlyReportPdfService::class);
        $file = $pdf->generate($this->teacher->user, $report, $this->form($report));
        Storage::disk('local')->put($file->file->storage_key, '%PDF-altered');
        $this->actingAs($this->teacher->user)->get(route('teacher.quarterly-reports.download', [$report, $file]))->assertNotFound();
        $this->post($this->url('generate', $report), $this->form($report))->assertSessionHasErrors('pdf');
        $this->post($this->url('regenerate', $report), $this->form($report))->assertSessionHasErrors('comment');
        $this->post($this->url('regenerate', $report), $this->form($report, ['comment' => 'File rusak; buat ulang.']))->assertSessionHasNoErrors();
        $this->assertSame(2, $report->files()->count());
        $this->assertSame('%PDF-altered', Storage::disk('local')->get($file->file->storage_key));
        $this->assertSame(1, $report->events()->where('event_type', 'pdf_regenerated')->count());
    }

    public function test_pdf_storage_and_metadata_roll_back_if_audit_insert_fails(): void
    {
        $report = $this->approve($this->prepare());
        $events = QuarterlyReportEvent::count();
        QuarterlyReportEvent::creating(fn () => throw new \RuntimeException('PDF event failure'));
        try {
            app(QuarterlyReportPdfService::class)->generate($this->teacher->user, $report, $this->form($report));
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('PDF event failure', $e->getMessage());
        }
        $this->assertDatabaseCount('quarterly_report_files', 0);
        $this->assertDatabaseCount('file_assets', 0);
        $this->assertSame($events, QuarterlyReportEvent::count());
        $this->assertSame([], Storage::disk('local')->allFiles('report-pdfs'));
        $this->assertSame('approved', $report->fresh()->status);
    }

    public function test_legacy_pdf_versions_are_preserved_and_cannot_be_served_as_new_approved_files(): void
    {
        $asset = FileAsset::create(['id' => (string) Str::uuid(), 'storage_key' => 'legacy.pdf', 'original_name' => 'legacy.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 12]);
        $legacy = QuarterlyReportFile::create(['id' => (string) Str::uuid(), 'cycle_id' => $this->cycle->id, 'file_id' => $asset->id, 'version_number' => 7, 'generated_by_user_id' => $this->admin->id, 'generated_at' => now()]);
        $report = $this->approve($this->prepare());
        $file = app(QuarterlyReportPdfService::class)->generate($this->teacher->user, $report, $this->form($report));
        $this->assertSame(8, $file->version_number);
        $this->assertNull($legacy->fresh()->quarterly_report_id);
        $this->actingAs($this->teacher->user)->get(route('teacher.quarterly-reports.download', [$report, $legacy]))->assertNotFound();
    }

    public function test_cycle_identity_cannot_change_after_final_preparation(): void
    {
        $this->prepare(false);
        $this->actingAs($this->superadmin)->putJson(route('admin.report-cycles.update', $this->cycle), ['cycle_number' => 2, 'start_month' => '2026-07-01', 'end_month' => '2026-09-01'])->assertUnprocessable()->assertJsonValidationErrors('start_month');
        $this->assertSame(1, $this->cycle->fresh()->cycle_number);
    }

    public function test_template_is_superadmin_only_and_rejects_stale_or_incomplete_settings(): void
    {
        $this->actingAs($this->admin)->get(route('superadmin.report-template.edit'))->assertForbidden();
        $this->actingAs($this->superadmin)->get(route('superadmin.report-template.edit'))->assertOk()->assertSee('Tanda tangan belum diunggah');
        $data = ['expected_snapshot' => app(ReportTemplateService::class)->signature(), ...config('report-pdf.defaults')];
        $this->put(route('superadmin.report-template.save'), array_replace($data, ['manager_name' => 'Manager Baru']))->assertSessionHasNoErrors();
        $this->put(route('superadmin.report-template.save'), $data)->assertSessionHasErrors('expected_snapshot');
        $this->put(route('superadmin.report-template.save'), ['expected_snapshot' => app(ReportTemplateService::class)->signature()])->assertSessionHasErrors('manager_name');
        $this->assertSame('Manager Baru', app(ReportTemplateService::class)->settings()['fields']['manager_name']);
    }

    public function test_long_multiline_unicode_descriptions_and_notes_generate_a_complete_pdf(): void
    {
        $report = $this->prepare();
        $data = $this->fields();
        foreach ($data['skills'] as $code => &$row) {
            $row['description'] = mb_substr(str_repeat('Latihan panjang '.$code." — café, progrès, “good”.\n", 80), 0, 3980);
        }
        unset($row);
        $data['skills']['reading']['description'] = str_repeat('W', 3900).' READING-END';
        $data['parent_note'] = mb_substr(str_repeat("Catatan untuk orang tua — latihan berlanjut.\n", 190), 0, 7950).' NOTE-END';
        $report = app(QuarterlyReportWorkflowService::class)->save($this->teacher->user, $report, $this->form($report, $data));
        $bytes = app(QuarterlyReportPdfService::class)->preview($this->teacher->user, $report);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertGreaterThan(30000, strlen($bytes));
        $this->export('long.pdf', $bytes);
    }

    public function test_cross_year_learning_period_and_extreme_valid_header_can_render(): void
    {
        $report = $this->prepare();
        $snapshot = app(QuarterlyReportContentService::class)->snapshot($report);
        $snapshot['start_month'] = '2026-12-01';
        $snapshot['end_month'] = '2027-02-01';
        $renderer = app(QuarterlyReportPdfRenderer::class);
        $this->export('cross-year.pdf', $renderer->render($snapshot, true));
        foreach (['organization_name', 'registration_number', 'slogan', 'address', 'phone'] as $key) {
            $snapshot['template']['fields'][$key] = str_repeat('W', 255);
        }
        $bytes = $renderer->render($snapshot, true);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->export('wide-header.pdf', $bytes);
    }

    public function test_migration_refuses_to_erase_final_history_on_rollback(): void
    {
        $this->prepare(false);
        $migration = require database_path('migrations/2026_10_11_080000_create_quarterly_report_workflow.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rollback ditolak');
        $migration->down();
    }

    public function test_latest_month_branch_controls_preparation_and_old_admin_loses_final_access(): void
    {
        $branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Terakhir', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
        $admin = $this->user('admin', 'Admin Cabang Terakhir');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $admin->id, 'branch_id' => $branch->id, 'starts_on' => '2026-01-01']);
        $this->cycle->monthlyReports()->whereDate('report_month', '2026-09-01')->update(['branch_id' => $branch->id]);
        $data = ['teacher_id' => $this->teacher->id, 'expected_snapshot' => app(QuarterlyReportContentService::class)->preparationSignature($this->cycle->fresh())];
        $this->actingAs($this->admin)->get(route('admin.quarterly-reports.prepare', $this->cycle))->assertForbidden();
        $this->post(route('admin.quarterly-reports.store', $this->cycle), $data)->assertForbidden();
        $this->actingAs($admin)->post(route('admin.quarterly-reports.store', $this->cycle), $data)->assertSessionHasNoErrors();
        $report = QuarterlyReport::firstOrFail();
        $this->assertSame($branch->id, $report->branch_id);
        $this->actingAs($this->admin)->get($this->url('show', $report, 'admin'))->assertForbidden();
        $this->actingAs($admin)->get($this->url('show', $report, 'admin'))->assertOk();
    }

    public function test_inactive_owner_is_explained_and_preparation_form_stays_visible(): void
    {
        $this->teacher->update(['is_active' => false]);
        $this->actingAs($this->admin)->get(route('admin.quarterly-reports.prepare', $this->cycle))->assertOk()->assertSee('Teacher pemilik rapor final')->assertSee('Belum ada Teacher pemilik bulanan');
        $this->post(route('admin.quarterly-reports.store', $this->cycle), ['teacher_id' => $this->teacher->id, 'expected_snapshot' => app(QuarterlyReportContentService::class)->preparationSignature($this->cycle->fresh())])->assertSessionHasErrors('teacher_id');
        $this->assertDatabaseCount('quarterly_reports', 0);
    }

    public function test_teacher_cannot_prepare_a_final_for_another_eligible_teacher(): void
    {
        $other = $this->newTeacher('Teacher Lain di Siklus');
        $this->cycle->monthlyReports()->whereDate('report_month', '2026-08-01')->update(['teacher_id' => $other->id]);
        $this->actingAs($this->teacher->user)->post(route('teacher.quarterly-reports.store', $this->cycle), ['teacher_id' => $other->id, 'expected_snapshot' => app(QuarterlyReportContentService::class)->preparationSignature($this->cycle->fresh())])->assertForbidden();
        $this->assertDatabaseCount('quarterly_reports', 0);
    }

    public function test_returning_to_the_same_content_does_not_revalidate_an_old_form(): void
    {
        $report = $this->prepare();
        $old = $this->form($report, $this->fields());
        $original = $report->getAttributes();
        $data = $this->fields();
        $data['parent_note'] = 'Catatan sementara';
        $workflow = app(QuarterlyReportWorkflowService::class);
        $workflow->save($this->teacher->user, $report, $this->form($report, $data));
        $workflow->save($this->teacher->user, $report, $this->form($report, $this->fields()));
        $this->assertSame($original, $report->fresh()->getAttributes());
        $this->actingAs($this->teacher->user)->put($this->url('save', $report), $old)->assertSessionHasErrors('expected_snapshot');
    }

    public function test_modified_file_snapshot_and_missing_file_are_rejected(): void
    {
        $report = $this->approve($this->prepare());
        $pdf = app(QuarterlyReportPdfService::class);
        $file = $pdf->generate($this->teacher->user, $report, $this->form($report));
        $original = $file->public_snapshot;
        $snapshot = $original;
        $snapshot['skills']['speaking']['score'] = 'A';
        $file->update(['public_snapshot' => $snapshot]);
        $this->actingAs($this->teacher->user)->get(route('teacher.quarterly-reports.download', [$report, $file]))->assertNotFound();
        $file->update(['public_snapshot' => $original]);
        Storage::disk('local')->delete($file->file->storage_key);
        $this->get(route('teacher.quarterly-reports.download', [$report, $file]))->assertNotFound();
        $this->post($this->url('regenerate', $report), $this->form($report, ['comment' => 'File hilang.']))->assertSessionHasNoErrors();
        $this->assertSame(2, $report->files()->count());
    }

    public function test_expired_admin_assignment_cannot_review_or_read_a_final_report(): void
    {
        $report = $this->prepare();
        app(QuarterlyReportWorkflowService::class)->transition($this->teacher->user, $report, 'submit', $this->form($report));
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-10-10']);
        $this->actingAs($this->admin)->get($this->url('show', $report, 'admin'))->assertForbidden();
        $this->post($this->url('approve', $report, 'admin'), $this->form($report))->assertForbidden();
        $this->assertSame('submitted', $report->fresh()->status);
    }

    public function test_approved_logo_bytes_are_frozen_even_after_institution_logo_changes(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 64, 64);
        $bytes = file_get_contents($file->getRealPath());
        $key = 'logos/test.png';
        Storage::disk('local')->put($key, $bytes);
        $asset = FileAsset::create(['id' => (string) Str::uuid(), 'storage_key' => $key, 'original_name' => 'logo.png', 'mime_type' => 'image/png', 'size_bytes' => strlen($bytes), 'sha256_hex' => hash('sha256', $bytes)]);
        $setting = SystemSetting::create(['id' => 1, 'organization_name' => 'DPE', 'logo_file_id' => $asset->id, 'default_report_due_day' => 31, 'default_monthly_video_target' => 4, 'in_app_reminders_enabled' => true]);
        $report = $this->approve($this->prepare());
        $logo = $report->approved_snapshot['template']['logo_data_uri'];
        $setting->update(['logo_file_id' => null]);
        Storage::disk('local')->delete($key);
        $pdf = app(QuarterlyReportPdfService::class)->generate($this->teacher->user, $report, $this->form($report));
        $this->assertSame('data:image/png;base64,'.base64_encode($bytes), $logo);
        $this->assertSame($logo, $pdf->public_snapshot['template']['logo_data_uri']);
    }

    public function test_empty_new_tables_can_roll_back_without_deleting_legacy_pdf_metadata(): void
    {
        $asset = FileAsset::create(['id' => (string) Str::uuid(), 'storage_key' => 'legacy.pdf', 'original_name' => 'legacy.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 12]);
        QuarterlyReportFile::create(['id' => (string) Str::uuid(), 'cycle_id' => $this->cycle->id, 'file_id' => $asset->id, 'version_number' => 1, 'generated_by_user_id' => $this->admin->id, 'generated_at' => now()]);
        $migration = require database_path('migrations/2026_10_11_080000_create_quarterly_report_workflow.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('quarterly_reports'));
        $this->assertDatabaseCount('quarterly_report_files', 1);
        $this->assertDatabaseCount('monthly_reports', 3);
    }


    public function test_eight_skill_schema_matches_monthly_categories_and_exposes_all_manual_fields(): void
    {
        $this->assertSame(['speaking', 'listening', 'reading', 'writing', 'vocabulary', 'grammar', 'pronunciation', 'confidence_participation'], array_keys(QuarterlyReportContentService::SKILLS));
        $this->assertSame(StudentProgressService::CATEGORIES, QuarterlyReportContentService::SKILLS);
        $report = $this->prepare();
        $response = $this->actingAs($this->teacher->user)->get($this->url('edit', $report))->assertOk()->assertSee('Delapan skill');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(8, $xpath->query('//select[starts-with(@name, "skills[")]')->length);
        $this->assertSame(8, $xpath->query('//textarea[starts-with(@name, "skills[")]')->length);
        foreach (QuarterlyReportContentService::SKILLS as $code => $label) {
            $response->assertSee('skills['.$code.'][score]', false)->assertSee('skills['.$code.'][description]', false);
        }
        $this->get($this->url('show', $report))->assertOk()->assertSee('8 / 8 skill lengkap');
    }

    public static function eightSkillCodes(): array
    {
        return array_map(fn ($code) => [$code], array_keys(QuarterlyReportContentService::SKILLS));
    }

    #[DataProvider('eightSkillCodes')]
    public function test_eight_skill_submission_and_approval_require_every_assessment(string $code): void
    {
        $report = $this->prepare();
        $data = $this->fields();
        $data['skills'][$code] = ['description' => null, 'score' => null];
        $this->actingAs($this->teacher->user)->put($this->url('save', $report), $this->form($report, $data))->assertSessionHasNoErrors();
        $this->postJson($this->url('submit', $report), $this->form($report))->assertUnprocessable()->assertJsonValidationErrors('skills.'.$code);
        $this->assertSame('draft', $report->fresh()->status);
        $report->update(['status' => 'submitted']);
        $this->actingAs($this->admin)->postJson($this->url('approve', $report, 'admin'), $this->form($report))->assertUnprocessable()->assertJsonValidationErrors('skills.'.$code);
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertNull($report->fresh()->approved_snapshot);
    }

    public function test_eight_skill_pdf_snapshot_and_parent_view_preserve_all_scores_in_monthly_order(): void
    {
        $report = $this->prepare();
        $data = $this->fields();
        $descriptions = [
            'speaking' => 'Mampu menyampaikan gagasan dan menjawab pertanyaan sederhana dengan kalimat yang runtut.',
            'listening' => 'Mampu memahami instruksi dan informasi utama dari percakapan dengan kecepatan wajar.',
            'reading' => 'Mampu menemukan gagasan pokok serta rincian penting pada bacaan sesuai tingkat belajar.',
            'writing' => 'Mampu menyusun kalimat dan paragraf pendek dengan struktur yang semakin konsisten.',
            'vocabulary' => 'Menggunakan kosakata baru secara tepat dalam latihan lisan dan tulisan sehari-hari.',
            'grammar' => 'Menerapkan tata bahasa dasar dengan baik dan memperbaiki kesalahan setelah mendapat umpan balik.',
            'pronunciation' => 'Mengucapkan bunyi dan kata dengan lebih jelas serta menunjukkan kemajuan pada intonasi.',
            'confidence_participation' => 'Percaya diri mencoba berbicara dan aktif berpartisipasi dalam diskusi serta kegiatan kelas.',
        ];
        foreach (array_keys($data['skills']) as $index => $code) {
            $data['skills'][$code] = ['description' => $descriptions[$code], 'score' => ['A', 'B', 'C'][$index % 3]];
        }
        $data['parent_note'] = 'Siswa menunjukkan perkembangan yang baik selama tiga bulan. Lanjutkan latihan membaca dan berbicara secara teratur di rumah.';
        $report = app(QuarterlyReportWorkflowService::class)->save($this->teacher->user, $report, $this->form($report, $data));
        $report = $this->approve($report);
        $snapshot = $report->approved_snapshot;
        $this->assertSame(2, $snapshot['snapshot_version']);
        $this->assertCount(8, $snapshot['skills']);
        $this->assertSame($data['skills'], $snapshot['skills']);
        $pdf = app(QuarterlyReportPdfService::class);
        $file = $pdf->generate($this->teacher->user, $report, $this->form($report));
        $bytes = $pdf->bytes($this->teacher->user, $report, $file);
        $this->export('eight-skills.pdf', $bytes);
        $this->assertSame($snapshot, $file->public_snapshot);
        foreach (['admin' => $this->admin, 'superadmin' => $this->superadmin] as $role => $actor) {
            $this->actingAs($actor)->get($this->url('show', $report, $role))->assertOk()->assertViewHas('skillLabels', QuarterlyReportContentService::SKILLS)->assertSee('Delapan skill');
        }
        foreach (['2026-07-01', '2026-08-01', '2026-09-01'] as $month) $this->pay($month);
        $response = $this->actingAs($this->student->portalUser)->get(route('student.reports.quarterly', $report))->assertOk()->assertViewIs('student.portal.quarterly');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(8, $xpath->query('//table[contains(@class,"dpe-portal-skills")]/tbody/tr')->length);
        foreach ($descriptions as $text) $response->assertSee($text);
        $this->get(route('student.reports.pdf', [$report, $file]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_eight_skill_legacy_approval_requires_revision_and_preserves_its_original_pdf(): void
    {
        $report = $this->approve($this->prepare());
        $legacy = $report->approved_snapshot;
        $legacy['snapshot_version'] = 1;
        $legacy['skills'] = array_intersect_key($legacy['skills'], QuarterlyReportContentService::LEGACY_SKILLS);
        $report->update(['skills' => $legacy['skills'], 'approved_snapshot' => $legacy]);
        $pdf = app(QuarterlyReportPdfService::class);
        $oldFile = $pdf->generate($this->teacher->user, $report, $this->form($report));
        $oldBytes = $pdf->bytes($this->teacher->user, $report, $oldFile);
        $this->export('legacy-four-skills.pdf', $oldBytes);
        $this->assertCount(4, $oldFile->public_snapshot['skills']);
        $this->actingAs($this->teacher->user)->get($this->url('show', $report))->assertOk()->assertSee('format lama (4 skill)')->assertViewHas('skillLabels', QuarterlyReportContentService::LEGACY_SKILLS)->assertDontSee('Vocabulary');
        foreach (['2026-07-01', '2026-08-01', '2026-09-01'] as $month) $this->pay($month);
        $this->actingAs($this->student->portalUser)->get(route('student.reports.quarterly', $report))->assertOk()->assertViewIs('student.portal.quarterly')->assertDontSee('Vocabulary');
        $this->actingAs($this->teacher->user)->post($this->url('reopen', $report), $this->form($report, ['comment' => 'Lengkapi penilaian delapan skill']))->assertSessionHasNoErrors();
        $report->refresh();
        $response = $this->get($this->url('edit', $report))->assertOk()->assertSee('skills[vocabulary][score]', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame('', $xpath->query('//textarea[@name="skills[vocabulary][description]"]')->item(0)->textContent);
        $this->assertSame(0, $xpath->query('//select[@name="skills[vocabulary][score]"]/option[@selected and @value!=""]')->length);
        $this->assertSame($legacy['skills'], $report->skills);
        $this->postJson($this->url('submit', $report), $this->form($report))->assertUnprocessable()->assertJsonValidationErrors(['skills.vocabulary', 'skills.grammar', 'skills.pronunciation', 'skills.confidence_participation']);
        $data = $this->fields();
        foreach ($legacy['skills'] as $code => $row) $data['skills'][$code] = $row;
        $this->put($this->url('save', $report), $this->form($report, $data))->assertSessionHasNoErrors();
        $report = $this->approve($report->fresh());
        $newFile = $pdf->generate($this->teacher->user, $report, $this->form($report));
        $this->assertSame(2, $newFile->version_number);
        $this->assertSame(2, $newFile->approval_revision);
        $this->assertSame(2, $newFile->public_snapshot['snapshot_version']);
        $this->assertCount(8, $newFile->public_snapshot['skills']);
        $this->assertSame($oldBytes, $pdf->bytes($this->teacher->user, $report, $oldFile->fresh()));
        $this->assertSame($legacy, $oldFile->fresh()->public_snapshot);
        $this->assertDatabaseCount('quarterly_report_files', 2);
        $this->export('legacy-upgraded-eight-skills.pdf', $pdf->bytes($this->teacher->user, $report, $newFile));
        $this->actingAs($this->student->portalUser)->get(route('student.reports.quarterly', $report))->assertOk()->assertSee('Vocabulary')->assertSee('Confidence/Participation');
    }

    public function test_eight_skill_old_submitted_report_cannot_be_approved_until_new_categories_are_filled(): void
    {
        $report = $this->prepare();
        $legacy = array_intersect_key($report->skills, QuarterlyReportContentService::LEGACY_SKILLS);
        $report->update(['skills' => $legacy, 'status' => 'submitted']);
        $this->actingAs($this->admin)->get($this->url('show', $report, 'admin'))->assertOk()->assertSee('4 / 8 skill lengkap');
        $this->postJson($this->url('approve', $report, 'admin'), $this->form($report))->assertUnprocessable()->assertJsonValidationErrors(['skills.vocabulary', 'skills.grammar', 'skills.pronunciation', 'skills.confidence_participation']);
        $this->post($this->url('revision', $report, 'admin'), $this->form($report, ['comment' => 'Lengkapi empat penilaian tambahan']))->assertSessionHasNoErrors();
        $this->assertSame('revision', $report->fresh()->status);
        $this->assertSame($legacy, $report->fresh()->skills);
        $this->actingAs($this->teacher->user)->get($this->url('edit', $report))->assertOk()->assertSee('skills[confidence_participation][score]', false);
    }

    public function test_eight_skill_renderer_does_not_hide_missing_or_unknown_categories(): void
    {
        $snapshot = app(QuarterlyReportContentService::class)->snapshot($this->prepare());
        $cases = [];
        $missing = $snapshot;
        unset($missing['skills']['confidence_participation']);
        $cases[] = $missing;
        $extra = $snapshot;
        $extra['skills']['unknown'] = ['score' => 'A', 'description' => 'Tidak boleh disembunyikan'];
        $cases[] = $extra;
        $incompleteImported = $snapshot;
        unset($incompleteImported['snapshot_version']);
        $incompleteImported['skills'] = array_intersect_key($snapshot['skills'], QuarterlyReportContentService::LEGACY_SKILLS + ['grammar' => 'Grammar']);
        $cases[] = $incompleteImported;
        foreach ($cases as $case) {
            try {
                app(QuarterlyReportPdfRenderer::class)->validateSnapshot($case);
                $this->fail('Incomplete or unknown categories must not be hidden in a PDF.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_eight_skill_long_pdf_retains_all_category_end_markers(): void
    {
        $snapshot = app(QuarterlyReportContentService::class)->snapshot($this->prepare());
        foreach ($snapshot['skills'] as $code => &$row) {
            $row['description'] = str_repeat('Perkembangan '.$code.' menunjukkan kemajuan yang konsisten melalui latihan terarah. ', 8).' END-'.$code;
        }
        unset($row);
        $snapshot['parent_note'] = str_repeat('Lanjutkan latihan teratur dan berikan kesempatan anak untuk mencoba secara mandiri. ', 22).' END-NOTE';
        $bytes = app(QuarterlyReportPdfRenderer::class)->render($snapshot, true);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->export('eight-skills-long.pdf', $bytes);
    }

    private function export(string $name, string $bytes): void
    {
        if ($directory = getenv('DPE_PDF_QA_DIR')) {
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            } file_put_contents($directory.'/'.$name, $bytes);
        }
    }
}
