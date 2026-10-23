<?php

namespace Tests\Feature\Admin;

use App\Models\{Branch, BranchAdminAssignment, ClassGroup, ClassGroupMembership, Program, Student,
    StudentEnrollment, StudentTeacherAssignment, Teacher, TeacherBranchAssignment, User};
use App\Services\TeachingDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeachingDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $superadmin;
    private Branch $branch;
    private Branch $foreignBranch;
    private Program $program;
    private Teacher $teacher;
    private Teacher $classTeacher;
    private Teacher $foreignTeacher;
    private Teacher $emptyTeacher;
    private Student $student;
    private Student $foreignStudent;
    private StudentTeacherAssignment $assignment;
    private ClassGroup $group;
    private ClassGroup $classOnlyGroup;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Makassar'));
        $this->admin = $this->user('admin', 'Admin Utama');
        $this->superadmin = $this->user('superadmin', 'Super Admin');
        $this->branch = $this->branch('Cabang Utama');
        $this->foreignBranch = $this->branch('Cabang Luar Rahasia');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id,
            'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => 'ENGLISH', 'name' => 'English Regular',
            'class_type' => 'regular', 'is_active' => true]);
        $this->teacher = $this->teacher('Alpha Teacher', $this->branch);
        $this->classTeacher = $this->teacher('Beta Teacher Kelas');
        $this->foreignTeacher = $this->teacher('Teacher Luar Rahasia', $this->foreignBranch);
        $this->emptyTeacher = $this->teacher('Teacher Tanpa Siswa', $this->branch);
        $this->student = $this->student('Siswa Utama', $this->branch);
        $this->foreignStudent = $this->student('Siswa Luar Rahasia', $this->foreignBranch);
        $this->assignment = $this->assign($this->student, $this->teacher);
        $this->assign($this->foreignStudent, $this->foreignTeacher);
        $this->group = $this->group($this->student, $this->teacher);
        $this->classOnlyGroup = $this->group($this->student, $this->classTeacher);
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function user(string $role, string $name): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(15), 'full_name' => $name,
            'role_code' => $role, 'is_active' => true, 'password_hash' => 'directory-test-password']);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => $name,
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function teacher(string $name, ?Branch $branch = null): Teacher
    {
        $teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', $name)->id,
            'is_active' => true, 'whatsapp_number' => '081234567890']);
        if ($branch) $this->teacherBranch($teacher, $branch);
        return $teacher;
    }

    private function teacherBranch(Teacher $teacher, Branch $branch): void
    {
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $teacher->id,
            'branch_id' => $branch->id, 'starts_on' => '2026-01-01']);
    }

    private function student(string $name, Branch $branch): Student
    {
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student', $name)->id,
            'full_name' => $name, 'began_on' => '2026-09-01', 'status' => 'active']);
        $this->enroll($student, $branch);
        return $student;
    }

    private function enroll(Student $student, Branch $branch): void
    {
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id,
            'branch_id' => $branch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-09-01']);
    }

    private function assign(Student $student, Teacher $teacher): StudentTeacherAssignment
    {
        return StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id,
            'teacher_id' => $teacher->id, 'starts_on' => '2026-09-01', 'is_report_owner' => false]);
    }

    private function group(Student $student, Teacher $teacher): ClassGroup
    {
        $group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id,
            'program_id' => $this->program->id, 'default_teacher_id' => $teacher->id,
            'code' => Str::random(10), 'name' => 'Kelompok Belajar', 'is_active' => true]);
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $group->id,
            'student_id' => $student->id, 'starts_on' => '2026-09-01']);
        return $group;
    }

    private function namesForStudent(): array
    {
        return app(TeachingDirectoryService::class)->teachersForStudents($this->admin, collect([$this->student->id]))
            ->get($this->student->id, collect())->pluck('teacher_id')->all();
    }

    private function summary(Teacher $teacher): array
    {
        return app(TeachingDirectoryService::class)->teacherSummaries($this->admin, collect([$teacher->id]))
            ->get($teacher->id, ['count' => 0, 'names' => collect()]);
    }

    public function test_students_show_current_direct_and_group_teachers_without_duplicates(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.index'))->assertOk()
            ->assertSee('Teacher pengajar')->assertSee('Alpha Teacher')->assertSee('Beta Teacher Kelas')
            ->assertDontSee('Teacher Luar Rahasia')->assertDontSee('Siswa Luar Rahasia');
        $this->assertSame(2, $response->viewData('studentTeachers')->get($this->student->id)->count());
        $this->assertEqualsCanonicalizing([$this->teacher->id, $this->classTeacher->id], $this->namesForStudent());
        $this->assertFalse($this->assignment->is_report_owner);
    }

    public function test_teacher_summaries_show_counts_and_the_complete_list_is_in_details(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.teachers.index'))->assertOk()
            ->assertSee('Siswa yang diajar')->assertSee('Lihat semua')->assertSee('Teacher Tanpa Siswa');
        $this->assertSame(3, $response->viewData('teachers')->total());
        $this->assertSame(1, $response->viewData('teacherSummaries')->get($this->teacher->id)['count']);
        $response->assertSee(route('admin.teachers.show', $this->teacher).'#teacher-students', false);
        $this->get(route('admin.teachers.show', $this->teacher))->assertOk()->assertSee('Detail siswa')
            ->assertSee(route('admin.students.show', $this->student), false)
            ->assertViewHas('teachingStudents', fn ($students) => $students->total() === 1);
        $this->get(route('admin.teachers.show', $this->emptyTeacher))->assertOk()->assertSee('Belum ada siswa aktif');
    }

    public function test_multiple_groups_and_direct_assignments_count_a_student_once(): void
    {
        $this->group($this->student, $this->teacher);
        $this->assign($this->student, $this->teacher);
        $this->assertSame(1, $this->summary($this->teacher)['count']);
        $this->assertSame(1, collect($this->namesForStudent())->filter(fn ($id) => $id === $this->teacher->id)->count());
        $this->actingAs($this->admin)->get(route('admin.teachers.show', $this->teacher))->assertOk()
            ->assertViewHas('teachingStudents', fn ($students) => $students->total() === 1);
    }

    public function test_group_only_teacher_can_be_viewed_but_cannot_be_edited_without_branch_authority(): void
    {
        $this->actingAs($this->admin)->get(route('admin.teachers.show', $this->classTeacher))->assertOk()
            ->assertSee('Siswa Utama')->assertDontSee(route('admin.teachers.edit', $this->classTeacher), false);
        $this->get(route('admin.teachers.edit', $this->classTeacher))->assertForbidden();
        $before = $this->classTeacher->fresh()->getAttributes();
        $this->put(route('admin.teachers.update', $this->classTeacher), ['user_id' => $this->classTeacher->user_id,
            'whatsapp_number' => $this->classTeacher->whatsapp_number, 'is_active' => '1'])->assertForbidden();
        $this->assertSame($before, $this->classTeacher->fresh()->getAttributes());
    }

    public function test_direct_private_tutor_without_branch_assignment_is_in_both_directions(): void
    {
        $teacher = $this->teacher('Private Teacher');
        $this->assign($this->student, $teacher);
        $this->assertContains($teacher->id, $this->namesForStudent());
        $this->assertSame(1, $this->summary($teacher)['count']);
        $this->actingAs($this->admin)->get(route('admin.teachers.show', $teacher))->assertOk()
            ->assertViewHas('teachingStudents', fn ($students) => $students->total() === 1);
    }

    public function test_multibranch_student_does_not_reveal_a_teacher_assigned_only_to_the_other_branch(): void
    {
        $this->student->enrollments()->update(['ends_on' => '2026-12-31']);
        $this->enroll($this->student, $this->foreignBranch);
        $this->assign($this->student, $this->foreignTeacher);
        $this->assertNotContains($this->foreignTeacher->id, $this->namesForStudent());
        $this->actingAs($this->admin)->get(route('admin.students.index'))->assertOk()->assertDontSee('Luar Rahasia');
        $this->get(route('admin.teachers.show', $this->foreignTeacher))->assertForbidden();
        $this->actingAs($this->superadmin)->get(route('admin.students.index'))->assertOk()->assertSee('Teacher Luar Rahasia');
    }

    public function test_shared_teacher_only_lists_students_and_branch_assignments_in_the_admin_scope(): void
    {
        $this->teacher->branchAssignments()->update(['ends_on' => '2026-12-31']);
        $this->teacherBranch($this->teacher, $this->foreignBranch);
        $this->assign($this->foreignStudent, $this->teacher);
        $this->assertSame(1, $this->summary($this->teacher)['count']);
        $this->actingAs($this->admin)->get(route('admin.teachers.show', $this->teacher))->assertOk()
            ->assertDontSee('Luar Rahasia')->assertViewHas('teachingStudents', fn ($students) => $students->total() === 1);
        $this->actingAs($this->superadmin)->get(route('admin.teachers.show', $this->teacher))->assertOk()
            ->assertSee('Siswa Luar Rahasia')->assertViewHas('teachingStudents', fn ($students) => $students->total() === 2);
    }

    public static function inactiveSources(): array
    {
        return array_map(fn ($name) => [$name], ['assignment_future', 'assignment_expired', 'student_inactive',
            'student_future', 'teacher_inactive', 'account_inactive', 'enrollment_future', 'enrollment_expired']);
    }

    #[DataProvider('inactiveSources')]
    public function test_inactive_direct_sources_are_excluded_in_both_directions(string $source): void
    {
        $this->group->update(['is_active' => false]);
        match ($source) {
            'assignment_future' => $this->assignment->update(['starts_on' => '2026-10-23']),
            'assignment_expired' => $this->assignment->update(['ends_on' => '2026-10-21']),
            'student_inactive' => $this->student->update(['status' => 'inactive']),
            'student_future' => $this->student->update(['began_on' => '2026-10-23']),
            'teacher_inactive' => $this->teacher->update(['is_active' => false]),
            'account_inactive' => $this->teacher->user->update(['is_active' => false]),
            'enrollment_future' => $this->student->enrollments()->update(['starts_on' => '2026-10-23']),
            'enrollment_expired' => $this->student->enrollments()->update(['ends_on' => '2026-10-21']),
        };
        $this->assertNotContains($this->teacher->id, $this->namesForStudent());
        $this->assertSame(0, $this->summary($this->teacher)['count']);
    }

    public static function inactiveGroupSources(): array
    {
        return [['future'], ['expired'], ['inactive_group'], ['wrong_program']];
    }

    #[DataProvider('inactiveGroupSources')]
    public function test_inactive_or_incompatible_class_membership_does_not_create_a_teaching_pair(string $source): void
    {
        $membership = $this->classOnlyGroup->memberships()->firstOrFail();
        if ($source === 'future') $membership->update(['starts_on' => '2026-10-23']);
        if ($source === 'expired') $membership->update(['ends_on' => '2026-10-21']);
        if ($source === 'inactive_group') $this->classOnlyGroup->update(['is_active' => false]);
        if ($source === 'wrong_program') {
            $program = Program::create(['id' => (string) Str::uuid(), 'code' => 'OTHER', 'name' => 'Other', 'class_type' => 'regular', 'is_active' => true]);
            $this->classOnlyGroup->update(['program_id' => $program->id]);
        }
        $this->assertNotContains($this->classTeacher->id, $this->namesForStudent());
        $this->assertSame(0, $this->summary($this->classTeacher)['count']);
    }

    public function test_current_date_boundaries_are_inclusive(): void
    {
        $this->assignment->update(['starts_on' => '2026-10-22', 'ends_on' => '2026-10-22']);
        $this->teacher->branchAssignments()->update(['starts_on' => '2026-10-22', 'ends_on' => '2026-10-22']);
        $this->student->enrollments()->update(['starts_on' => '2026-10-22', 'ends_on' => '2026-10-22']);
        $this->classOnlyGroup->memberships()->update(['starts_on' => '2026-10-22', 'ends_on' => '2026-10-22']);
        $this->assertContains($this->teacher->id, $this->namesForStudent());
        $this->assertContains($this->classTeacher->id, $this->namesForStudent());
    }

    public function test_preview_is_limited_to_three_names_while_details_paginate_all_unique_students(): void
    {
        for ($i = 0; $i < 17; $i++) $this->assign($this->student('Anak '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), $this->branch), $this->teacher);
        $summary = $this->summary($this->teacher);
        $this->assertSame(18, $summary['count']); $this->assertCount(3, $summary['names']);
        $response = $this->actingAs($this->admin)->get(route('admin.teachers.show', $this->teacher))->assertOk();
        $paginator = $response->viewData('teachingStudents');
        $this->assertSame(18, $paginator->total()); $this->assertCount(15, $paginator);
        $this->assertStringContainsString('students_page=2', $paginator->nextPageUrl());
        $this->assertStringEndsWith('#teacher-students', $paginator->nextPageUrl());
        $this->get($paginator->nextPageUrl())->assertOk()->assertViewHas('teachingStudents', fn ($students) => $students->count() === 3);
    }

    public function test_expired_admin_assignment_removes_all_teaching_names_and_detail_access(): void
    {
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-10-21']);
        $this->assertSame([], $this->namesForStudent());
        $this->actingAs($this->admin)->get(route('admin.teachers.index'))->assertOk()->assertViewHas('teachers', fn ($teachers) => $teachers->total() === 0);
        $this->get(route('admin.teachers.show', $this->classTeacher))->assertForbidden();
        $this->get(route('admin.students.show', $this->student))->assertForbidden();
    }

    public function test_links_and_names_are_escaped_and_listing_gets_do_not_mutate_business_data(): void
    {
        $this->teacher->user->update(['full_name' => '<script>bad()</script>']);
        $tables = ['students', 'teachers', 'student_teacher_assignments', 'student_enrollments',
            'class_groups', 'class_group_memberships', 'teacher_branch_assignments'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
        $this->actingAs($this->admin)->get(route('admin.students.index'))->assertOk()->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false);
        $this->get(route('admin.teachers.index'))->assertOk();
        $this->get(route('admin.teachers.show', $this->teacher))->assertOk();
        foreach ($before as $table => $rows) $this->assertSame($rows, DB::table($table)->get()->toJson());
    }
}
