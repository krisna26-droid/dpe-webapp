<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\ReportCycle;
use App\Models\MonthlyReport;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\TeacherAvailability;
use App\Models\User;
use App\Services\BranchOperationsService;
use App\Services\StudentAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BranchOperationsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $superadmin;
    private Branch $branch;
    private Branch $otherBranch;
    private Program $program;
    private Teacher $teacher;
    private Teacher $otherTeacher;
    private Student $student;
    private Student $otherStudent;
    private ClassGroup $group;
    private ClassGroup $otherGroup;
    private ClassGroupMembership $membership;
    private ClassGroupMembership $otherMembership;
    private LessonSession $lesson;
    private LessonSession $otherLesson;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-14 18:00:00', 'Asia/Makassar'));
        Http::fake();
        $this->admin = $this->user('admin', 'Admin Utama');
        $this->superadmin = $this->user('superadmin', 'Super Admin');
        $this->branch = $this->branch('Cabang Utama');
        $this->otherBranch = $this->branch('Cabang Luar Rahasia');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id,
            'admin_user_id' => $this->admin->id, 'starts_on' => '2026-01-01']);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => 'English Regular', 'class_type' => 'regular', 'is_active' => true]);
        $this->teacher = $this->teacher('Dipta Uji');
        $this->otherTeacher = $this->teacher('Teacher Luar Rahasia');
        $this->student = $this->student('Siswa Utama', $this->branch, $this->teacher);
        $this->otherStudent = $this->student('Siswa Luar Rahasia', $this->otherBranch, $this->otherTeacher);
        $this->group = $this->group('Kelas Utama', $this->branch, $this->teacher);
        $this->otherGroup = $this->group('Kelas Luar Rahasia', $this->otherBranch, $this->otherTeacher);
        $this->membership = ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $this->group->id, 'student_id' => $this->student->id, 'starts_on' => '2026-10-01']);
        $this->otherMembership = ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $this->otherGroup->id, 'student_id' => $this->otherStudent->id, 'starts_on' => '2026-10-01']);
        $this->lesson = LessonSession::create(['id' => (string) Str::uuid()] + $this->sessionData(['planned_start_at' => '2026-10-14 09:00:00', 'planned_end_at' => '2026-10-14 10:30:00']));
        $this->otherLesson = LessonSession::create(['id' => (string) Str::uuid()] + $this->sessionData([
            'branch_id' => $this->otherBranch->id, 'teacher_id' => $this->otherTeacher->id, 'class_group_id' => $this->otherGroup->id, 'topic' => 'Topik Luar Rahasia']));
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function user(string $role, string $name = 'Pengguna Uji'): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'full_name' => $name,
            'password_hash' => 'fixture-hash', 'role_code' => $role, 'is_active' => true]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => $name,
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function teacher(string $name): Teacher
    {
        return Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', $name)->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
    }

    private function student(string $name, Branch $branch, Teacher $teacher): Student
    {
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student', $name)->id,
            'full_name' => $name, 'began_on' => '2026-10-01', 'status' => 'active']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $branch->id,
            'program_id' => $this->program->id, 'starts_on' => '2026-10-01']);
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'teacher_id' => $teacher->id,
            'starts_on' => '2026-10-01', 'is_report_owner' => true]);

        return $student;
    }

    private function group(string $name, Branch $branch, ?Teacher $teacher = null): ClassGroup
    {
        return ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $branch->id, 'program_id' => $this->program->id,
            'default_teacher_id' => $teacher?->id, 'code' => Str::random(10), 'name' => $name, 'is_active' => true]);
    }

    private function groupData(array $extra = []): array
    {
        return $extra + ['branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'default_teacher_id' => $this->teacher->id,
            'code' => 'BARU-01', 'name' => 'Kelas Baru', 'is_active' => 1];
    }

    private function sessionData(array $extra = []): array
    {
        return $extra + ['branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id,
            'class_group_id' => $this->group->id, 'teaching_location' => 'branch', 'planned_start_at' => '2026-10-14 11:00:00',
            'planned_end_at' => '2026-10-14 12:30:00', 'actual_start_at' => null, 'actual_end_at' => null,
            'status' => 'scheduled', 'rescheduled_from_session_id' => null, 'topic' => 'Topik Utama', 'material' => null, 'activity' => null];
    }

    private function membershipData(array $extra = []): array
    {
        return $extra + ['class_group_id' => $this->group->id, 'student_id' => $this->student->id, 'starts_on' => '2026-10-01', 'ends_on' => null];
    }

    private function version($record): string { return app(BranchOperationsService::class)->version($record->fresh()); }

    public function test_sidebar_opens_both_menus_and_retains_the_other_planned_menus(): void
    {
        $page = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $page->assertSee('href="'.route('admin.class-groups.index').'"', false)
            ->assertSee('href="'.route('admin.lesson-sessions.index').'"', false)->assertSee('Pengaturan Cabang');
        $this->get(route('admin.class-groups.index'))->assertOk()->assertSee('Kelas Utama')->assertDontSee('Kelas Luar Rahasia');
        $this->get(route('admin.lesson-sessions.index'))->assertOk()->assertSee('Kelas Utama')->assertDontSee('Kelas Luar Rahasia');
        $this->get(route('admin.class-group-memberships.index'))->assertOk()->assertSee('Siswa Utama')->assertDontSee('Siswa Luar Rahasia');
    }

    public static function listPages(): array
    {
        return array_map(fn ($name) => [$name], ['class-groups.index', 'class-groups.create', 'class-group-memberships.index',
            'class-group-memberships.create', 'lesson-sessions.index', 'lesson-sessions.create']);
    }

    #[DataProvider('listPages')]
    public function test_guests_must_login_before_opening_an_operations_page(string $name): void
    {
        $this->get(route('admin.'.$name))->assertRedirect(route('login'));
    }

    public static function prohibitedRoles(): array
    {
        $cases = [];
        foreach (['teacher', 'student', 'inactive-admin'] as $role) {
            foreach (self::listPages() as [$page]) { $cases[] = [$role, $page]; }
        }

        return $cases;
    }

    #[DataProvider('prohibitedRoles')]
    public function test_other_roles_and_inactive_accounts_cannot_open_admin_operations(string $role, string $page): void
    {
        $actor = $this->user($role === 'inactive-admin' ? 'admin' : $role);
        if ($role === 'inactive-admin') { $actor->update(['is_active' => false]); }
        $this->actingAs($actor)->get(route('admin.'.$page))->assertForbidden();
    }

    public static function foreignPages(): array
    {
        return [['class-groups.show', 'otherGroup'], ['class-groups.edit', 'otherGroup'],
            ['class-group-memberships.show', 'otherMembership'], ['class-group-memberships.edit', 'otherMembership'],
            ['lesson-sessions.show', 'otherLesson'], ['lesson-sessions.edit', 'otherLesson']];
    }

    #[DataProvider('foreignPages')]
    public function test_foreign_records_are_denied_even_through_direct_urls(string $name, string $property): void
    {
        $this->actingAs($this->admin)->get(route('admin.'.$name, $this->$property))->assertForbidden();
    }

    public function test_superadmin_can_use_admin_pages_without_losing_superadmin_navigation(): void
    {
        foreach (['class-groups.index', 'class-group-memberships.index', 'lesson-sessions.index'] as $name) {
            $this->actingAs($this->superadmin)->get(route('admin.'.$name))->assertOk()->assertSee('Cabang Luar Rahasia');
        }
        $this->get(route('superadmin.class-groups.show', $this->group))->assertOk()
            ->assertSee(route('superadmin.class-groups.edit', $this->group))->assertDontSee('branch-operations.js');
    }

    public function test_teacher_with_only_student_assignment_is_visible_and_can_be_scheduled(): void
    {
        $this->group->update(['default_teacher_id' => null]);
        $this->lesson->delete();
        $this->assertSame(0, $this->teacher->branchAssignments()->count());
        $this->actingAs($this->admin)->get(route('admin.lesson-sessions.create'))->assertOk()->assertSee('Dipta Uji')->assertDontSee('Teacher Luar Rahasia');
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData(['class_group_id' => null]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('lesson_sessions', ['branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'class_group_id' => null]);
    }

    public function test_class_can_be_created_edited_and_deactivated_with_a_scoped_teacher(): void
    {
        $this->actingAs($this->admin)->post(route('admin.class-groups.store'), $this->groupData())->assertSessionHasNoErrors()->assertRedirect();
        $group = ClassGroup::where('code', 'BARU-01')->firstOrFail();
        $this->get(route('admin.class-groups.edit', $group))->assertOk()->assertSee('record_version')->assertDontSee('Teacher Luar Rahasia');
        $this->put(route('admin.class-groups.update', $group), $this->groupData(['name' => 'Kelas Diperbarui', 'record_version' => $this->version($group)]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Kelas Diperbarui', $group->fresh()->name);
        $this->patch(route('admin.class-groups.status', $group), ['record_version' => $this->version($group)])->assertRedirect();
        $this->assertFalse($group->fresh()->is_active);
        $this->assertTrue($this->otherGroup->fresh()->is_active);
    }

    public function test_duplicate_class_code_is_rejected_within_the_branch(): void
    {
        $this->actingAs($this->admin)->post(route('admin.class-groups.store'), $this->groupData(['code' => $this->group->code]))->assertSessionHasErrors('code');
        $this->assertSame(2, ClassGroup::count());
    }

    public function test_class_create_cannot_use_another_branch_or_unrelated_teacher(): void
    {
        $this->actingAs($this->admin)->post(route('admin.class-groups.store'), $this->groupData(['branch_id' => $this->otherBranch->id]))->assertForbidden();
        $this->post(route('admin.class-groups.store'), $this->groupData(['default_teacher_id' => $this->otherTeacher->id]))->assertSessionHasErrors('default_teacher_id');
        $this->assertSame(2, ClassGroup::count());
    }

    public function test_stale_class_form_and_status_toggle_cannot_overwrite_a_newer_change(): void
    {
        $version = $this->version($this->group);
        $this->group->update(['name' => 'Perubahan Petugas Lain']);
        $this->actingAs($this->admin)->put(route('admin.class-groups.update', $this->group), $this->groupData(['record_version' => $version]))->assertSessionHasErrors('record_version');
        $this->patch(route('admin.class-groups.status', $this->group), ['record_version' => $version])->assertSessionHasErrors('record_version');
        $this->assertSame('Perubahan Petugas Lain', $this->group->fresh()->name);
        $this->assertTrue($this->group->fresh()->is_active);
    }

    public function test_class_with_participants_or_sessions_cannot_change_program(): void
    {
        $program = Program::create(['id' => (string) Str::uuid(), 'code' => 'OTHER', 'name' => 'Other Program', 'class_type' => 'regular', 'is_active' => true]);
        $this->actingAs($this->admin)->put(route('admin.class-groups.update', $this->group), $this->groupData([
            'program_id' => $program->id, 'code' => $this->group->code, 'record_version' => $this->version($this->group)]))->assertSessionHasErrors('branch_id');
        $this->assertSame($this->program->id, $this->group->fresh()->program_id);
    }

    public function test_existing_inactive_references_can_be_retained_when_renaming_a_class(): void
    {
        $this->branch->update(['is_active' => false]);
        $this->program->update(['is_active' => false]);
        $this->teacher->update(['is_active' => false]);
        $this->actingAs($this->admin)->get(route('admin.class-groups.edit', $this->group))->assertOk()->assertSee('Dipta Uji');
        $this->put(route('admin.class-groups.update', $this->group), $this->groupData([
            'name' => 'Nama Historis Baru', 'code' => $this->group->code, 'record_version' => $this->version($this->group)]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Nama Historis Baru', $this->group->fresh()->name);
    }

    public function test_member_can_be_added_and_end_date_updated_without_erasing_the_record(): void
    {
        $student = $this->student('Siswa Tambahan', $this->branch, $this->teacher);
        $this->actingAs($this->admin)->post(route('admin.class-group-memberships.store'), $this->membershipData(['student_id' => $student->id]))->assertSessionHasNoErrors()->assertRedirect();
        $member = ClassGroupMembership::where('student_id', $student->id)->firstOrFail();
        $this->put(route('admin.class-group-memberships.update', $member), $this->membershipData([
            'student_id' => $student->id, 'ends_on' => '2026-10-31', 'record_version' => $this->version($member)]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('2026-10-31', $member->fresh()->ends_on->toDateString());
        $this->assertDatabaseHas('class_group_memberships', ['id' => $this->membership->id]);
    }

    public function test_member_creation_rejects_foreign_group_student_and_wrong_program(): void
    {
        $this->actingAs($this->admin)->post(route('admin.class-group-memberships.store'), $this->membershipData(['class_group_id' => $this->otherGroup->id]))->assertForbidden();
        $this->post(route('admin.class-group-memberships.store'), $this->membershipData(['student_id' => $this->otherStudent->id]))->assertForbidden();
        $program = Program::create(['id' => (string) Str::uuid(), 'code' => 'WRONG', 'name' => 'Wrong Program', 'class_type' => 'regular', 'is_active' => true]);
        $group = $this->group('Program Lain', $this->branch);
        $group->update(['program_id' => $program->id]);
        $this->post(route('admin.class-group-memberships.store'), $this->membershipData(['class_group_id' => $group->id]))->assertSessionHasErrors('student_id');
        $this->assertSame(2, ClassGroupMembership::count());
    }

    public function test_member_creation_rejects_overlapping_periods_and_invalid_dates(): void
    {
        $this->actingAs($this->admin)->post(route('admin.class-group-memberships.store'), $this->membershipData())->assertSessionHasErrors('student_id');
        $this->post(route('admin.class-group-memberships.store'), $this->membershipData(['ends_on' => '2026-09-30']))->assertSessionHasErrors('ends_on');
        $this->assertSame(2, ClassGroupMembership::count());
    }

    public function test_member_forms_only_offer_students_and_groups_in_the_admin_branch(): void
    {
        $this->actingAs($this->admin)->get(route('admin.class-group-memberships.create', ['class_group_id' => $this->group->id]))
            ->assertOk()->assertSee('Siswa Utama')->assertSee('Kelas Utama')->assertDontSee('Siswa Luar Rahasia')->assertDontSee('Kelas Luar Rahasia');
        $this->get(route('admin.class-group-memberships.create', ['class_group_id' => $this->otherGroup->id]))->assertForbidden();
        $this->get(route('admin.class-group-memberships.create', ['class_group_id' => [$this->group->id]]))->assertSessionHasErrors('class_group_id');
    }

    public function test_recorded_attendance_keeps_membership_identity_and_coverage_intact(): void
    {
        SessionStudent::create(['session_id' => $this->lesson->id, 'student_id' => $this->student->id, 'attendance_status' => 'present', 'recorded_at' => now()]);
        $this->actingAs($this->admin)->put(route('admin.class-group-memberships.update', $this->membership), $this->membershipData([
            'starts_on' => '2026-10-15', 'record_version' => $this->version($this->membership)]))->assertSessionHasErrors('starts_on');
        $newGroup = $this->group('Kelas Baru Tujuan', $this->branch);
        $this->put(route('admin.class-group-memberships.update', $this->membership), $this->membershipData([
            'class_group_id' => $newGroup->id, 'record_version' => $this->version($this->membership)]))->assertSessionHasErrors('class_group_id');
        $this->put(route('admin.class-group-memberships.update', $this->membership), $this->membershipData([
            'ends_on' => '2026-10-31', 'record_version' => $this->version($this->membership)]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('session_students', ['session_id' => $this->lesson->id, 'student_id' => $this->student->id, 'attendance_status' => 'present']);
    }

    public function test_stale_membership_form_is_rejected(): void
    {
        $version = $this->version($this->membership);
        $this->membership->update(['ends_on' => '2026-11-30']);
        $this->actingAs($this->admin)->put(route('admin.class-group-memberships.update', $this->membership), $this->membershipData(['record_version' => $version]))->assertSessionHasErrors('record_version');
        $this->assertSame('2026-11-30', $this->membership->fresh()->ends_on->toDateString());
    }

    public function test_session_can_be_created_and_edited_with_correct_back_and_attendance_links(): void
    {
        $this->actingAs($this->admin)->post(route('admin.lesson-sessions.store'), $this->sessionData(['topic' => 'Sesi Baru Uji']))->assertSessionHasNoErrors()->assertRedirect();
        $session = LessonSession::where('topic', 'Sesi Baru Uji')->firstOrFail();
        $this->get(route('admin.lesson-sessions.show', $session))->assertOk()->assertSee(route('admin.lesson-sessions.index'))
            ->assertSee(route('admin.student-attendance.show', $session))->assertDontSee(route('superadmin.lesson-sessions.edit', $session));
        $this->put(route('admin.lesson-sessions.update', $session), $this->sessionData([
            'topic' => 'Sesi Diperbarui', 'record_version' => $this->version($session)]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Sesi Diperbarui', $session->fresh()->topic);
    }

    public function test_session_create_rejects_foreign_branch_class_teacher_and_source(): void
    {
        $this->actingAs($this->admin)->post(route('admin.lesson-sessions.store'), $this->sessionData(['branch_id' => $this->otherBranch->id]))->assertForbidden();
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData(['class_group_id' => $this->otherGroup->id]))->assertForbidden();
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData(['teacher_id' => $this->otherTeacher->id]))->assertSessionHasErrors('teacher_id');
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData(['rescheduled_from_session_id' => $this->otherLesson->id]))->assertForbidden();
        $this->assertSame(2, LessonSession::count());
    }

    public function test_session_create_rejects_wrong_class_program_and_unknown_status(): void
    {
        $program = Program::create(['id' => (string) Str::uuid(), 'code' => 'OTHER', 'name' => 'Other Program', 'class_type' => 'regular', 'is_active' => true]);
        $this->actingAs($this->admin)->post(route('admin.lesson-sessions.store'), $this->sessionData(['program_id' => $program->id]))->assertSessionHasErrors('class_group_id');
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData(['status' => 'arbitrary']))->assertSessionHasErrors('status');
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData(['actual_end_at' => '2026-10-14 10:00:00']))->assertSessionHasErrors('actual_start_at');
        $this->assertSame(2, LessonSession::count());
    }

    public function test_future_teacher_assignment_can_schedule_the_future_but_not_a_day_before_it(): void
    {
        $future = $this->teacher('Teacher November');
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $future->id, 'branch_id' => $this->branch->id,
            'starts_on' => '2026-11-01', 'ends_on' => '2026-12-31']);
        $this->actingAs($this->admin)->get(route('admin.lesson-sessions.create'))->assertOk()->assertSee('Teacher November');
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData(['teacher_id' => $future->id]))->assertSessionHasErrors('teacher_id');
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData(['teacher_id' => $future->id,
            'planned_start_at' => '2026-11-01 09:00:00', 'planned_end_at' => '2026-11-01 10:30:00']))->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_rescheduling_creates_one_replacement_and_does_not_duplicate_attendance(): void
    {
        $this->actingAs($this->admin)->post(route('admin.lesson-sessions.store'), $this->sessionData([
            'planned_start_at' => '2026-10-15 09:00:00', 'planned_end_at' => '2026-10-15 10:30:00', 'rescheduled_from_session_id' => $this->lesson->id]))->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData([
            'planned_start_at' => '2026-10-16 09:00:00', 'planned_end_at' => '2026-10-16 10:30:00', 'rescheduled_from_session_id' => $this->lesson->id]))->assertSessionHasErrors('rescheduled_from_session_id');
        $this->assertSame(1, $this->lesson->replacementSessions()->count());
        $this->assertSame(0, SessionStudent::count());
    }

    public function test_stale_session_form_and_recorded_attendance_protect_the_existing_schedule(): void
    {
        $version = $this->version($this->lesson);
        $this->lesson->update(['topic' => 'Perubahan Terbaru']);
        $this->actingAs($this->admin)->put(route('admin.lesson-sessions.update', $this->lesson), $this->sessionData(['record_version' => $version]))->assertSessionHasErrors('record_version');
        SessionStudent::create(['session_id' => $this->lesson->id, 'student_id' => $this->student->id, 'attendance_status' => 'present', 'recorded_at' => now()]);
        $this->put(route('admin.lesson-sessions.update', $this->lesson), $this->sessionData([
            'planned_start_at' => '2026-10-15 09:00:00', 'planned_end_at' => '2026-10-15 10:30:00', 'record_version' => $this->version($this->lesson)]))->assertSessionHasErrors('planned_start_at');
        $this->put(route('admin.lesson-sessions.update', $this->lesson), $this->sessionData([
            'teaching_location' => 'student_home', 'record_version' => $this->version($this->lesson)]))->assertSessionHasErrors('teaching_location');
        $this->assertSame('2026-10-14', $this->lesson->fresh()->planned_start_at->toDateString());
    }

    public function test_teachers_and_students_see_the_admin_created_class_and_session(): void
    {
        $this->actingAs($this->admin)->post(route('admin.lesson-sessions.store'), $this->sessionData(['topic' => 'Pertemuan Admin']))->assertSessionHasNoErrors()->assertRedirect();
        $session = LessonSession::where('topic', 'Pertemuan Admin')->firstOrFail();
        $this->actingAs($this->teacher->user)->get(route('teacher.student-attendance.show', $session))->assertOk()->assertSee('Siswa Utama');
        $this->actingAs($this->student->portalUser)->get(route('student.schedule'))->assertOk()->assertSee('Pertemuan Admin');
        $this->assertCount(1, app(StudentAttendanceService::class)->roster($session));
        Http::assertNothingSent();
    }

    public function test_expired_or_future_admin_assignment_does_not_grant_branch_access(): void
    {
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-10-13']);
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'branch_id' => $this->otherBranch->id,
            'admin_user_id' => $this->admin->id, 'starts_on' => '2026-11-01', 'ends_on' => '2026-12-31']);
        $this->actingAs($this->admin)->get(route('admin.class-groups.index'))->assertOk()->assertDontSee('Kelas Utama')->assertDontSee('Kelas Luar Rahasia');
        $this->get(route('admin.class-groups.show', $this->group))->assertForbidden();
        $this->get(route('admin.lesson-sessions.create'))->assertForbidden();
        $this->get(route('admin.class-groups.show', $this->otherGroup))->assertForbidden();
    }

    public function test_filters_work_without_exposing_foreign_teacher_or_branch(): void
    {
        $this->actingAs($this->admin)->get(route('admin.lesson-sessions.index', ['to' => '2026-10-14', 'q' => 'Utama']))->assertOk()->assertSee('Kelas Utama');
        $this->get(route('admin.lesson-sessions.index', ['from' => '2026-10-15']))->assertOk()->assertDontSee('Kelas Utama');
        $this->get(route('admin.class-groups.index', ['status' => 'inactive']))->assertOk()
            ->assertViewHas('classGroups', fn ($groups) => $groups->total() === 0);
        $this->get(route('admin.class-groups.index', ['branch_id' => $this->otherBranch->id]))->assertForbidden();
        $this->get(route('admin.lesson-sessions.index', ['teacher_id' => $this->otherTeacher->id]))->assertForbidden();
        $this->get(route('admin.lesson-sessions.index', ['from' => '2026-10-15', 'to' => '2026-10-14']))->assertSessionHasErrors('to');
    }

    public function test_direct_foreign_mutations_do_not_change_the_other_branch(): void
    {
        $beforeGroup = $this->otherGroup->fresh()->getAttributes();
        $beforeMember = $this->otherMembership->fresh()->getAttributes();
        $beforeSession = $this->otherLesson->fresh()->getAttributes();
        $this->actingAs($this->admin)->put(route('admin.class-groups.update', $this->otherGroup), $this->groupData())->assertForbidden();
        $this->patch(route('admin.class-groups.status', $this->otherGroup), ['record_version' => $this->version($this->otherGroup)])->assertForbidden();
        $this->put(route('admin.class-group-memberships.update', $this->otherMembership), $this->membershipData())->assertForbidden();
        $this->put(route('admin.lesson-sessions.update', $this->otherLesson), $this->sessionData())->assertForbidden();
        $this->assertSame($beforeGroup, $this->otherGroup->fresh()->getAttributes());
        $this->assertSame($beforeMember, $this->otherMembership->fresh()->getAttributes());
        $this->assertSame($beforeSession, $this->otherLesson->fresh()->getAttributes());
    }

    public function test_listing_is_read_only_and_does_not_create_memberships_sessions_or_attendance(): void
    {
        $before = [ClassGroup::count(), ClassGroupMembership::count(), LessonSession::count(), SessionStudent::count()];
        foreach (['class-groups.index', 'class-group-memberships.index', 'lesson-sessions.index'] as $name) {
            $response = $this->actingAs($this->admin)->get(route('admin.'.$name))->assertOk();
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        }
        $this->assertSame($before, [ClassGroup::count(), ClassGroupMembership::count(), LessonSession::count(), SessionStudent::count()]);
        Http::assertNothingSent();
    }

    public function test_teacher_cannot_be_double_booked_and_adjacent_sessions_are_allowed(): void
    {
        $this->actingAs($this->admin)->post(route('admin.lesson-sessions.store'), $this->sessionData([
            'planned_start_at' => '2026-10-14 10:00:00', 'planned_end_at' => '2026-10-14 11:00:00']))->assertSessionHasErrors('planned_start_at');
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData([
            'planned_start_at' => '2026-10-14 10:30:00', 'planned_end_at' => '2026-10-14 11:00:00']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(3, LessonSession::count());
    }

    private function slot(string $start, string $end, string $status = 'Available', string $type = 'Regular'): TeacherAvailability
    {
        return TeacherAvailability::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id,
            'available_on' => '2026-10-14', 'starts_at' => $start, 'ends_at' => $end, 'class_type' => $type, 'availability_status' => $status]);
    }

    public function test_availability_is_shown_in_the_form_and_checked_on_save(): void
    {
        $this->slot('11:00:00', '13:00:00');
        $this->actingAs($this->admin)->get(route('admin.lesson-sessions.create'))->assertOk()->assertSee('Ketersediaan Teacher')->assertSee('11:00–13:00')->assertDontSee('Teacher Luar Rahasia');
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData([
            'planned_start_at' => '2026-10-14 14:00:00', 'planned_end_at' => '2026-10-14 15:00:00']))->assertSessionHasErrors('planned_start_at');
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData())->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_blocked_and_booked_slots_override_available_windows(): void
    {
        $this->slot('11:00:00', '14:00:00');
        $this->slot('12:00:00', '12:30:00', 'Blocked');
        $this->actingAs($this->admin)->post(route('admin.lesson-sessions.store'), $this->sessionData())->assertSessionHasErrors('planned_start_at');
        TeacherAvailability::where('availability_status', 'Blocked')->update(['availability_status' => 'Booked']);
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData())->assertSessionHasErrors('planned_start_at');
        $this->assertSame(2, LessonSession::count());
    }

    public function test_contiguous_available_slots_cover_a_session_but_gaps_and_other_class_types_do_not(): void
    {
        $a = $this->slot('11:00:00', '12:00:00');
        $b = $this->slot('12:15:00', '13:00:00');
        $this->actingAs($this->admin)->post(route('admin.lesson-sessions.store'), $this->sessionData())->assertSessionHasErrors('planned_start_at');
        $b->update(['starts_at' => '12:00:00', 'class_type' => 'Private']);
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData())->assertSessionHasErrors('planned_start_at');
        $b->update(['class_type' => 'Regular']);
        $this->post(route('admin.lesson-sessions.store'), $this->sessionData())->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_cancelled_sessions_do_not_block_a_new_schedule(): void
    {
        $this->lesson->update(['status' => 'cancelled']);
        $this->actingAs($this->admin)->post(route('admin.lesson-sessions.store'), $this->sessionData([
            'planned_start_at' => '2026-10-14 09:00:00', 'planned_end_at' => '2026-10-14 10:30:00']))->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_inactive_teacher_cannot_be_selected_for_a_new_or_moved_session(): void
    {
        $this->teacher->update(['is_active' => false]);
        $this->actingAs($this->admin)->post(route('admin.lesson-sessions.store'), $this->sessionData())->assertSessionHasErrors('teacher_id');
        $this->put(route('admin.lesson-sessions.update', $this->lesson), $this->sessionData(['record_version' => $this->version($this->lesson)]))->assertSessionHasErrors('teacher_id');
        $this->put(route('admin.lesson-sessions.update', $this->lesson), $this->sessionData([
            'planned_start_at' => '2026-10-14 09:00:00', 'planned_end_at' => '2026-10-14 10:30:00',
            'topic' => 'Koreksi Topik Historis', 'record_version' => $this->version($this->lesson)]))->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_searching_for_the_digit_zero_does_not_return_every_class(): void
    {
        $this->group->update(['code' => 'ABC', 'name' => 'Kelas Tanpa Angka']);
        $response = $this->actingAs($this->admin)->get(route('admin.class-groups.index', ['q' => '0']))->assertOk();
        $this->assertSame(0, $response->viewData('classGroups')->total());
    }

    public function test_private_rescheduling_copies_valid_participants_without_copying_attendance(): void
    {
        $this->lesson->update(['class_group_id' => null]);
        SessionStudent::create(['session_id' => $this->lesson->id, 'student_id' => $this->student->id, 'attendance_status' => 'rescheduled']);
        $this->actingAs($this->admin)->post(route('admin.lesson-sessions.store'), $this->sessionData([
            'class_group_id' => null, 'planned_start_at' => '2026-10-15 11:00:00', 'planned_end_at' => '2026-10-15 12:30:00',
            'rescheduled_from_session_id' => $this->lesson->id]))->assertSessionHasNoErrors()->assertRedirect();
        $replacement = $this->lesson->replacementSessions()->firstOrFail();
        $this->assertDatabaseHas('session_students', ['session_id' => $replacement->id, 'student_id' => $this->student->id, 'attendance_status' => null]);
        $this->assertDatabaseHas('session_students', ['session_id' => $this->lesson->id, 'student_id' => $this->student->id, 'attendance_status' => 'rescheduled']);
    }

    public function test_validation_redirect_retains_the_stale_version_until_the_form_is_refreshed(): void
    {
        $edit = route('admin.class-groups.edit', $this->group);
        $version = $this->version($this->group);
        $this->group->update(['name' => 'Nama Terbaru Petugas Lain']);
        $this->actingAs($this->admin)->from($edit)->put(route('admin.class-groups.update', $this->group),
            $this->groupData(['record_version' => $version]))->assertRedirect($edit)->assertSessionHasErrors('record_version');
        $this->get($edit)->assertOk()->assertSee('name="record_version" value="'.$version.'"', false);
        $this->put(route('admin.class-groups.update', $this->group), $this->groupData(['record_version' => $version]))->assertSessionHasErrors('record_version');
        $this->assertSame('Nama Terbaru Petugas Lain', $this->group->fresh()->name);
    }

    private function cycles(): array
    {
        $cycles = [];
        foreach ([$this->student, $this->otherStudent] as $student) {
            $cycles[] = ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $student->id,
                'cycle_number' => 1, 'start_month' => '2026-10-01', 'end_month' => '2026-12-01', 'share_due_on' => '2027-01-10']);
        }

        return $cycles;
    }

    public function test_report_cycle_sidebar_opens_a_scoped_html_table_and_preserves_the_json_api(): void
    {
        [$own, $foreign] = $this->cycles();
        $this->actingAs($this->admin)->get(route('admin.report-cycles.index'))->assertOk()->assertViewIs('admin.report-cycles.index')
            ->assertSee('Siswa Utama')->assertDontSee('Siswa Luar Rahasia')->assertSee('Siapkan periode bulanan')
            ->assertSee(route('admin.student-progress.preparation', ['student' => $this->student->id, 'month' => '2026-10']));
        $this->getJson(route('admin.report-cycles.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->actingAs($this->superadmin)->get(route('admin.report-cycles.index'))->assertOk()->assertSee('Siswa Luar Rahasia')
            ->assertSee(route('superadmin.student-progress.index'));
    }

    public function test_cycle_table_only_links_monthly_reports_within_the_admin_scope(): void
    {
        [$cycle] = $this->cycles();
        $own = MonthlyReport::forceCreate(['id' => (string) Str::uuid(), 'cycle_id' => $cycle->id, 'student_id' => $this->student->id,
            'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'report_month' => '2026-10-01', 'due_on' => '2026-10-31', 'status' => 'draft', 'video_target' => 0]);
        $foreign = MonthlyReport::forceCreate(['id' => (string) Str::uuid(), 'cycle_id' => $cycle->id, 'student_id' => $this->student->id,
            'branch_id' => $this->otherBranch->id, 'teacher_id' => $this->otherTeacher->id, 'report_month' => '2026-11-01', 'due_on' => '2026-11-30', 'status' => 'draft', 'video_target' => 0]);
        $this->actingAs($this->admin)->get(route('admin.report-cycles.index'))->assertOk()
            ->assertSee(route('admin.monthly-reports.show', $own))->assertDontSee(route('admin.monthly-reports.show', $foreign));
    }

    public function test_cycle_table_search_and_empty_state_do_not_create_periods(): void
    {
        $this->cycles();
        $this->actingAs($this->admin)->get(route('admin.report-cycles.index', ['q' => 'Tidak Ditemukan']))->assertOk()->assertSee('Belum ada siklus laporan');
        $this->assertSame(2, ReportCycle::count());
        $this->assertSame(0, MonthlyReport::count());
    }

    public function test_superadmin_uses_the_same_collision_and_availability_checks(): void
    {
        $this->actingAs($this->superadmin)->post(route('superadmin.lesson-sessions.store'), $this->sessionData([
            'planned_start_at' => '2026-10-14 09:30:00', 'planned_end_at' => '2026-10-14 10:45:00']))->assertSessionHasErrors('planned_start_at');
        $this->slot('11:00:00', '13:00:00', 'Blocked');
        $this->post(route('superadmin.lesson-sessions.store'), $this->sessionData())->assertSessionHasErrors('planned_start_at');
        $this->assertSame(2, LessonSession::count());
    }
}
