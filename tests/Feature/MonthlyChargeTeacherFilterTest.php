<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LessonSession;
use App\Models\MonthlyCharge;
use App\Models\MonthlyReport;
use App\Models\Program;
use App\Models\ReportCycle;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonthlyChargeTeacherFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $admin;
    private Branch $branch;
    private Branch $foreign;
    private Program $program;
    private Teacher $teacherA;
    private Teacher $teacherB;
    private Teacher $foreignTeacher;
    private Student $studentA;
    private Student $studentB;
    private Student $shared;
    private Student $unassigned;
    private Student $foreignStudent;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 10:00:00');
        $this->superadmin = $this->account('superadmin', 'Super Admin');
        $this->admin = $this->account('admin', 'Admin Cabang');
        $this->branch = $this->branch('Cabang Saya');
        $this->foreign = $this->branch('Cabang Luar');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id,
            'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => 'EN', 'name' => 'English', 'class_type' => 'private', 'is_active' => true]);
        $this->teacherA = $this->teacher('Guru A', $this->branch);
        $this->teacherB = $this->teacher('Guru B', $this->branch);
        $this->foreignTeacher = $this->teacher('Guru Luar Rahasia', $this->foreign);
        $this->studentA = $this->student('Siswa Guru A');
        $this->studentB = $this->student('Siswa Guru B');
        $this->shared = $this->student('Siswa Dua Guru');
        $this->unassigned = $this->student('Siswa Belum Ada Guru');
        $this->foreignStudent = $this->student('Siswa Luar Rahasia', $this->foreign);
        foreach ([$this->studentA, $this->shared, $this->foreignStudent] as $s) { $this->assign($s, $this->teacherA); }
        foreach ([$this->studentB, $this->shared] as $s) { $this->assign($s, $this->teacherB); }
        $this->assign($this->foreignStudent, $this->foreignTeacher);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $role, string $name): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => 'akun_'.Str::random(12),
            'password_hash' => 'fixture-password', 'full_name' => $name, 'role_code' => $role, 'is_active' => true]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => $name,
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
    }

    private function teacher(string $name, Branch $branch): Teacher
    {
        $teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->account('teacher', $name)->id,
            'whatsapp_number' => '081234567890', 'is_active' => true]);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $teacher->id,
            'branch_id' => $branch->id, 'starts_on' => '2026-01-01']);

        return $teacher;
    }

    private function student(string $name, ?Branch $branch = null): Student
    {
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->account('student', $name)->id,
            'full_name' => $name, 'began_on' => '2026-01-01', 'status' => 'active']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id,
            'branch_id' => ($branch ?? $this->branch)->id, 'program_id' => $this->program->id, 'starts_on' => '2026-01-01']);

        return $student;
    }

    private function assign(Student $student, Teacher $teacher, ?string $ends = null): void
    {
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id,
            'teacher_id' => $teacher->id, 'starts_on' => '2026-01-01', 'ends_on' => $ends, 'is_report_owner' => false]);
    }

    private function filters(?Teacher $teacher = null, string $month = '2026-11-01'): array
    {
        return ['teacher_filter_id' => $teacher?->id ?? '', 'charge_month' => $month];
    }

    private function data(?Student $student = null, ?Teacher $teacher = null): array
    {
        return $this->filters($teacher) + ['student_id' => ($student ?? $this->studentA)->id,
            'branch_id' => $this->branch->id, 'amount_idr' => 500000, 'due_on' => '2026-11-20'];
    }

    public static function roles(): array { return [['admin'], ['superadmin']]; }

    private function expectedA(string $role): array
    {
        return $role === 'admin' ? [$this->studentA->id, $this->shared->id]
            : [$this->studentA->id, $this->shared->id, $this->foreignStudent->id];
    }

    #[DataProvider('roles')]
    public function test_create_and_dynamic_options_filter_by_teacher_without_creating_charges(string $role): void
    {
        $this->actingAs($this->$role);
        $page = $this->get(route($role.'.monthly-charges.create', $this->filters($this->teacherA)))->assertOk()
            ->assertSee('name="teacher_filter_id"', false)->assertSee('Tagihan tetap ditujukan ke siswa yang dipilih.');
        $this->assertEqualsCanonicalizing($this->expectedA($role), $page->viewData('students')->pluck('id')->all());
        $response = $this->getJson(route($role.'.monthly-charges.student-options', $this->filters($this->teacherB)))->assertOk();
        $this->assertEqualsCanonicalizing([$this->studentB->id, $this->shared->id], array_column($response->json('students'), 'id'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('monthly_charges', 0);
    }

    #[DataProvider('roles')]
    public function test_empty_teacher_filter_keeps_unassigned_students_available(string $role): void
    {
        $response = $this->actingAs($this->$role)->getJson(route($role.'.monthly-charges.student-options', $this->filters()))->assertOk();
        $this->assertContains($this->unassigned->id, array_column($response->json('students'), 'id'));
        $this->post(route($role.'.monthly-charges.store'), $this->data($this->unassigned))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('monthly_charges', ['student_id' => $this->unassigned->id]);
    }

    #[DataProvider('roles')]
    public function test_selected_teacher_never_becomes_the_charge_recipient(string $role): void
    {
        $this->actingAs($this->$role)->post(route($role.'.monthly-charges.store'), $this->data(teacher: $this->teacherA))
            ->assertRedirect()->assertSessionHasNoErrors();
        $charge = MonthlyCharge::sole();
        $this->assertSame($this->studentA->id, $charge->student_id);
        $this->assertSame('2026-11-01', $charge->charge_month->toDateString());
        $this->assertSame('500000.00', $charge->amount_idr);
        $this->assertArrayNotHasKey('teacher_filter_id', $charge->getAttributes());
        $this->assertDatabaseMissing('monthly_charges', ['student_id' => $this->teacherA->id]);
        $this->assertDatabaseMissing('monthly_charges', ['student_id' => $this->teacherA->user_id]);
    }

    #[DataProvider('roles')]
    public function test_forged_or_stale_student_teacher_combinations_cannot_be_saved(string $role): void
    {
        $this->actingAs($this->$role);
        $this->postJson(route($role.'.monthly-charges.store'), $this->data($this->studentB, $this->teacherA))
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        StudentTeacherAssignment::where('student_id', $this->studentA->id)->delete();
        $this->postJson(route($role.'.monthly-charges.store'), $this->data(teacher: $this->teacherA))
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $data = $this->data(teacher: $this->teacherA); $data['student_id'] = $this->teacherA->user_id;
        $this->postJson(route($role.'.monthly-charges.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('monthly_charges', 0);
    }

    public function test_admin_options_and_forged_filter_cannot_disclose_another_branch(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson(route('admin.monthly-charges.student-options', $this->filters($this->teacherA)))->assertOk();
        $this->assertNotContains($this->foreignStudent->id, array_column($response->json('students'), 'id'));
        $this->assertNotContains($this->foreignTeacher->id, array_column($response->json('teachers'), 'id'));
        $this->getJson(route('admin.monthly-charges.student-options', $this->filters($this->foreignTeacher)))
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_filter_id')->assertDontSee('Guru Luar Rahasia');
        $this->postJson(route('admin.monthly-charges.store'), $this->data($this->foreignStudent, $this->teacherA))
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('monthly_charges', 0);
    }

    #[DataProvider('roles')]
    public function test_assignment_dates_and_inactive_or_deleted_students_are_respected(string $role): void
    {
        StudentTeacherAssignment::where('student_id', $this->studentA->id)->update(['ends_on' => '2026-10-31']);
        $this->shared->update(['status' => 'inactive']);
        $this->foreignStudent->delete();
        $response = $this->actingAs($this->$role)->getJson(route($role.'.monthly-charges.student-options', $this->filters($this->teacherA)))
            ->assertOk()->assertJsonCount(0, 'students');
        $response = $this->getJson(route($role.'.monthly-charges.student-options', $this->filters($this->teacherA, '2026-10-15')))->assertOk();
        $this->assertSame([$this->studentA->id], array_column($response->json('students'), 'id'));
        $this->teacherA->user->update(['is_active' => false]);
        $this->getJson(route($role.'.monthly-charges.student-options', $this->filters($this->teacherA)))
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_filter_id');
    }

    #[DataProvider('roles')]
    public function test_class_and_lesson_participants_follow_the_existing_siswa_binaan_rules(string $role): void
    {
        $classStudent = $this->student('Siswa Dari Kelas');
        $lessonStudent = $this->student('Siswa Dari Pertemuan');
        $group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id,
            'program_id' => $this->program->id, 'default_teacher_id' => $this->teacherA->id,
            'code' => 'GROUP-A', 'name' => 'Kelas A', 'is_active' => true]);
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'student_id' => $classStudent->id,
            'class_group_id' => $group->id, 'starts_on' => '2026-11-01']);
        $lesson = LessonSession::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id,
            'program_id' => $this->program->id, 'teacher_id' => $this->teacherA->id,
            'planned_start_at' => '2026-11-10 10:00:00', 'planned_end_at' => '2026-11-10 11:00:00', 'status' => 'completed']);
        SessionStudent::create(['session_id' => $lesson->id, 'student_id' => $lessonStudent->id]);
        $response = $this->actingAs($this->$role)->getJson(route($role.'.monthly-charges.student-options', $this->filters($this->teacherA)))->assertOk();
        $this->assertEqualsCanonicalizing([...$this->expectedA($role), $classStudent->id, $lessonStudent->id], array_column($response->json('students'), 'id'));
        $lesson->update(['status' => 'cancelled']);
        $response = $this->getJson(route($role.'.monthly-charges.student-options', $this->filters($this->teacherA)))->assertOk();
        $this->assertNotContains($lessonStudent->id, array_column($response->json('students'), 'id'));
    }

    public function test_teacher_who_only_owns_a_report_is_available_to_admin_consistently_with_siswa_binaan(): void
    {
        $reportTeacher = $this->teacher('Guru Pemilik Rapor', $this->foreign);
        $cycle = ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->unassigned->id,
            'cycle_number' => 1, 'start_month' => '2026-11-01', 'end_month' => '2027-01-01', 'share_due_on' => '2027-02-01']);
        MonthlyReport::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->unassigned->id,
            'branch_id' => $this->branch->id, 'teacher_id' => $reportTeacher->id, 'cycle_id' => $cycle->id,
            'report_month' => '2026-11-01', 'due_on' => '2026-11-30', 'video_target' => 4, 'status' => 'draft']);
        $this->actingAs($this->admin)->getJson(route('admin.monthly-charges.student-options', $this->filters($reportTeacher)))
            ->assertOk()->assertJsonPath('students.0.id', $this->unassigned->id)->assertJsonCount(1, 'students');
    }

    #[DataProvider('roles')]
    public function test_validation_errors_keep_teacher_month_and_student_choices(string $role): void
    {
        $data = $this->data(teacher: $this->teacherA); $data['amount_idr'] = -1;
        $url = route($role.'.monthly-charges.create');
        $this->actingAs($this->$role)->from($url)->post(route($role.'.monthly-charges.store'), $data)
            ->assertRedirect($url)->assertSessionHasErrors('amount_idr')->assertSessionHasInput('teacher_filter_id', $this->teacherA->id);
        $response = $this->get($url)->assertOk();
        $this->assertSame($this->teacherA->id, $response->viewData('chargeFilters')['teacher_filter_id']);
        $this->assertEqualsCanonicalizing($this->expectedA($role), $response->viewData('students')->pluck('id')->all());
    }

    public function test_legacy_edit_keeps_its_current_inactive_student_and_has_no_teacher_filter(): void
    {
        $charge = MonthlyCharge::create(['id' => (string) Str::uuid(), 'student_id' => $this->studentA->id,
            'branch_id' => $this->branch->id, 'charge_month' => '2026-11-01']);
        $this->studentA->update(['status' => 'inactive']);
        foreach (['admin', 'superadmin'] as $role) {
            $this->actingAs($this->$role)->get(route($role.'.monthly-charges.edit', $charge))->assertOk()
                ->assertDontSee('name="teacher_filter_id"', false)->assertViewHas('students', fn ($students) => $students->contains('id', $this->studentA->id));
        }
    }

    public function test_options_endpoint_rejects_unprivileged_roles_and_invalid_inputs(): void
    {
        $this->getJson(route('admin.monthly-charges.student-options'))->assertUnauthorized();
        foreach (['teacher', 'student'] as $role) {
            foreach (['admin', 'superadmin'] as $prefix) {
                $this->actingAs($this->account($role, 'Akun Biasa'))->getJson(route($prefix.'.monthly-charges.student-options'))->assertForbidden();
            }
        }
        foreach (['admin', 'superadmin'] as $role) {
            $this->actingAs($this->$role);
            $this->getJson(route($role.'.monthly-charges.student-options', ['charge_month' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('charge_month');
            $this->getJson(route($role.'.monthly-charges.student-options', ['teacher_filter_id' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('teacher_filter_id');
        }
    }
}
