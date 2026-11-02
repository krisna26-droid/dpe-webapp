<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\ClassLearningSession;
use App\Models\ClassLearningSessionEnrollment;
use App\Models\LessonSession;
use App\Models\MonthlyReport;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\SystemSetting;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\AutomaticMonthlyReportService;
use App\Services\LessonSeriesService;
use App\Services\StudentAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientRevisionsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $superadmin;
    private Branch $branch;
    private Program $program;
    private Teacher $teacher;
    private Student $student;
    private ClassGroup $group;
    private ClassLearningSession $learningSession;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Makassar']);
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:00:00', 'Asia/Makassar'));
        $this->admin = $this->user('admin');
        $this->superadmin = $this->user('superadmin');
        $this->branch = $this->branch('Cabang Utama');
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => 'REG', 'name' => 'English', 'class_type' => 'regular', 'is_active' => true]);
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->teacher = $this->teacher($this->branch);
        $this->student = $this->student($this->branch);
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'starts_on' => '2026-09-01', 'is_report_owner' => true]);
        $this->group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'default_teacher_id' => $this->teacher->id, 'code' => 'A', 'name' => 'Kelas A', 'is_active' => true]);
        $this->learningSession = ClassLearningSession::create(['id' => (string) Str::uuid(), 'class_group_id' => $this->group->id, 'name' => 'Sesi 1', 'slot_capacity' => 4]);
        $membership = ClassGroupMembership::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'class_group_id' => $this->group->id, 'starts_on' => '2026-09-01', 'session_selection_from' => '2026-09-01']);
        ClassLearningSessionEnrollment::create(['id' => (string) Str::uuid(), 'class_group_membership_id' => $membership->id, 'class_learning_session_id' => $this->learningSession->id, 'weekday' => 1, 'starts_on' => '2026-09-01']);
        SystemSetting::create(['id' => 1, 'organization_name' => 'DPE', 'default_report_due_day' => 28, 'default_monthly_video_target' => 4]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'full_name' => ucfirst($role).' Uji', 'password_hash' => 'fixture-hash', 'role_code' => $role, 'is_active' => true]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => $name, 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function teacher(Branch $branch): Teacher
    {
        $teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher')->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $teacher->id, 'branch_id' => $branch->id, 'starts_on' => '2026-01-01']);

        return $teacher;
    }

    private function student(Branch $branch, string $began = '2026-09-01'): Student
    {
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student')->id, 'full_name' => 'Siswa '.Str::random(8), 'began_on' => $began, 'status' => 'active']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $branch->id, 'program_id' => $this->program->id, 'starts_on' => $began]);

        return $student;
    }

    private function lesson(string $start = '2026-11-02 09:00:00'): LessonSession
    {
        return LessonSession::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'class_group_id' => $this->group->id, 'class_learning_session_id' => $this->learningSession->id, 'teaching_location' => 'branch', 'planned_start_at' => $start, 'planned_end_at' => Carbon::parse($start)->addHour(), 'status' => 'scheduled']);
    }

    public function test_teacher_gets_current_month_draft_without_admin_preparing_it_and_without_duplicates(): void
    {
        $this->actingAs($this->teacher->user)->get(route('teacher.monthly-reports.index'))->assertOk()->assertSee($this->student->full_name);
        $report = MonthlyReport::sole();
        $this->assertSame('2026-11-01', $report->report_month->toDateString());
        $this->assertSame($this->teacher->id, $report->teacher_id);
        $this->assertSame('2026-09-01', $report->cycle->start_month->toDateString());
        $this->assertSame('draft', $report->status);
        $this->get(route('teacher.student-progress.index'))->assertOk()->assertSee('Buka perkembangan')->assertDontSee('Catatan Belajar');
        $this->get(route('teacher.monthly-reports.index'))->assertOk();
        $this->assertDatabaseCount('monthly_reports', 1);
        $this->assertDatabaseHas('monthly_progress_audits', ['report_id' => $report->id, 'event_type' => 'prepared_automatically']);
    }

    public function test_new_month_uses_latest_defaults_and_preserves_previous_report(): void
    {
        $service = app(AutomaticMonthlyReportService::class);
        $service->ensureFor($this->superadmin);
        $old = MonthlyReport::sole();
        $old->update(['development_summary' => 'Laporan bulan lalu', 'status' => 'approved']);
        SystemSetting::whereKey(1)->update(['default_monthly_video_target' => 3]);
        Carbon::setTestNow(Carbon::parse('2026-12-01 08:00:00', 'Asia/Makassar'));
        $service->ensureFor($this->superadmin);
        $new = MonthlyReport::whereDate('report_month', '2026-12-01')->sole();
        $this->assertSame(3, $new->video_target);
        $this->assertSame('2026-12-01', $new->cycle->start_month->toDateString());
        $this->assertSame('2027-02-01', $new->cycle->end_month->toDateString());
        $this->assertSame(4, $old->fresh()->video_target);
        $this->assertSame('approved', $old->fresh()->status);
        $this->assertSame('Laporan bulan lalu', $old->fresh()->development_summary);
        $this->assertDatabaseCount('monthly_reports', 2);
    }

    public function test_partial_first_month_draft_is_available_but_target_still_requires_admin_confirmation(): void
    {
        $this->student->update(['began_on' => '2026-11-02']);
        app(AutomaticMonthlyReportService::class)->ensureFor($this->superadmin);
        $report = MonthlyReport::sole();
        $this->assertNull($report->video_target_confirmed_at);
        $this->assertFalse(app(\App\Services\StudentProgressService::class)->targetReady($report));
        $this->actingAs($this->teacher->user)->get(route('teacher.student-progress.edit', $report))->assertOk();
    }

    public function test_ambiguous_teacher_is_not_chosen_arbitrarily_or_allowed_to_break_teacher_page(): void
    {
        StudentTeacherAssignment::query()->update(['is_report_owner' => false]);
        $other = $this->teacher($this->branch);
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $other->id, 'starts_on' => '2026-09-01', 'is_report_owner' => false]);
        $this->actingAs($this->teacher->user)->get(route('teacher.student-progress.index'))->assertOk();
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_admin_cannot_generate_or_delete_data_outside_own_branch(): void
    {
        $foreignBranch = $this->branch('Cabang Lain');
        $foreignTeacher = $this->teacher($foreignBranch);
        $foreignStudent = $this->student($foreignBranch);
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $foreignStudent->id, 'teacher_id' => $foreignTeacher->id, 'starts_on' => '2026-09-01', 'is_report_owner' => true]);
        app(AutomaticMonthlyReportService::class)->ensureFor($this->admin);
        $this->assertDatabaseMissing('monthly_reports', ['student_id' => $foreignStudent->id]);
        $this->actingAs($this->admin)->delete(route('admin.students.destroy', $foreignStudent))->assertForbidden();
        $this->delete(route('admin.teachers.destroy', $foreignTeacher))->assertForbidden();
        $this->assertNotNull($foreignStudent->fresh());
        $this->assertNotNull($foreignTeacher->fresh());
    }

    public function test_admin_deletes_student_from_student_menu_without_extra_fields(): void
    {
        $userId = $this->student->portal_user_id;
        $this->actingAs($this->admin)->get(route('admin.students.index'))->assertOk()->assertSee(route('admin.students.destroy', $this->student), false);
        $this->delete(route('admin.students.destroy', $this->student))->assertRedirect(route('admin.students.index'))->assertSessionHasNoErrors();
        $this->assertNull(Student::find($this->student->id));
        $this->assertNull(User::find($userId));
        $this->get(route('admin.students.show', $this->student->id))->assertNotFound();
    }

    public function test_admin_deletes_teacher_and_account_using_same_super_admin_mechanism(): void
    {
        $lesson = $this->lesson();
        $userId = $this->teacher->user_id;
        $this->actingAs($this->admin)->get(route('admin.teachers.show', $this->teacher))->assertOk()->assertSee('Hapus');
        $this->delete(route('admin.teachers.destroy', $this->teacher))->assertRedirect(route('admin.teachers.index'))->assertSessionHasNoErrors();
        $this->assertNull(Teacher::find($this->teacher->id));
        $this->assertNull(User::find($userId));
        $this->assertSame('cancelled', $lesson->fresh()->status);
        $this->assertNull($this->group->fresh()->default_teacher_id);
    }

    public function test_admin_can_delete_a_student_profile_whose_portal_account_was_already_deleted(): void
    {
        $this->student->portalUser->delete();
        $this->actingAs($this->admin)->delete(route('admin.students.destroy', $this->student))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(Student::find($this->student->id));
    }

    public function test_orphan_profile_does_not_bypass_other_branch_protection(): void
    {
        $this->student->portalUser->delete();
        $other = $this->branch('Cabang Kedua');
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'branch_id' => $other->id,
            'program_id' => $this->program->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31']);
        $this->actingAs($this->admin)->delete(route('admin.students.destroy', $this->student))->assertForbidden();
        $this->assertNotNull(Student::find($this->student->id));
    }

    #[DataProvider('portalRoles')]
    public function test_teacher_and_student_cannot_delete_admin_data(string $role): void
    {
        $this->actingAs($this->user($role))->delete(route('admin.students.destroy', $this->student))->assertForbidden();
        $this->delete(route('admin.teachers.destroy', $this->teacher))->assertForbidden();
        $this->assertNotNull(Student::find($this->student->id));
        $this->assertNotNull(Teacher::find($this->teacher->id));
    }

    public static function portalRoles(): array
    {
        return [['teacher'], ['student']];
    }

    public function test_unpaid_student_can_be_marked_present_in_following_month_and_headers_use_sesi(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 11:00:00', 'Asia/Makassar'));
        $lesson = $this->lesson();
        $attendance = app(StudentAttendanceService::class);
        $this->assertTrue($attendance->cell($lesson, $this->student)['can_record']);
        \App\Models\MonthlyCharge::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id,
            'branch_id' => $this->branch->id, 'charge_month' => '2026-10-01', 'amount_idr' => 100000, 'due_on' => '2026-10-18']);
        $this->actingAs($this->teacher->user)->get(route('teacher.student-attendance.index', ['period' => '2026-11', 'class_group_id' => $this->group->id]))
            ->assertOk()->assertSee('Sesi 1')->assertSee('Sesi 8')->assertDontSee('<th>P1', false);
        $this->assertDatabaseCount('payment_proofs', 0);
        $record = $attendance->record($this->teacher->user, $lesson, $this->student, ['attendance_status' => 'present', 'expected_version' => 0]);
        $this->assertSame('present', $record->attendance_status);
        $this->assertDatabaseHas('student_attendance_audits', ['session_id' => $lesson->id, 'student_id' => $this->student->id]);
    }

    public function test_inactive_future_and_ended_students_are_not_prepared_automatically(): void
    {
        $this->student->update(['status' => 'inactive']);
        $this->student($this->branch, '2026-12-01');
        $ended = $this->student($this->branch);
        $ended->enrollments()->update(['ends_on' => '2026-10-31']);
        app(AutomaticMonthlyReportService::class)->ensureFor($this->superadmin);
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_automatic_month_uses_each_branch_timezone_at_month_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 16:30:00', 'UTC'));
        $western = $this->branch('Cabang WIB');
        $western->update(['timezone_name' => 'Asia/Jakarta']);
        $teacher = $this->teacher($western);
        $student = $this->student($western);
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'teacher_id' => $teacher->id, 'starts_on' => '2026-09-01', 'is_report_owner' => true]);
        app(AutomaticMonthlyReportService::class)->ensureFor($this->superadmin);
        $this->assertDatabaseHas('monthly_reports', ['student_id' => $this->student->id, 'report_month' => '2026-11-01 00:00:00']);
        $this->assertDatabaseHas('monthly_reports', ['student_id' => $student->id, 'report_month' => '2026-10-01 00:00:00']);
    }

    public static function staffRoles(): array
    {
        return [['superadmin'], ['admin'], ['teacher']];
    }

    #[DataProvider('staffRoles')]
    public function test_learning_notes_pages_and_actions_are_removed_for_each_role(string $role): void
    {
        $actor = match ($role) { 'superadmin' => $this->superadmin, 'admin' => $this->admin, default => $this->teacher->user };
        $this->actingAs($actor)->get('/'.$role.'/learning-notes')->assertNotFound();
        $this->get('/'.$role.'/learning-notes/'.Str::uuid())->assertNotFound();
        $this->put('/'.$role.'/learning-notes/'.Str::uuid(), ['topic' => 'Tidak boleh tersimpan'])->assertNotFound();
        $this->get(route($role.'.student-progress.index'))->assertOk()->assertDontSee('Catatan Belajar');
    }

    public function test_console_command_prepares_only_one_draft_per_student_month(): void
    {
        $this->artisan('dpe:prepare-monthly-reports')->assertSuccessful();
        $this->artisan('dpe:prepare-monthly-reports')->assertSuccessful();
        $this->assertDatabaseCount('monthly_reports', 1);
    }

    public function test_continued_series_reuses_existing_session_and_students_without_reentry(): void
    {
        $service = app(LessonSeriesService::class);
        $data = ['branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id,
            'class_group_id' => $this->group->id, 'class_learning_session_id' => $this->learningSession->id,
            'teaching_location' => 'branch', 'starts_on' => '2026-11-02', 'ends_on' => '2027-01-31', 'weekdays' => [1], 'starts_at' => '09:00', 'ends_at' => '10:00'];
        $preview = $service->previewCreate($this->admin, $data);
        $first = $service->create($this->admin, ['preview_token' => $preview['previewToken'], 'excluded_dates' => []]);
        $response = $this->actingAs($this->admin)->get(route('admin.lesson-series.create', ['continue_series' => $first->id]))->assertOk();
        $next = $response->viewData('formValues');
        $this->assertSame($this->learningSession->id, $next['class_learning_session_id']);
        $this->assertSame('2027-02-01', $next['starts_on']);
        $preview = $service->previewCreate($this->admin, $next);
        $continued = $service->create($this->admin, ['preview_token' => $preview['previewToken'], 'excluded_dates' => []]);
        $lesson = $continued->sessions()->orderBy('planned_start_at')->firstOrFail();
        $this->assertTrue(app(StudentAttendanceService::class)->roster($lesson)->contains('id', $this->student->id));
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('class_group_memberships', 1);
        $this->assertDatabaseCount('class_learning_session_enrollments', 1);
    }
}
