<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\ClassLearningSession;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\BranchOperationsService;
use App\Services\ClassLearningSessionService;
use App\Services\OperationalDeletionService;
use App\Services\StudentAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClassLearningSessionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superadmin;

    private Branch $branch;

    private Branch $otherBranch;

    private Program $program;

    private Teacher $teacher;

    private Teacher $otherTeacher;

    private ClassGroup $group;

    private ClassGroup $otherGroup;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-23 08:00:00', 'Asia/Makassar'));
        Http::fake();
        $this->admin = $this->user('admin', 'Admin Utama');
        $this->superadmin = $this->user('superadmin', 'Super Admin');
        $this->branch = $this->branch('Cabang Utama');
        $this->otherBranch = $this->branch('Cabang Luar Rahasia');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id,
            'admin_user_id' => $this->admin->id, 'starts_on' => '2026-01-01']);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => 'REG', 'name' => 'English Regular',
            'class_type' => 'regular', 'is_active' => true]);
        $this->teacher = $this->teacher('Teacher Utama');
        $this->otherTeacher = $this->teacher('Teacher Kedua');
        $this->assign($this->teacher, $this->branch);
        $this->assign($this->otherTeacher, $this->branch);
        $this->group = $this->group('Kelas A', $this->branch, $this->teacher);
        $this->otherGroup = $this->group('Kelas Luar Rahasia', $this->otherBranch, null);
        $this->student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student', 'Siswa Utama')->id,
            'full_name' => 'Siswa Utama', 'began_on' => '2026-10-01', 'status' => 'active']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id,
            'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-10-01']);
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $this->group->id,
            'student_id' => $this->student->id, 'starts_on' => '2026-10-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name = 'Pengguna Uji'): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(15), 'full_name' => $name,
            'password_hash' => 'fixture-hash', 'role_code' => $role, 'is_active' => true]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => $name,
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function teacher(string $name): Teacher
    {
        return Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', $name)->id,
            'whatsapp_number' => '081234567890', 'is_active' => true]);
    }

    private function assign(Teacher $teacher, Branch $branch, array $extra = []): void
    {
        TeacherBranchAssignment::create($extra + ['id' => (string) Str::uuid(), 'teacher_id' => $teacher->id,
            'branch_id' => $branch->id, 'starts_on' => '2026-01-01']);
    }

    private function group(string $name, Branch $branch, ?Teacher $teacher): ClassGroup
    {
        return ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $branch->id, 'program_id' => $this->program->id,
            'default_teacher_id' => $teacher?->id, 'code' => Str::random(12), 'name' => $name, 'is_active' => true]);
    }

    private function slot(string $name, ?ClassGroup $group = null): ClassLearningSession
    {
        return ClassLearningSession::create(['id' => (string) Str::uuid(), 'class_group_id' => ($group ?? $this->group)->id, 'name' => $name]);
    }

    private function lesson(ClassLearningSession $slot, string $day = '2026-11-02', int $hour = 14): LessonSession
    {
        return LessonSession::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id,
            'teacher_id' => $this->teacher->id, 'class_group_id' => $slot->class_group_id, 'class_learning_session_id' => $slot->id,
            'planned_start_at' => sprintf('%s %02d:00:00', $day, $hour), 'planned_end_at' => sprintf('%s %02d:00:00', $day, $hour + 1),
            'status' => 'scheduled', 'teaching_location' => 'branch']);
    }

    private function member(): ClassGroupMembership
    {
        return ClassGroupMembership::where('student_id', $this->student->id)->firstOrFail();
    }

    private function input(array $slots): array
    {
        return ['username' => 'murid_baru', 'full_name' => 'Murid Baru', 'password' => 'DpePassword123',
            'grade_name' => '5 SD', 'school_name' => 'SD Uji', 'guardian_phone' => '+62 812 3456 7890', 'branch_id' => $this->branch->id,
            'teacher_id' => $this->teacher->id, 'learning_session_ids' => array_map(fn ($s) => $s->id, $slots)];
    }

    public function test_multiple_same_day_sessions_repeat_each_week_without_other_sessions(): void
    {
        $one = $this->slot('Sesi 1');
        $two = $this->slot('Sesi 2');
        $three = $this->slot('Sesi 3');
        app(ClassLearningSessionService::class)->select($this->member(), [$one->id, $three->id], '2026-10-23');
        foreach (['2026-11-02', '2026-11-09'] as $day) {
            foreach ([$one, $two, $three] as $i => $slot) {
                $lesson = $this->lesson($slot, $day, 14 + $i);
                $this->assertSame($slot->id !== $two->id, app(StudentAttendanceService::class)->roster($lesson)->contains('id', $this->student->id));
            }
        }
    }

    public function test_legacy_memberships_keep_all_sessions_until_effective_date(): void
    {
        $one = $this->slot('Sesi 1');
        $two = $this->slot('Sesi 2');
        $this->assertCount(1, app(StudentAttendanceService::class)->roster($this->lesson($two, '2026-10-26')));
        app(ClassLearningSessionService::class)->select($this->member(), [$one->id], '2026-11-01');
        $this->assertCount(1, app(StudentAttendanceService::class)->roster($this->lesson($two, '2026-10-27')));
        $this->assertCount(0, app(StudentAttendanceService::class)->roster($this->lesson($two)));
    }

    public function test_changing_choices_preserves_previous_period_and_updates_version(): void
    {
        $one = $this->slot('Sesi 1');
        $two = $this->slot('Sesi 2');
        $m = $this->member();
        app(ClassLearningSessionService::class)->select($m, [$one->id], '2026-10-23');
        $version = app(BranchOperationsService::class)->version($m->fresh());
        app(ClassLearningSessionService::class)->select($m, [$two->id], '2026-11-05');
        $this->assertNotSame($version, app(BranchOperationsService::class)->version($m->fresh()));
        $this->assertCount(1, app(StudentAttendanceService::class)->roster($this->lesson($one, '2026-11-02')));
        $this->assertCount(0, app(StudentAttendanceService::class)->roster($this->lesson($one, '2026-11-09')));
        $this->assertCount(1, app(StudentAttendanceService::class)->roster($this->lesson($two, '2026-11-09')));
    }

    public function test_same_day_choice_change_invalidates_stale_version(): void
    {
        $one = $this->slot('Sesi 1');
        $two = $this->slot('Sesi 2');
        $m = $this->member();
        app(ClassLearningSessionService::class)->select($m, [$one->id], '2026-10-23');
        $version = app(BranchOperationsService::class)->version($m->fresh());
        app(ClassLearningSessionService::class)->select($m, [$two->id], '2026-10-23');
        $this->expectException(ValidationException::class);
        app(BranchOperationsService::class)->checkVersion($m->fresh(), ['record_version' => $version]);
    }

    public function test_sessions_from_another_group_cannot_be_selected(): void
    {
        $slot = $this->slot('Sesi lain', $this->otherGroup);
        $this->expectException(ValidationException::class);
        app(ClassLearningSessionService::class)->select($this->member(), [$slot->id], '2026-10-23');
    }

    public function test_choice_changes_cannot_rewrite_past_dates(): void
    {
        $slot = $this->slot('Sesi 1');
        $this->expectException(ValidationException::class);
        app(ClassLearningSessionService::class)->select($this->member(), [$slot->id], '2026-10-01');
    }

    public function test_empty_choice_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(ClassLearningSessionService::class)->select($this->member(), [], '2026-10-23');
    }

    public function test_saved_attendance_is_retained_when_session_selection_changes(): void
    {
        $slot = $this->slot('Sesi 1');
        $other = $this->slot('Sesi 2');
        $lesson = $this->lesson($slot, '2026-10-01');
        SessionStudent::create(['session_id' => $lesson->id, 'student_id' => $this->student->id, 'attendance_status' => 'present', 'recorded_at' => now()]);
        app(ClassLearningSessionService::class)->select($this->member(), [$other->id], '2026-10-23');
        $this->assertCount(1, app(StudentAttendanceService::class)->roster($lesson));
    }

    public function test_unrecorded_manual_roster_does_not_bypass_selected_sessions(): void
    {
        $slot = $this->slot('Sesi 1');
        $other = $this->slot('Sesi 2');
        $lesson = $this->lesson($slot);
        SessionStudent::create(['session_id' => $lesson->id, 'student_id' => $this->student->id]);
        app(ClassLearningSessionService::class)->select($this->member(), [$other->id], '2026-10-23');
        $this->assertCount(0, app(StudentAttendanceService::class)->roster($lesson));
    }

    public function test_admin_creates_all_student_records_atomically_with_nine_fields(): void
    {
        $one = $this->slot('Sesi 1');
        $three = $this->slot('Sesi 3');
        $this->lesson($one);
        $this->lesson($three, '2026-11-02', 16);
        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->input([$one, $three]))->assertSessionHasNoErrors()->assertRedirect();
        $user = User::where('username', 'murid_baru')->firstOrFail();
        $student = $user->studentProfile;
        $this->assertTrue(Hash::check('DpePassword123', $user->password_hash));
        $this->assertSame('5 SD', $student->grade_name);
        $this->assertSame('2026-10-23', $student->began_on->toDateString());
        $this->assertCount(1, $student->enrollments);
        $this->assertCount(1, $student->studentTeacherAssignments);
        $this->assertSame('+6281234567890', $student->guardians->first()->whatsapp_number);
        $this->assertCount(2, $student->classGroupMemberships->first()->learningSessionEnrollments);
    }

    public function test_superadmin_student_role_uses_same_provisioning_flow(): void
    {
        $slot = $this->slot('Sesi 1');
        $this->lesson($slot);
        $this->actingAs($this->superadmin)->post(route('superadmin.users.store'), $this->input([$slot]) + ['role_code' => 'student'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(User::where('username', 'murid_baru')->firstOrFail()->studentProfile);
    }

    public function test_other_branch_is_rejected_without_partial_user_creation(): void
    {
        $slot = $this->slot('Sesi 1');
        $this->lesson($slot);
        $data = $this->input([$slot]);
        $data['branch_id'] = $this->otherBranch->id;
        $this->actingAs($this->admin)->post(route('admin.users.store'), $data)->assertSessionHasErrors('branch_id');
        $this->assertDatabaseMissing('users', ['username' => 'murid_baru']);
    }

    public function test_another_teacher_session_is_rejected_without_partial_records(): void
    {
        $slot = $this->slot('Sesi 1');
        $this->lesson($slot);
        $data = $this->input([$slot]);
        $data['teacher_id'] = $this->otherTeacher->id;
        $this->actingAs($this->admin)->post(route('admin.users.store'), $data)->assertSessionHasErrors('learning_session_ids');
        $this->assertDatabaseMissing('users', ['username' => 'murid_baru']);
    }

    public function test_student_form_does_not_require_password_confirmation_or_email(): void
    {
        $slot = $this->slot('Sesi 1');
        $this->lesson($slot);
        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk()->assertSee('Nama siswa')->assertSee('Nomor telepon wali siswa')->assertSee('Sesi belajar')->assertDontSee('name="password_confirmation"', false)->assertDontSee('name="email"', false);
    }

    public function test_superadmin_role_switch_and_scoped_sessions_are_rendered(): void
    {
        $slot = $this->slot('Sesi 1');
        $this->lesson($slot);
        $this->actingAs($this->superadmin)->get(route('superadmin.users.create'))->assertOk()->assertSee('data-role-account', false)->assertSee('data-student-panel', false)->assertSee('Sesi 1');
        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertDontSee($this->otherBranch->name);
    }

    public function test_class_delete_hides_group_cancels_future_and_keeps_attendance(): void
    {
        $slot = $this->slot('Sesi 1');
        $future = $this->lesson($slot);
        $past = $this->lesson($slot, '2026-10-01');
        SessionStudent::create(['session_id' => $past->id, 'student_id' => $this->student->id, 'attendance_status' => 'present']);
        $this->actingAs($this->admin)->delete(route('admin.class-groups.destroy', $this->group), ['record_version' => app(BranchOperationsService::class)->version($this->group->fresh())])->assertSessionHasNoErrors()->assertRedirect(route('admin.class-groups.index'));
        $this->assertNull(ClassGroup::find($this->group->id));
        $this->assertSame('cancelled', $future->fresh()->status);
        $this->assertSame($this->group->name, $past->fresh()->classGroup->name);
        $this->assertCount(1, app(StudentAttendanceService::class)->roster($past->fresh()));
        $this->assertDatabaseHas('operational_deletion_events', ['record_id' => $this->group->id]);
    }

    public function test_student_delete_keeps_historical_profile_and_allows_new_username(): void
    {
        $oldUser = $this->student->portalUser;
        $username = $oldUser->username;
        $slot = $this->slot('Sesi 1');
        $lesson = $this->lesson($slot, '2026-10-01');
        SessionStudent::create(['session_id' => $lesson->id, 'student_id' => $this->student->id, 'attendance_status' => 'present']);
        app(OperationalDeletionService::class)->delete($this->admin, $oldUser);
        $this->assertNull(User::find($oldUser->id));
        $this->assertNull(Student::find($this->student->id));
        $this->assertSame($this->student->id, $lesson->fresh()->sessionStudents->first()->student->id);
        $this->assertSame('present', $lesson->fresh()->sessionStudents->first()->attendance_status);
        $new = $this->user('student', 'Siswa pengganti');
        $new->update(['username' => $username]);
        $this->assertSame($username, User::findOrFail($new->id)->username);
        $this->assertNotSame($oldUser->id, $new->id);
    }

    public function test_admin_cannot_delete_group_outside_their_branch(): void
    {
        $this->actingAs($this->admin)->delete(route('admin.class-groups.destroy', $this->otherGroup), ['record_version' => app(BranchOperationsService::class)->version($this->otherGroup)])->assertForbidden();
        $this->assertNotNull(ClassGroup::find($this->otherGroup->id));
    }

    public function test_admin_cannot_delete_teacher_account(): void
    {
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->teacher->user))->assertNotFound();
    }

    public function test_self_delete_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(OperationalDeletionService::class)->delete($this->superadmin, $this->superadmin);
    }

    public function test_future_saved_data_blocks_deletion_atomically(): void
    {
        $slot = $this->slot('Sesi 1');
        $lesson = $this->lesson($slot);
        SessionStudent::create(['session_id' => $lesson->id, 'student_id' => $this->student->id, 'individual_learning_note' => 'Catatan tersimpan']);
        try {
            app(OperationalDeletionService::class)->delete($this->superadmin, $this->group);
            $this->fail('Must reject');
        } catch (ValidationException) {
            $this->assertNotNull(ClassGroup::find($this->group->id));
            $this->assertSame('scheduled', $lesson->fresh()->status);
        }
    }

    public function test_deleted_class_code_can_be_reused_in_same_branch(): void
    {
        $code = $this->group->code;
        app(OperationalDeletionService::class)->delete($this->superadmin, $this->group);
        $new = $this->group('Kelas baru', $this->branch, $this->teacher);
        $new->update(['code' => $code]);
        $this->assertSame($code, $new->fresh()->code);
    }

    public function test_nonactive_flags_are_excluded_from_master_edit_requests(): void
    {
        $this->actingAs($this->superadmin)->put(route('superadmin.users.update', $this->teacher->user), ['username' => $this->teacher->user->username, 'full_name' => 'Guru diedit', 'role_code' => 'teacher', 'is_active' => false])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($this->teacher->user->fresh()->is_active);
    }

    public function test_status_endpoints_are_removed_and_delete_actions_are_present(): void
    {
        foreach (['superadmin.users', 'superadmin.branches', 'superadmin.programs', 'superadmin.class-groups', 'admin.class-groups'] as $name) {
            $this->assertFalse(Route::has($name.'.status'));
            $this->assertTrue(Route::has($name.'.destroy'));
        }
        $this->actingAs($this->superadmin)->get(route('superadmin.class-groups.show', $this->group))->assertOk()->assertSee('Hapus')->assertDontSee('Nonaktifkan kelompok');
    }

    public function test_superadmin_membership_edit_requires_current_version(): void
    {
        $m = $this->member();
        $slot = $this->slot('Sesi 1');
        $this->actingAs($this->superadmin)->get(route('superadmin.class-group-memberships.edit', $m))->assertOk()->assertSee('name="record_version"', false);
        $this->actingAs($this->superadmin)->put(route('superadmin.class-group-memberships.update', $m), ['class_group_id' => $m->class_group_id, 'student_id' => $m->student_id, 'starts_on' => '2026-10-01', 'record_version' => str_repeat('0', 64)])->assertSessionHasErrors('record_version');
    }

    public function test_branch_delete_archives_groups_and_closes_periods(): void
    {
        $slot = $this->slot('Sesi 1');
        $future = $this->lesson($slot);
        app(OperationalDeletionService::class)->delete($this->superadmin, $this->branch);
        $this->assertNull(Branch::find($this->branch->id));
        $this->assertNull(ClassGroup::find($this->group->id));
        $this->assertSame('cancelled', $future->fresh()->status);
        $this->assertSame('2026-10-23', $this->student->enrollments->first()->ends_on->toDateString());
    }

    public function test_program_delete_preserves_program_labels_in_past_lessons(): void
    {
        $slot = $this->slot('Sesi 1');
        $past = $this->lesson($slot, '2026-10-01');
        app(OperationalDeletionService::class)->delete($this->superadmin, $this->program);
        $this->assertNull(Program::find($this->program->id));
        $this->assertSame($this->program->name, $past->fresh()->program->name);
    }

    public function test_new_and_reused_session_identity_are_scoped_to_group(): void
    {
        $service = app(ClassLearningSessionService::class);
        $id = $service->resolve(['class_group_id' => $this->group->id, 'learning_session_name' => 'Sesi 1']);
        $this->assertSame($id, $service->resolve(['class_group_id' => $this->group->id, 'class_learning_session_id' => $id]));
        $this->expectException(ValidationException::class);
        $service->resolve(['class_group_id' => $this->otherGroup->id, 'class_learning_session_id' => $id]);
    }

    public function test_deleting_admin_closes_assignments_and_removes_future_period_with_audit(): void
    {
        $future = BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id, 'branch_id' => $this->otherBranch->id, 'starts_on' => '2026-11-01', 'ends_on' => '2026-12-31']);
        app(OperationalDeletionService::class)->delete($this->superadmin, $this->admin);
        $this->assertNull(User::find($this->admin->id));
        $this->assertDatabaseHas('branch_admin_assignments', ['admin_user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'ends_on' => '2026-10-23']);
        $this->assertDatabaseMissing('branch_admin_assignments', ['id' => $future->id]);
        $event = DB::table('operational_deletion_events')->where('record_id', $this->admin->id)->first();
        $this->assertStringContainsString($future->id, $event->before_snapshot);
        $this->assertStringNotContainsString('password_hash', $event->before_snapshot);
    }
}
