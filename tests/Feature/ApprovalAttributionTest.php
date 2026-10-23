<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\MonthlyReport;
use App\Models\Program;
use App\Models\QuarterlyReport;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\MonthlyReportContentService;
use App\Services\MonthlyReportWorkflowService;
use App\Services\QuarterlyReportContentService;
use App\Services\QuarterlyReportWorkflowService;
use App\Services\StudentProgressService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApprovalAttributionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $superadmin;
    private Teacher $teacher;
    private Branch $branch;
    private Student $student;
    private ReportCycle $cycle;
    private MonthlyReport $monthly;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-22 14:30:00', 'Asia/Makassar'));
        $this->admin = $this->user('admin', 'Rina Pemeriksa');
        $this->superadmin = $this->user('superadmin', 'Budi Pusat');
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', 'Teacher Pemilik')->id,
            'whatsapp_number' => '081234567890', 'is_active' => true]);
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => 'Cabang Persetujuan',
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id,
            'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id,
            'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => 'English', 'class_type' => 'private', 'is_active' => true]);
        $this->student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student', 'Akun Anak')->id,
            'full_name' => 'Siswa Persetujuan', 'began_on' => '2026-08-01', 'status' => 'active']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id,
            'branch_id' => $this->branch->id, 'program_id' => $program->id, 'starts_on' => '2026-08-01']);
        $this->cycle = ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->student->id,
            'cycle_number' => 1, 'start_month' => '2026-08-01', 'end_month' => '2026-10-01']);
        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $month) {
            $report = MonthlyReport::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->student->id,
                'cycle_id' => $this->cycle->id, 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id,
                'report_month' => $month, 'due_on' => Carbon::parse($month)->endOfMonth(), 'status' => 'draft',
                'video_target' => 0, 'video_target_confirmed_at' => now(), 'video_target_confirmed_by_user_id' => $this->admin->id,
                'development_summary' => 'Ringkasan siswa', 'parent_challenges_summary' => 'Latihan siswa', 'parent_message' => 'Terus berlatih']);
            foreach (app(StudentProgressService::class)->categories() as $code => $skill) {
                DB::table('monthly_report_skills')->insert(['report_id' => $report->id, 'skill_id' => $skill->id,
                    'trend' => 'Improving', 'description' => 'Perkembangan ' . $code]);
            }
            $this->monthly = $report;
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => $role . '_' . Str::random(12),
            'password_hash' => 'fixture-approval-hash', 'full_name' => $name, 'role_code' => $role, 'is_active' => true]);
    }

    private function approveMonthly(User $approver): MonthlyReport
    {
        $workflow = app(MonthlyReportWorkflowService::class);
        $submitted = $workflow->submit($this->monthly->fresh(), $this->teacher->user);

        return $this->monthly = $workflow->approve($submitted, $approver);
    }

    private function quarterly(User $approver): QuarterlyReport
    {
        $workflow = app(QuarterlyReportWorkflowService::class);
        $content = app(QuarterlyReportContentService::class);
        $report = $workflow->prepare($this->admin, $this->cycle, ['teacher_id' => $this->teacher->id,
            'expected_snapshot' => $content->preparationSignature($this->cycle->fresh())]);
        $report = $workflow->save($this->teacher->user, $report, ['expected_snapshot' => $content->signature($report->fresh()),
            'skills' => collect(QuarterlyReportContentService::SKILLS)->map(fn ($label) => ['description' => 'Kemajuan ' . $label, 'score' => 'B'])->all(),
            'parent_note' => 'Latihan rutin di rumah']);
        $report = $workflow->transition($this->teacher->user, $report, 'submit', ['expected_snapshot' => $content->signature($report->fresh())]);

        return $workflow->transition($approver, $report, 'approve', ['expected_snapshot' => $content->signature($report->fresh())]);
    }

    private function attendance(User $approver): TeacherAttendance
    {
        return TeacherAttendance::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id,
            'branch_id' => $this->branch->id, 'attendance_mode' => 'branch_visit', 'attendance_date' => '2026-10-22',
            'check_in_at' => '2026-10-22 10:00:00', 'check_out_at' => '2026-10-22 12:00:00',
            'attendance_type' => 'present', 'status' => 'approved', 'reviewed_by_user_id' => $approver->id, 'reviewed_at' => now()]);
    }

    private function html(TestResponse $response): DOMXPath
    {
        $response->assertOk();
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function metadata(TestResponse $response): \DOMNodeList
    {
        return $this->html($response)->query('//*[contains(concat(" ",normalize-space(@class)," ")," dpe-approval-meta ")]');
    }

    private function assertAttribution(TestResponse $response, User $approver, string $date = '22 Oktober 2026, 14:30 WITA'): void
    {
        $metadata = $this->metadata($response);
        $this->assertSame(1, $metadata->length);
        $this->assertStringContainsString('Disetujui oleh', $metadata->item(0)->textContent);
        $this->assertStringContainsString($approver->full_name, $metadata->item(0)->textContent);
        $this->assertStringContainsString($approver->role_code === 'admin' ? 'Admin Cabang' : 'Super Admin', $metadata->item(0)->textContent);
        $this->assertStringContainsString($date, $metadata->item(0)->textContent);
        $this->assertSame(1, $this->html($response)->query('//link[contains(@href,"/assets/dpe/approval.css?v=")]')->length);
    }

    public static function staffAndApprovers(): array
    {
        return [['admin', 'admin'], ['admin', 'superadmin'], ['superadmin', 'admin'], ['superadmin', 'superadmin'], ['teacher', 'admin'], ['teacher', 'superadmin']];
    }

    #[DataProvider('staffAndApprovers')]
    public function test_report_lists_and_details_show_the_real_approver_for_all_staff(string $viewer, string $approverRole): void
    {
        $approver = $this->$approverRole;
        $monthly = $this->approveMonthly($approver);
        $quarterly = $this->quarterly($approver);
        $actor = $viewer === 'teacher' ? $this->teacher->user : $this->$viewer;
        $this->actingAs($actor);
        $list = $this->get(route($viewer . '.monthly-reports.index', ['month' => '2026-10', 'status' => 'all']));
        $this->assertAttribution($list, $approver);
        $list->assertViewHas('reports', fn ($reports) => $reports->every(fn ($r) => $r->relationLoaded('approvedBy')));
        $this->assertAttribution($this->get(route($viewer . '.monthly-reports.show', $monthly)), $approver);
        $quarterList = $this->get(route($viewer . '.quarterly-reports.index'));
        $this->assertAttribution($quarterList, $approver);
        $quarterList->assertViewHas('rows', fn ($rows) => $rows->every(fn ($r) => $r['report']?->relationLoaded('approvedBy')));
        $detail = $this->get(route($viewer . '.quarterly-reports.show', $quarterly));
        $this->assertAttribution($detail, $approver);
        $this->assertStringContainsString('Status rapor final', $this->html($detail)->query('//*[contains(@class,"dpe-approval-meta")]/ancestor::article[1]')->item(0)->textContent);
    }

    public function test_forged_approval_identity_is_ignored_and_the_logged_in_reviewer_is_shown(): void
    {
        $workflow = app(MonthlyReportWorkflowService::class);
        $report = $workflow->submit($this->monthly, $this->teacher->user);
        $this->actingAs($this->admin)->post(route('admin.monthly-reports.approve', $report), [
            'expected_snapshot' => app(MonthlyReportContentService::class)->signature($report->fresh()),
            'approved_by_user_id' => $this->superadmin->id, 'approved_at' => '2000-01-01 00:00:00',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->admin->id, $report->fresh()->approved_by_user_id);
        $this->assertAttribution($this->get(route('admin.monthly-reports.show', $report)), $this->admin);
    }

    public function test_attendance_lists_show_the_reviewer_to_teacher_admin_and_superadmin(): void
    {
        $this->attendance($this->superadmin);
        $this->actingAs($this->teacher->user);
        $this->assertAttribution($this->get(route('teacher.attendance.index')), $this->superadmin);
        foreach ([$this->admin, $this->superadmin] as $actor) {
            $this->actingAs($actor);
            $this->assertAttribution($this->get(route('admin.teacher-attendance.index', ['status' => 'approved', 'date_from' => '2026-10-01', 'date_to' => '2026-10-22'])), $this->superadmin);
        }
    }

    public function test_nonapproved_rows_do_not_show_stale_approval_information(): void
    {
        $monthly = $this->approveMonthly($this->admin);
        $quarterly = $this->quarterly($this->admin);
        $attendance = $this->attendance($this->admin);
        $monthly->update(['status' => 'submitted']);
        $quarterly->update(['status' => 'submitted']);
        $attendance->update(['status' => 'pending_review']);
        $this->actingAs($this->teacher->user);
        foreach ([route('teacher.monthly-reports.index', ['month' => '2026-10']), route('teacher.monthly-reports.show', $monthly),
            route('teacher.quarterly-reports.index'), route('teacher.quarterly-reports.show', $quarterly), route('teacher.attendance.index')] as $url) {
            $this->assertSame(0, $this->metadata($this->get($url))->length);
        }
    }

    public function test_legacy_missing_approver_and_time_are_explicit_instead_of_using_the_viewer(): void
    {
        $monthly = $this->approveMonthly($this->admin);
        $quarterly = $this->quarterly($this->admin);
        $attendance = $this->attendance($this->admin);
        $monthly->update(['approved_by_user_id' => null, 'approved_at' => null]);
        $quarterly->update(['approved_by_user_id' => null, 'approved_at' => null]);
        $attendance->update(['reviewed_by_user_id' => null, 'reviewed_at' => null]);
        $this->actingAs($this->teacher->user);
        foreach ([route('teacher.monthly-reports.index', ['month' => '2026-10']), route('teacher.monthly-reports.show', $monthly),
            route('teacher.quarterly-reports.index'), route('teacher.quarterly-reports.show', $quarterly), route('teacher.attendance.index')] as $url) {
            $metadata = $this->metadata($this->get($url));
            $this->assertSame(1, $metadata->length);
            $this->assertStringContainsString('Nama belum tersedia', $metadata->item(0)->textContent);
            $this->assertStringContainsString('Waktu persetujuan belum tersedia', $metadata->item(0)->textContent);
            $this->assertStringNotContainsString('Teacher Pemilik', $metadata->item(0)->textContent);
        }
    }

    public function test_inactive_reviewer_is_still_identified_and_username_is_a_fallback(): void
    {
        $monthly = $this->approveMonthly($this->admin);
        $this->admin->update(['is_active' => false]);
        $this->actingAs($this->teacher->user);
        $this->assertAttribution($this->get(route('teacher.monthly-reports.show', $monthly)), $this->admin);
        $this->admin->update(['full_name' => '   ']);
        $meta = $this->metadata($this->get(route('teacher.monthly-reports.show', $monthly)));
        $this->assertStringContainsString($this->admin->username, $meta->item(0)->textContent);
    }

    public function test_timestamps_use_branch_timezone_and_include_machine_readable_offset(): void
    {
        $monthly = $this->approveMonthly($this->admin);
        $this->branch->update(['timezone_name' => 'Asia/Jakarta']);
        $this->actingAs($this->teacher->user);
        $response = $this->get(route('teacher.monthly-reports.show', $monthly));
        $this->assertAttribution($response, $this->admin, '22 Oktober 2026, 13:30 WIB');
        $time = $this->html($response)->query('//*[contains(@class,"dpe-approval-meta")]/time')->item(0);
        $this->assertStringEndsWith('+07:00', $time->getAttribute('datetime'));
        $this->branch->update(['timezone_name' => 'legacy-invalid-zone']);
        $this->assertAttribution($this->get(route('teacher.monthly-reports.index', ['month' => '2026-10'])), $this->admin);
    }

    public function test_reopening_removes_current_approval_and_reapproval_shows_the_new_reviewer(): void
    {
        $quarterly = $this->quarterly($this->admin);
        $workflow = app(QuarterlyReportWorkflowService::class);
        $content = app(QuarterlyReportContentService::class);
        $quarterly = $workflow->transition($this->teacher->user, $quarterly, 'reopen', ['comment' => 'Perbaikan rapor', 'expected_snapshot' => $content->signature($quarterly->fresh())]);
        $this->actingAs($this->teacher->user);
        $this->assertSame(0, $this->metadata($this->get(route('teacher.quarterly-reports.show', $quarterly)))->length);
        $quarterly = $workflow->transition($this->teacher->user, $quarterly, 'submit', ['expected_snapshot' => $content->signature($quarterly->fresh())]);
        $quarterly = $workflow->transition($this->superadmin, $quarterly, 'approve', ['expected_snapshot' => $content->signature($quarterly->fresh())]);
        $response = $this->get(route('teacher.quarterly-reports.show', $quarterly));
        $this->assertAttribution($response, $this->superadmin);
        $this->assertStringNotContainsString($this->admin->full_name, $this->metadata($response)->item(0)->textContent);
        $response->assertSee('Rina Pemeriksa'); // Previous approval remains in the audit history.
    }

    public function test_unrelated_admin_teacher_student_and_guest_cannot_read_approval_details(): void
    {
        $monthly = $this->approveMonthly($this->admin);
        $quarterly = $this->quarterly($this->admin);
        foreach (['admin', 'teacher', 'student'] as $role) {
            $actor = $this->user($role, 'Pengguna luar');
            $this->actingAs($actor);
            $prefix = $role === 'student' ? 'admin' : $role;
            $this->get(route($prefix . '.monthly-reports.show', $monthly))->assertForbidden();
            $this->get(route($prefix . '.quarterly-reports.show', $quarterly))->assertForbidden();
            if ($role !== 'student') {
                $this->get(route($role . '.monthly-reports.index', ['month' => '2026-10', 'status' => 'all']))->assertOk()->assertDontSee('Rina Pemeriksa');
                $this->get(route($role . '.quarterly-reports.index'))->assertOk()->assertDontSee('Rina Pemeriksa');
            }
        }
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.monthly-reports.show', $monthly))->assertRedirect(route('login'));
    }

    public function test_names_are_escaped_and_get_requests_preserve_approval_records(): void
    {
        $this->admin->update(['full_name' => '<img src=x onerror=alert(1)>']);
        $monthly = $this->approveMonthly($this->admin);
        $quarterly = $this->quarterly($this->admin);
        $this->attendance($this->admin);
        $tables = ['monthly_reports', 'quarterly_reports', 'teacher_attendances', 'report_status_events', 'quarterly_report_events'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $this->actingAs($this->teacher->user);
        foreach ([route('teacher.monthly-reports.index', ['month' => '2026-10']), route('teacher.monthly-reports.show', $monthly),
            route('teacher.quarterly-reports.index'), route('teacher.quarterly-reports.show', $quarterly), route('teacher.attendance.index')] as $url) {
            $this->get($url)->assertOk()->assertSee('&lt;img src=x', false)->assertDontSee('<img src=x', false);
        }
        $after = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $this->assertSame($before, $after);
    }
}
