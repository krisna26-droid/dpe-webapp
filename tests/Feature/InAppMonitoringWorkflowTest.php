<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\FileAsset;
use App\Models\LearningVideo;
use App\Models\MonthlyCharge;
use App\Models\MonthlyReport;
use App\Models\Notification;
use App\Models\PaymentProof;
use App\Models\Program;
use App\Models\QuarterlyReport;
use App\Models\QuarterlyReportEvent;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use App\Services\InAppNotificationService;
use App\Services\MonthlyReportWorkflowService;
use App\Services\OperationsAccessService;
use App\Services\OperationsMonitoringService;
use App\Services\PortalPaymentService;
use App\Services\QuarterlyReportContentService;
use App\Services\QuarterlyReportWorkflowService;
use App\Services\QuarterlyReportPdfService;
use App\Services\StudentProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InAppMonitoringWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $admin;
    private Teacher $teacher;
    private Teacher $otherTeacher;
    private Branch $branch;
    private Branch $otherBranch;
    private Student $student;
    private Student $otherStudent;
    private MonthlyReport $report;
    private MonthlyReport $otherReport;
    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Makassar'));
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->superadmin = $this->user('superadmin', 'Super Admin Uji');
        $this->admin = $this->user('admin', 'Admin Uji');
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', 'Teacher A Uji')->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        $this->otherTeacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', 'Teacher B Uji')->id, 'whatsapp_number' => '081234567891', 'is_active' => true]);
        $this->branch = $this->branch('Cabang A Uji', 'Asia/Makassar');
        $this->otherBranch = $this->branch('Cabang B Rahasia', 'Asia/Jakarta');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Program Uji', 'class_type' => 'private', 'is_active' => true]);
        $this->student = $this->student('Siswa A Uji', $this->branch, $this->teacher);
        $this->otherStudent = $this->student('Siswa B Rahasia', $this->otherBranch, $this->otherTeacher);
        $this->report = $this->report($this->student, $this->branch, $this->teacher);
        $this->otherReport = $this->report($this->otherStudent, $this->otherBranch, $this->otherTeacher);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(15), 'password_hash' => 'test-fixture', 'full_name' => $name, 'role_code' => $role, 'is_active' => true]);
    }

    private function branch(string $name, string $zone): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => $name, 'timezone_name' => $zone, 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function student(string $name, Branch $branch, Teacher $teacher): Student
    {
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student', $name.' Portal')->id, 'full_name' => $name, 'began_on' => '2026-10-01', 'status' => 'active', 'special_notes_internal' => 'RAHASIA PROFIL']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $branch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-10-01']);
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'teacher_id' => $teacher->id, 'starts_on' => '2026-10-01', 'is_report_owner' => true]);

        return $student;
    }

    private function report(Student $student, Branch $branch, Teacher $teacher, string $month = '2026-10-01'): MonthlyReport
    {
        $cycle = $student->reportCycles()->first() ?? ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'cycle_number' => 1, 'start_month' => '2026-10-01', 'end_month' => '2026-12-01']);
        $report = MonthlyReport::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'cycle_id' => $cycle->id, 'branch_id' => $branch->id, 'teacher_id' => $teacher->id,
            'report_month' => $month, 'due_on' => '2026-10-14', 'status' => 'draft', 'video_target' => 0, 'video_target_confirmed_at' => now(), 'video_target_confirmed_by_user_id' => $this->admin->id,
            'development_summary' => 'PERKEMBANGAN PUBLIK', 'parent_challenges_summary' => 'CHALLENGES PUBLIK', 'parent_message' => 'PESAN PUBLIK', 'internal_teacher_note' => 'RAHASIA TEACHER']);
        foreach (app(StudentProgressService::class)->categories() as $skill) {
            DB::table('monthly_report_skills')->insert(['report_id' => $report->id, 'skill_id' => $skill->id, 'trend' => 'Improving', 'description' => 'Deskripsi uji']);
        }

        return $report;
    }

    private function approve(?MonthlyReport $report = null): MonthlyReport
    {
        $report ??= $this->report;
        $workflow = app(MonthlyReportWorkflowService::class);
        $report = $workflow->submit($report, $report->teacher->user);

        return $workflow->approve($report, $this->superadmin);
    }

    private function charge(?Student $student = null, string $month = '2026-10-01'): MonthlyCharge
    {
        $student ??= $this->student;

        return MonthlyCharge::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $student->enrollments->first()->branch_id, 'charge_month' => $month, 'amount_idr' => null]);
    }

    private function proof(MonthlyCharge $charge, string $status = 'pending'): PaymentProof
    {
        $asset = FileAsset::create(['id' => (string) Str::uuid(), 'storage_key' => 'test/'.Str::random(10), 'original_name' => 'fixture.png', 'mime_type' => 'image/png', 'size_bytes' => 1]);

        return PaymentProof::create(['id' => (string) Str::uuid7(), 'charge_id' => $charge->id, 'image_file_id' => $asset->id, 'uploaded_by_user_id' => $charge->student->portal_user_id, 'submitted_at' => now(), 'status' => $status]);
    }

    public function test_guests_cannot_open_either_module(): void
    {
        $this->get(route('inbox.index'))->assertRedirect(route('login'));
        $this->get(route('monitoring.index'))->assertRedirect(route('login'));
    }

    public static function staffRoles(): array
    {
        return [['superadmin'], ['admin'], ['teacher']];
    }

    #[DataProvider('staffRoles')]
    public function test_staff_pages_show_real_data_and_no_whatsapp_integration(string $role): void
    {
        $actor = match ($role) { 'superadmin' => $this->superadmin, 'admin' => $this->admin, default => $this->teacher->user };
        $this->actingAs($actor)->get(route('inbox.index'))->assertOk()->assertSee('Notifikasi')->assertSee('Siswa A Uji')->assertDontSee('RAHASIA TEACHER');
        $this->get(route('monitoring.index'))->assertOk()->assertSee('Monitoring Laporan')->assertSee('Siswa A Uji')->assertDontSee('Fonnte');
        $dashboard = $this->get(route($role.'.dashboard'))->assertOk()->assertDontSee('Menunggu sinkronisasi');
        $dashboard->assertDontSee('Monitoring laporan ·');
        if ($role !== 'teacher') {
            if ($role === 'admin') {
                $dashboard->assertSee('Review laporan');
            }
        }
        $this->assertFalse(Schema::hasTable('fonnte_connections'));
        Http::assertNothingSent();
    }

    public function test_superadmin_overview_reuses_monthly_approval_and_overdue_rules(): void
    {
        $this->report->forceFill(['due_on' => '2026-10-01'])->save();
        $this->approve($this->report);
        $this->otherReport->forceFill(['status' => 'submitted', 'due_on' => '2026-10-11'])->save();

        $response = $this->actingAs($this->superadmin)->get(route('superadmin.dashboard'))
            ->assertOk()->assertSee('Monitoring rapor bulanan')->assertSee('Ringkasan cabang')
            ->assertSee('Completed')->assertSee('Pending')->assertSee('Overdue')
            ->assertSee('Cabang aktif')->assertSee('Pengguna aktif')->assertSee('Jadwal hari ini');
        $overview = $response->viewData('dashboardOverview');
        $expected = app(OperationsMonitoringService::class)->data($this->superadmin, [], false)['summary'];
        $this->assertSame($expected, $overview['summary']);
        $this->assertSame(1, $overview['summary']['approved']);
        $this->assertSame(1, $overview['summary']['pending']);
        $this->assertSame(1, $overview['summary']['overdue']);
        $this->assertSame($this->otherTeacher->id, $overview['teacher_attention']->first()['teacher_id']);
        $this->assertSame($this->otherBranch->id, $overview['teacher_attention']->first()['branch_id']);
    }

    public function test_superadmin_overview_groups_pending_by_report_owner_and_report_branch(): void
    {
        $student = $this->student('Siswa A tambahan', $this->branch, $this->teacher);
        $this->report($student, $this->branch, $this->teacher);
        $this->otherReport->forceFill(['teacher_id' => $this->teacher->id])->save();

        $overview = $this->actingAs($this->superadmin)->get(route('superadmin.dashboard'))->assertOk()->viewData('dashboardOverview');
        $this->assertCount(2, $overview['teacher_attention']);
        $groups = $overview['teacher_attention']->keyBy('branch_id');
        $this->assertSame(2, $groups[$this->branch->id]['pending']);
        $this->assertSame(1, $groups[$this->otherBranch->id]['pending']);
        $this->assertSame($this->teacher->id, $groups[$this->otherBranch->id]['teacher_id']);
        $this->assertSame($this->otherBranch->name, $groups[$this->otherBranch->id]['branch_name']);
    }

    public function test_superadmin_overview_prioritizes_overdue_and_limits_attention_rows(): void
    {
        $this->otherReport->forceFill(['due_on' => '2026-10-11'])->save();
        for ($i = 0; $i < 3; $i++) {
            $teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', 'Teacher tambahan '.$i)->id,
                'whatsapp_number' => '08123456789'.$i, 'is_active' => true]);
            $student = $this->student('Siswa tambahan '.$i, $this->branch, $teacher);
            $this->report($student, $this->branch, $teacher);
        }

        $overview = $this->actingAs($this->superadmin)->get(route('superadmin.dashboard'))->assertOk()
            ->assertSee('Menampilkan 3 dari 5')->viewData('dashboardOverview');
        $this->assertSame(5, $overview['teacher_attention_total']);
        $this->assertCount(3, $overview['teacher_attention']);
        $this->assertSame($this->otherTeacher->id, $overview['teacher_attention']->first()['teacher_id']);
        $this->assertSame(5, $overview['summary']['pending']);
        $this->assertSame(1, $overview['summary']['overdue']);
    }

    public function test_branch_overview_counts_distinct_active_students_and_date_boundaries(): void
    {
        $secondProgram = $this->program->replicate();
        $secondProgram->forceFill(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Program kedua'])->save();
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'branch_id' => $this->branch->id,
            'program_id' => $secondProgram->id, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']);
        $inactive = $this->student('Siswa nonaktif', $this->branch, $this->teacher);
        $inactive->forceFill(['status' => 'inactive'])->save();
        $future = $this->student('Siswa belum mulai', $this->branch, $this->teacher);
        $future->forceFill(['began_on' => '2026-10-13'])->save();
        $ended = $this->student('Pendaftaran selesai', $this->branch, $this->teacher);
        StudentEnrollment::where('student_id', $ended->id)->update(['ends_on' => '2026-10-11']);
        $futureEnrollment = $this->student('Pendaftaran mendatang', $this->branch, $this->teacher);
        StudentEnrollment::where('student_id', $futureEnrollment->id)->update(['starts_on' => '2026-10-13']);
        $boundary = $this->student('Pendaftaran hari ini', $this->branch, $this->teacher);
        StudentEnrollment::where('student_id', $boundary->id)->update(['starts_on' => '2026-10-12', 'ends_on' => '2026-10-12']);
        $zero = $this->branch('Cabang tanpa siswa', 'Asia/Makassar');
        $this->otherBranch->forceFill(['is_active' => false])->save();

        $overview = $this->actingAs($this->superadmin)->get(route('superadmin.dashboard'))->assertOk()->viewData('dashboardOverview');
        $branches = $overview['branches']->keyBy('id');
        $this->assertCount(2, $branches);
        $this->assertSame(2, $branches[$this->branch->id]['students']);
        $this->assertSame(0, $branches[$zero->id]['students']);
        $this->assertFalse($branches->has($this->otherBranch->id));
        $this->assertSame(2, $overview['branch_max']);
        $this->assertSame($this->branch->id, $overview['branches']->first()['id']);
        $this->assertSame('12/10/2026', $overview['branch_as_of']);
    }

    public function test_overview_period_selector_does_not_change_existing_current_month_metrics(): void
    {
        $this->report->forceFill(['status' => 'submitted'])->save();
        $this->report($this->student, $this->branch, $this->teacher, '2026-11-01');

        $response = $this->actingAs($this->superadmin)->get(route('superadmin.dashboard', ['month' => '2026-11']))
            ->assertOk()->assertSee('November 2026')->assertSee('Laporan diajukan bulan ini');
        $this->assertSame('2026-10', $response->viewData('monitoring')['filters']['month']);
        $this->assertSame(1, $response->viewData('monitoring')['summary']['submitted']);
        $overview = $response->viewData('dashboardOverview');
        $this->assertSame('2026-11', $overview['month']);
        $this->assertSame(1, $overview['summary']['pending']);
        $this->assertSame(0, $overview['summary']['submitted']);
        $this->assertSame(0, $overview['summary']['overdue']);
        $this->assertSame('12/10/2026', $overview['branch_as_of']);
        $this->assertSame(2, $overview['branches']->sum('students'));
    }

    public function test_overview_empty_period_and_no_active_branches_are_zero_and_readable(): void
    {
        Branch::query()->update(['is_active' => false]);
        $response = $this->actingAs($this->superadmin)->get(route('superadmin.dashboard', ['month' => '2026-11']))
            ->assertOk()->assertSee('Belum ada periode rapor pada bulan ini.')->assertSee('Belum ada cabang aktif');
        $overview = $response->viewData('dashboardOverview');
        $this->assertSame(0, $overview['summary']['total']);
        $this->assertSame(0, $overview['summary']['pending']);
        $this->assertSame(0, $overview['summary']['overdue']);
        $this->assertCount(0, $overview['branches']);
        $this->assertSame(1, $overview['branch_max']);
    }

    public function test_overview_zero_students_never_uses_a_zero_progress_maximum(): void
    {
        Student::query()->update(['status' => 'inactive']);
        $overview = $this->actingAs($this->superadmin)->get(route('superadmin.dashboard'))->assertOk()->viewData('dashboardOverview');
        $this->assertCount(2, $overview['branches']);
        $this->assertSame(0, $overview['branches']->sum('students'));
        $this->assertSame(1, $overview['branch_max']);
    }

    public function test_superadmin_overview_does_not_change_report_or_enrollment_data(): void
    {
        $beforeReports = MonthlyReport::orderBy('id')->get()->map->getAttributes()->all();
        $beforeEnrollments = StudentEnrollment::orderBy('id')->get()->map->getAttributes()->all();
        $this->actingAs($this->superadmin)->get(route('superadmin.dashboard'))->assertOk()->assertDontSee('RAHASIA TEACHER')->assertDontSee('RAHASIA PROFIL');
        $this->assertSame($beforeReports, MonthlyReport::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($beforeEnrollments, StudentEnrollment::orderBy('id')->get()->map->getAttributes()->all());
        Http::assertNothingSent();
    }

    public function test_other_role_dashboards_do_not_load_superadmin_overview(): void
    {
        foreach ([$this->admin, $this->teacher->user] as $actor) {
            $this->actingAs($actor)->get(route($actor->role_code.'.dashboard'))->assertOk()
                ->assertDontSee('Monitoring rapor bulanan')->assertDontSee('dashboard-overview.css');
            $this->get(route('superadmin.dashboard'))->assertForbidden();
        }
        $this->actingAs($this->student->portalUser)->get(route('superadmin.dashboard'))->assertForbidden();
    }

    public static function invalidOverviewMonths(): array
    {
        return [['2026-13'], ['2026-00'], ['2026-10-01'], ['1999-12'], ['2101-01'], [['2026-10']], ['10/2026']];
    }

    public function test_superadmin_visiting_other_allowed_dashboards_keeps_their_layout(): void
    {
        foreach (['admin.dashboard', 'teacher.dashboard'] as $route) {
            $this->actingAs($this->superadmin)->get(route($route))->assertOk()
                ->assertDontSee('Monitoring rapor bulanan')->assertDontSee('dashboard-overview.css');
        }
        $this->get(route('superadmin.dashboard'))->assertOk()->assertSee('Monitoring rapor bulanan')->assertSee('dashboard-overview.css');
    }

    #[DataProvider('invalidOverviewMonths')]
    public function test_superadmin_overview_rejects_invalid_periods(mixed $month): void
    {
        $this->actingAs($this->superadmin)->getJson(route('superadmin.dashboard', ['month' => $month]))
            ->assertUnprocessable()->assertJsonValidationErrors('month');
    }

    public function test_superadmin_overview_accepts_supported_period_boundaries(): void
    {
        foreach (['2000-01', '2100-12'] as $month) {
            $overview = $this->actingAs($this->superadmin)->get(route('superadmin.dashboard', ['month' => $month]))->assertOk()->viewData('dashboardOverview');
            $this->assertSame($month, $overview['month']);
            $this->assertSame(0, $overview['summary']['total']);
        }
    }

    #[DataProvider('staffRoles')]
    public function test_monthly_list_count_and_teacher_summary_use_identical_scope(string $role): void
    {
        $actor = match ($role) { 'superadmin' => $this->superadmin, 'admin' => $this->admin, default => $this->teacher->user };
        $this->report->update(['video_target' => 2, 'due_on' => '2026-10-10']);
        $data = app(OperationsMonitoringService::class)->data($actor);
        $expected = $role === 'superadmin' ? 2 : 1;
        $this->assertSame($expected, $data['summary']['total']);
        $this->assertSame($expected, $data['rows']->count());
        $this->assertSame($expected, $data['teacherSummary']->sum('summary.total'));
        $this->assertSame(1, $data['summary']['overdue']);
        $this->assertSame(1, $data['summary']['video_missing']);
        $this->assertSame($expected, $data['summary']['unpaid']);
    }

    public function test_teacher_does_not_need_extra_branch_assignment(): void
    {
        $this->assertSame(0, $this->teacher->branchAssignments()->count());
        $this->actingAs($this->teacher->user)->get(route('monitoring.index'))->assertOk()->assertSee('Siswa A Uji')->assertDontSee('Siswa B Rahasia')->assertDontSee('Cabang B Rahasia');
        $this->get(route('inbox.index'))->assertOk()->assertSee('Siswa A Uji')->assertDontSee('Siswa B Rahasia');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertViewHas('activeTeachers', 1);
    }

    public function test_admin_cannot_select_foreign_branch_or_teacher(): void
    {
        $this->actingAs($this->admin)->get(route('monitoring.index', ['branch_id' => $this->otherBranch->id]))->assertForbidden();
        $this->get(route('monitoring.index', ['teacher_id' => $this->otherTeacher->id]))->assertForbidden();
        $this->get(route('monitoring.index'))->assertOk()->assertDontSee('Siswa B Rahasia')->assertDontSee('Teacher B Uji');
    }

    public function test_expired_admin_assignment_revokes_notifications_and_monitoring(): void
    {
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-10-11']);
        $this->actingAs($this->admin)->get(route('inbox.index'))->assertOk()->assertDontSee('Siswa A Uji');
        $this->get(route('monitoring.index'))->assertOk()->assertDontSee('Siswa A Uji');
        $this->assertCount(0, app(InAppNotificationService::class)->items($this->admin));
    }

    public function test_inactive_teacher_profile_has_no_report_or_payment_notifications(): void
    {
        $charge = $this->charge();
        $this->paymentEvent($charge, 'approved');
        $this->teacher->update(['is_active' => false]);
        $this->assertCount(0, app(InAppNotificationService::class)->items($this->teacher->user));
        $this->actingAs($this->teacher->user)->get(route('monitoring.index'))->assertOk()->assertDontSee('Siswa A Uji');
    }

    public function test_fresh_user_state_rejects_a_deactivated_account(): void
    {
        $stale = $this->teacher->user;
        User::whereKey($stale->id)->update(['is_active' => false]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InAppNotificationService::class)->items($stale);
    }

    public function test_read_receipt_is_personal_and_idempotent(): void
    {
        $this->approve();
        $teacher = $this->teacher->user;
        $service = app(InAppNotificationService::class);
        $item = $service->items($teacher)->firstWhere('title', 'Laporan bulanan disetujui');
        $this->actingAs($teacher)->post(route('inbox.read', $item['key']))->assertRedirect(route('inbox.index'));
        $first = DB::table('in_app_notification_reads')->where('user_id', $teacher->id)->value('read_at');
        Carbon::setTestNow(now()->addMinute());
        $this->post(route('inbox.read', $item['key']))->assertRedirect();
        $this->assertSame(1, DB::table('in_app_notification_reads')->where('user_id', $teacher->id)->count());
        $this->assertSame($first, DB::table('in_app_notification_reads')->where('user_id', $teacher->id)->value('read_at'));
        $this->assertTrue($service->items($teacher)->firstWhere('key', $item['key'])['read']);
        $this->assertFalse($service->items($this->student->portalUser)->firstWhere('key', $item['key'])['read']);
    }

    public function test_unrelated_user_cannot_read_or_open_a_notification_key(): void
    {
        $item = app(InAppNotificationService::class)->items($this->teacher->user)->first();
        $this->actingAs($this->otherTeacher->user)->post(route('inbox.read', $item['key']))->assertNotFound();
        $this->post(route('inbox.open', $item['key']))->assertNotFound();
        $this->assertDatabaseCount('in_app_notification_reads', 0);
    }

    public function test_permissions_are_rechecked_when_an_old_notification_is_opened(): void
    {
        $item = app(InAppNotificationService::class)->items($this->teacher->user)->first();
        $this->report->update(['teacher_id' => $this->otherTeacher->id]);
        $this->actingAs($this->teacher->user)->post(route('inbox.open', $item['key']))->assertNotFound();
        $this->assertDatabaseCount('in_app_notification_reads', 0);
    }

    public function test_open_uses_server_path_and_preserves_report_access_rules(): void
    {
        $this->approve();
        $student = $this->student->portalUser;
        $item = app(InAppNotificationService::class)->items($student)->first();
        $this->actingAs($student)->post(route('inbox.open', $item['key']), ['path' => 'https://untrusted.example'])->assertRedirect(route('student.reports.monthly', $this->report, false));
        $this->get(route('student.reports.monthly', $this->report))->assertOk()->assertSee('Pembayaran bulan ini belum tervalidasi')->assertDontSee('RAHASIA TEACHER')->assertDontSee('PERKEMBANGAN PUBLIK');
    }

    public function test_mark_page_only_marks_reviewed_keys_not_new_items(): void
    {
        $teacher = $this->teacher->user;
        $service = app(InAppNotificationService::class);
        $keys = $service->items($teacher)->pluck('key')->all();
        $this->notification($teacher, 'Baru tiba');
        $this->actingAs($teacher)->post(route('inbox.read-page'), ['keys' => $keys])->assertRedirect();
        $this->assertFalse($service->items($teacher)->firstWhere('title', 'Baru tiba')['read']);
    }

    public function test_batch_read_is_atomic_for_forged_keys(): void
    {
        $teacher = $this->teacher->user;
        $key = app(InAppNotificationService::class)->items($teacher)->first()['key'];
        $this->actingAs($teacher)->post(route('inbox.read-page'), ['keys' => [$key, str_repeat('f', 64)]])->assertNotFound();
        $this->assertDatabaseCount('in_app_notification_reads', 0);
    }

    public function test_get_requests_never_mark_read_and_responses_are_private(): void
    {
        $this->actingAs($this->teacher->user)->get(route('inbox.index'))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->get(route('monitoring.index'))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertDatabaseCount('in_app_notification_reads', 0);
        $this->assertDatabaseCount('report_status_events', 0);
    }

    public function test_submitted_and_revision_notifications_follow_real_workflow(): void
    {
        $workflow = app(MonthlyReportWorkflowService::class);
        $submitted = $workflow->submit($this->report, $this->teacher->user);
        $this->actingAs($this->admin)->get(route('inbox.index'))->assertOk()->assertSee('Laporan bulanan menunggu tinjauan');
        $workflow->requestRevision($submitted, $this->admin, 'Perbaiki isi');
        $this->actingAs($this->teacher->user)->get(route('inbox.index'))->assertOk()->assertSee('Laporan bulanan perlu direvisi');
        $this->actingAs($this->admin)->get(route('inbox.index'))->assertOk()->assertDontSee('Laporan bulanan menunggu tinjauan');
    }

    public function test_resolved_reminders_disappear_and_changed_conditions_get_new_keys(): void
    {
        $service = app(InAppNotificationService::class);
        $teacher = $this->teacher->user;
        $near = $service->items($teacher)->firstWhere('title', 'Tenggat laporan mendekat');
        $service->read($teacher, [$near['key']]);
        $this->report->update(['due_on' => '2026-10-10']);
        $late = $service->items($teacher)->firstWhere('title', 'Tenggat laporan terlewati');
        $this->assertNotSame($near['key'], $late['key']);
        $this->assertFalse($late['read']);
        $this->approve();
        $items = $service->items($teacher);
        $this->assertNull($items->firstWhere('title', 'Tenggat laporan terlewati'));
    }

    public function test_global_reminder_toggle_hides_only_reminders_and_keeps_read_receipts(): void
    {
        $service = app(InAppNotificationService::class);
        $teacher = $this->teacher->user;
        Notification::forceCreate(['id' => (string) Str::uuid(), 'user_id' => $teacher->id, 'notification_type' => 'general', 'title' => 'Pemberitahuan tetap', 'body' => 'Isi pemberitahuan', 'created_at' => now()]);
        $before = $service->items($teacher);
        $reminder = $before->firstWhere('type', 'reminders');
        $this->assertNotNull($reminder);
        $service->read($teacher, [$reminder['key']]);
        \App\Models\SystemSetting::create(['id' => 1, 'organization_name' => 'DPE', 'default_report_due_day' => 31,
            'default_monthly_video_target' => 4, 'in_app_reminders_enabled' => false]);
        $disabled = $service->items($teacher);
        $this->assertCount(0, $disabled->where('type', 'reminders'));
        $this->assertNotNull($disabled->firstWhere('title', 'Pemberitahuan tetap'));
        $this->assertDatabaseHas('in_app_notification_reads', ['user_id' => $teacher->id, 'notification_key' => $reminder['key']]);
        \App\Models\SystemSetting::find(1)->update(['in_app_reminders_enabled' => true]);
        $this->assertTrue($service->items($teacher)->firstWhere('key', $reminder['key'])['read']);
    }

    public function test_video_count_excludes_future_unsafe_and_before_join_date_videos(): void
    {
        $this->report->update(['video_target' => 2]);
        foreach ([['2026-10-05', 'https://example.test/video'], ['2026-10-20', 'https://example.test/future'], ['2026-10-05', 'javascript:alert(1)']] as [$date, $url]) {
            LearningVideo::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'video_on' => $date, 'topic' => 'Video uji', 'video_url' => $url]);
        }
        $state = app(OperationsMonitoringService::class)->videoState($this->report->fresh());
        $this->assertSame(1, $state['count']);
        $this->assertSame(1, $state['missing']);
        $this->student->update(['began_on' => '2026-10-06']);
        $this->assertSame(0, app(OperationsMonitoringService::class)->videoState($this->report->fresh())['count']);
    }

    public function test_approved_video_count_uses_frozen_snapshot(): void
    {
        $approved = $this->approve();
        LearningVideo::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'video_on' => '2026-10-05', 'topic' => 'Video sesudah persetujuan', 'video_url' => 'https://example.test/video']);
        $state = app(OperationsMonitoringService::class)->videoState($approved->fresh());
        $this->assertSame(0, $state['count']);
        $this->assertSame(0, $state['target']);
    }

    public function test_duplicate_charges_do_not_show_paid_even_with_approved_proof(): void
    {
        $charge = $this->charge();
        $this->proof($charge, 'approved');
        $this->assertSame(0, app(OperationsMonitoringService::class)->data($this->teacher->user)['summary']['unpaid']);
        $this->charge(month: '2026-10-15');
        $this->assertSame(1, app(OperationsMonitoringService::class)->data($this->teacher->user)['summary']['unpaid']);
    }

    private function paymentEvent(MonthlyCharge $charge, string $status): void
    {
        app(PortalPaymentService::class)->event($this->superadmin, 'payment_reviewed', $charge, null, null, ['status' => $status, 'unsafe_internal' => 'RAHASIA SNAPSHOT'], 'RAHASIA CATATAN');
    }

    public function test_pending_proof_is_for_superadmin_and_payment_result_has_correct_recipients(): void
    {
        $charge = $this->charge();
        $proof = $this->proof($charge);
        $service = app(InAppNotificationService::class);
        $this->assertNotNull($service->items($this->superadmin)->firstWhere('title', 'Bukti pembayaran menunggu validasi'));
        $this->assertNull($service->items($this->admin)->firstWhere('title', 'Bukti pembayaran menunggu validasi'));
        $this->assertNull($service->items($this->teacher->user)->firstWhere('title', 'Bukti pembayaran menunggu validasi'));
        $proof->update(['status' => 'rejected']);
        $this->paymentEvent($charge, 'rejected');
        foreach ([$this->student->portalUser, $this->teacher->user, $this->admin] as $actor) {
            $this->assertNotNull($service->items($actor)->firstWhere('title', 'Bukti pembayaran ditolak'));
            $this->actingAs($actor)->get(route('inbox.index'))->assertOk()->assertDontSee('RAHASIA SNAPSHOT')->assertDontSee('RAHASIA CATATAN');
        }
        $this->assertNull($service->items($this->otherStudent->portalUser)->firstWhere('title', 'Bukti pembayaran ditolak'));
        $this->assertNull($service->items($this->otherTeacher->user)->firstWhere('title', 'Bukti pembayaran ditolak'));
    }

    public function test_teacher_payment_scope_matches_calendar_month_not_charge_day(): void
    {
        $charge = $this->charge(month: '2026-10-15');
        $this->paymentEvent($charge, 'approved');
        $this->assertNotNull(app(InAppNotificationService::class)->items($this->teacher->user)->firstWhere('title', 'Pembayaran tervalidasi'));
        $this->paymentEvent($this->charge(month: '2026-09-01'), 'approved');
        $this->assertSame(1, app(InAppNotificationService::class)->items($this->teacher->user)->where('type', 'payments')->count());
    }

    public function test_attendance_notifications_do_not_expose_photos_or_coordinates(): void
    {
        $attendance = TeacherAttendance::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id,
            'attendance_mode' => 'branch_visit', 'attendance_date' => '2026-10-12', 'status' => 'pending_review', 'attendance_type' => 'present', 'check_in_latitude' => '12.1234567', 'manual_note' => 'RAHASIA LOKASI']);
        $this->actingAs($this->admin)->get(route('inbox.index'))->assertOk()->assertSee('Kehadiran Teacher perlu diperiksa')->assertDontSee('12.1234567')->assertDontSee('RAHASIA LOKASI');
        $attendance->update(['status' => 'needs_correction', 'reviewed_at' => now()]);
        $this->actingAs($this->teacher->user)->get(route('inbox.index'))->assertOk()->assertSee('Kehadiran Teacher perlu dikoreksi');
        $this->actingAs($this->otherTeacher->user)->get(route('inbox.index'))->assertOk()->assertDontSee('Kehadiran Teacher perlu dikoreksi');
    }

    public function test_student_has_only_own_notifications_and_cannot_open_staff_monitoring(): void
    {
        $this->approve();
        $this->approve($this->otherReport);
        $this->actingAs($this->student->portalUser)->get(route('inbox.index'))->assertOk()->assertSee('Laporan bulanan disetujui')->assertDontSee('Siswa B Rahasia')->assertDontSee('RAHASIA PROFIL')->assertDontSee('Target video perlu dilengkapi');
        $this->get(route('monitoring.index'))->assertForbidden();
    }

    public function test_legacy_notification_is_escaped_and_arbitrary_action_is_not_followed(): void
    {
        $teacher = $this->teacher->user;
        $notification = $this->notification($teacher, '<script>alert(1)</script>');
        $notification->update(['action_path' => 'https://untrusted.example/steal']);
        $response = $this->actingAs($teacher)->get(route('inbox.index'));
        $response->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('https://untrusted.example/steal');
        $key = app(InAppNotificationService::class)->items($teacher)->firstWhere('type', 'system')['key'];
        $this->post(route('inbox.open', $key))->assertNotFound();
        $this->assertDatabaseCount('in_app_notification_reads', 0);
    }

    public function test_history_window_keeps_old_actionable_reminders_but_not_old_events(): void
    {
        $this->notification($this->teacher->user, 'Riwayat lama');
        DB::table('notifications')->update(['created_at' => now()->subDays(91)]);
        $this->report->update(['due_on' => '2026-01-01']);
        $this->actingAs($this->teacher->user)->get(route('inbox.index'))->assertOk()->assertSee('Tenggat laporan terlewati')->assertDontSee('Riwayat lama');
    }

    public function test_filters_and_pagination_keep_totals_consistent(): void
    {
        $this->report->update(['status' => 'submitted']);
        $this->actingAs($this->superadmin)->get(route('monitoring.index', ['status' => 'submitted']))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 1 && $summary['submitted'] === 1);
        $this->get(route('monitoring.index', ['q' => 'Siswa B']))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 1)->assertDontSee('Siswa A Uji</strong>', false);
        $this->get(route('monitoring.index', ['month' => '2026-11']))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 0);
        for ($i = 0; $i < 17; $i++) {
            $this->notification($this->teacher->user, 'Pesan '.$i);
        }
        $this->actingAs($this->teacher->user)->get(route('inbox.index', ['type' => 'system', 'page' => 2]))->assertOk()->assertViewHas('notifications', fn ($paginator) => $paginator->total() === 17 && $paginator->count() === 2 && $paginator->currentPage() === 2);
    }

    public static function invalidFilters(): array
    {
        return [['month', '2026-99'], ['month', '9999-12'], ['branch_id', 'bad-id'], ['teacher_id', 'bad-id'], ['status', 'sent'], ['page', '-1'], ['q', ['array']]];
    }

    private function notification(User $user, string $title): Notification
    {
        $notification = Notification::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'notification_type' => 'system', 'title' => $title, 'body' => 'Pesan khusus']);
        $notification->forceFill(['created_at' => now()])->save();

        return $notification;
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_monitoring_filters_are_rejected(string $field, mixed $value): void
    {
        $this->actingAs($this->admin)->getJson(route('monitoring.index', [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_new_read_table_prevents_destructive_rollback(): void
    {
        $migration = require database_path('migrations/2026_10_12_140000_create_in_app_notification_reads.php');
        $key = app(InAppNotificationService::class)->items($this->teacher->user)->first()['key'];
        app(InAppNotificationService::class)->read($this->teacher->user, [$key]);
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_csrf_is_required_for_read_actions(): void
    {
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken
            {
                protected function runningUnitTests() { return false; }
            };
        });
        $key = app(InAppNotificationService::class)->items($this->teacher->user)->first()['key'];
        $this->actingAs($this->teacher->user)->post(route('inbox.read', $key))->assertStatus(419);
        $this->assertDatabaseCount('in_app_notification_reads', 0);
        $this->withSession(['_token' => 'fixture-token'])->post(route('inbox.read', $key), ['_token' => 'fixture-token'])->assertRedirect();
    }

    public function test_an_older_attendance_correction_opens_its_exact_record_with_ownership_checks(): void
    {
        $record = TeacherAttendance::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id,
            'attendance_mode' => 'branch_visit', 'attendance_date' => '2026-01-01', 'status' => 'needs_correction', 'attendance_type' => 'present', 'review_note' => 'Periksa waktu kunjungan lama']);
        $this->actingAs($this->teacher->user)->get(route('inbox.attendance', $record))->assertOk()->assertSee('Periksa waktu kunjungan lama');
        $this->actingAs($this->otherTeacher->user)->get(route('inbox.attendance', $record))->assertNotFound();
        $this->actingAs($this->student->portalUser)->get(route('inbox.attendance', $record))->assertForbidden();
    }

    private function publishFinal(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-12-20 10:00:00', 'Asia/Makassar'));
        $this->report($this->student, $this->branch, $this->teacher, '2026-11-01');
        $this->report($this->student, $this->branch, $this->teacher, '2026-12-01');
        foreach ($this->student->monthlyReports()->get() as $monthly) {
            $this->approve($monthly);
            $this->proof($this->charge(month: $monthly->report_month->toDateString()), 'approved');
        }
        $workflow = app(QuarterlyReportWorkflowService::class);
        $content = app(QuarterlyReportContentService::class);
        $cycle = $this->report->cycle;
        $report = $workflow->prepare($this->admin, $cycle, ['teacher_id' => $this->teacher->id, 'expected_snapshot' => $content->preparationSignature($cycle)]);
        $report = $workflow->save($this->teacher->user, $report, ['skills' => collect(QuarterlyReportContentService::SKILLS)->map(fn ($name) => ['score' => 'B', 'description' => 'FINAL PUBLIK '.$name])->all(),
            'parent_note' => 'NOTE PUBLIK', 'expected_snapshot' => $content->signature($report)]);
        $report = $workflow->transition($this->teacher->user, $report, 'submit', ['expected_snapshot' => $content->signature($report)]);
        $report = $workflow->transition($this->admin, $report, 'approve', ['expected_snapshot' => $content->signature($report)]);
        $file = app(QuarterlyReportPdfService::class)->generate($this->teacher->user, $report, ['expected_snapshot' => $content->signature($report)]);

        return [$report, $file];
    }

    public function test_final_monitoring_preserves_all_three_payment_gates_and_verifies_pdf_bytes(): void
    {
        [$final, $file] = $this->publishFinal();
        $service = app(OperationsMonitoringService::class);
        $data = $service->data($this->teacher->user, ['month' => '2026-10']);
        $this->assertSame($final->id, $data['finalRows']->first()['report']->id);
        $this->assertTrue($data['finalRows']->first()['portal']['ready']);
        $this->actingAs($this->teacher->user)->get(route('monitoring.index', ['month' => '2026-10']))->assertOk()->assertSee('Tersedia')->assertDontSee('RAHASIA TEACHER')->assertDontSee('@endforeach');
        $this->student->monthlyCharges()->whereDate('charge_month', '2026-11-01')->first()->paymentProofs()->update(['status' => 'rejected']);
        $this->assertFalse($service->data($this->teacher->user, ['month' => '2026-10'])['finalRows']->first()['portal']['ready']);
        $this->student->monthlyCharges()->whereDate('charge_month', '2026-11-01')->first()->paymentProofs()->update(['status' => 'approved']);
        Storage::disk('local')->put($file->file->storage_key, 'tampered');
        $state = $service->data($this->teacher->user, ['month' => '2026-10'])['finalRows']->first()['portal'];
        $this->assertFalse($state['ready']);
        $this->assertStringContainsString('PDF final belum tersedia', implode(' ', $state['reasons']));
    }

    public function test_final_approval_notification_does_not_include_private_notes(): void
    {
        [$final] = $this->publishFinal();
        $this->actingAs($this->student->portalUser)->get(route('inbox.index'))->assertOk()->assertSee('Rapor tiga bulan disetujui')->assertDontSee('RAHASIA TEACHER')->assertDontSee('FINAL PUBLIK')->assertDontSee('NOTE PUBLIK');
        $this->actingAs($this->otherTeacher->user)->get(route('inbox.index'))->assertOk()->assertDontSee('Rapor tiga bulan disetujui');
    }

    public function test_final_deadline_is_separate_from_monthly_status(): void
    {
        $cycle = $this->report->cycle;
        $cycle->update(['share_due_on' => '2026-10-11']);
        $final = QuarterlyReport::create(['id' => (string) Str::uuid(), 'cycle_id' => $cycle->id, 'student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id,
            'status' => 'draft', 'revision_number' => 1, 'skills' => []]);
        $this->actingAs($this->teacher->user)->get(route('inbox.index'))->assertOk()->assertSee('Tenggat rapor tiga bulan terlewati');
        $data = app(OperationsMonitoringService::class)->data($this->teacher->user);
        $this->assertSame('overdue', $data['finalRows']->first()['deadline']);
        $this->assertSame(0, $data['summary']['overdue']);
        $final->update(['status' => 'approved']);
        $this->assertNull(app(InAppNotificationService::class)->items($this->teacher->user)->firstWhere('title', 'Tenggat rapor tiga bulan terlewati'));
    }

    public function test_notification_badge_and_status_filter_follow_read_receipt(): void
    {
        $this->approve();
        $actor = $this->teacher->user;
        $key = app(InAppNotificationService::class)->items($actor)->first()['key'];
        $this->actingAs($actor)->get(route('inbox.index'))->assertOk()->assertSee('Buka notifikasi, 1 belum dibaca');
        $this->post(route('inbox.read', $key))->assertRedirect();
        $this->get(route('inbox.index', ['read' => 'unread']))->assertOk()->assertSee('Buka notifikasi, 0 belum dibaca')->assertViewHas('notifications', fn ($rows) => $rows->total() === 0);
        $this->get(route('inbox.index', ['read' => 'read']))->assertOk()->assertViewHas('notifications', fn ($rows) => $rows->total() === 1);
    }

    public function test_branch_timezone_controls_deadline_at_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 23:30:00', 'Asia/Jakarta'));
        $service = app(OperationsMonitoringService::class);
        $this->assertSame('overdue', $service->deadline($this->report->fresh()));
        $this->assertSame('near', $service->deadline($this->otherReport->fresh()));
    }

    public function test_actual_html_forms_are_exportable_for_css_and_confirmation_review(): void
    {
        $teacher = $this->teacher->user;
        $inbox = $this->actingAs($teacher)->get(route('inbox.index'))->assertOk()->assertDontSee('@endif');
        $monitor = $this->get(route('monitoring.index'))->assertOk();
        $proof = $this->proof($this->charge());
        $review = $this->actingAs($this->superadmin)->get(route('superadmin.payment-review.show', $proof))->assertOk();
        $settings = $this->get(route('superadmin.portal-settings.index'))->assertOk();
        $path = getenv('DPE_V42_HTML_EXPORT');
        if ($path) {
            if (! is_dir($path)) { mkdir($path, 0700, true); }
            foreach (['inbox' => $inbox, 'monitoring' => $monitor, 'payment-review' => $review, 'portal-settings' => $settings] as $name => $response) {
                file_put_contents($path.'/'.$name.'.html', $response->getContent());
            }
        }
    }

    public static function videoTargetPeriods(): array
    {
        return [
            ['2026-10-01', '2026-10-01', '2026-10-12', 0, 0, false],
            ['2026-10-06', '2026-10-01', '2026-10-12', 0, null, true],
            ['2026-10-06', '2026-11-01', '2026-11-20', 0, 0, false],
            ['2026-10-15', '2026-10-01', '2026-10-12', 0, null, false],
            ['2026-10-01', '2026-10-01', '2026-10-12', 4, 4, true],
        ];
    }

    #[DataProvider('videoTargetPeriods')]
    public function test_default_and_first_partial_month_video_targets_match_existing_workflow(string $join, string $month, string $today, int $target, ?int $expected, bool $reminder): void
    {
        Carbon::setTestNow(Carbon::parse($today.' 10:00:00', 'Asia/Makassar'));
        $this->student->update(['began_on' => $join]);
        $this->report->update(['report_month' => $month, 'video_target' => $target, 'video_target_confirmed_at' => null, 'video_target_confirmed_by_user_id' => null]);
        $state = app(OperationsMonitoringService::class)->videoState($this->report->fresh());
        $this->assertSame($expected, $state['target']);
        $message = app(InAppNotificationService::class)->items($this->teacher->user)->firstWhere('title', 'Target video perlu dilengkapi');
        $this->assertSame($reminder, $message !== null);
        if ($message && $expected === 4) {
            $this->assertStringContainsString('0 / 4 video', $message['body']);
        }
    }
}
