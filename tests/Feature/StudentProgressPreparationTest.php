<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LessonSession;
use App\Models\MonthlyReport;
use App\Models\Program;
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
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentProgressPreparationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private Program $program;

    private Teacher $dipta;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-11 18:00:00', 'Asia/Makassar'));
        $this->admin = $this->user('admin', 'Admin Cabang');
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'DPE Test', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'English', 'class_type' => 'regular', 'is_active' => true]);
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-10-01']);
        $this->dipta = $this->teacher('Dipta', '2026-10-10');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'password_hash' => 'fixture-hash', 'full_name' => $name, 'role_code' => $role, 'is_active' => true]);
    }

    private function teacher(string $name, ?string $starts = null, ?string $ends = null): Teacher
    {
        $teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', $name)->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        if ($starts) {
            TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $teacher->id, 'branch_id' => $this->branch->id, 'starts_on' => $starts, 'ends_on' => $ends]);
        }

        return $teacher;
    }

    private function student(string $name, string $join = '2026-10-09', array $enrollment = []): Student
    {
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student', $name.' Parent')->id, 'full_name' => $name, 'began_on' => $join, 'status' => 'active']);
        StudentEnrollment::create($enrollment + ['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => $join]);

        return $student;
    }

    private function assignment(Student $student, ?Teacher $teacher = null, array $extra = []): StudentTeacherAssignment
    {
        return StudentTeacherAssignment::create($extra + ['id' => (string) Str::uuid(), 'student_id' => $student->id, 'teacher_id' => ($teacher ?? $this->dipta)->id, 'starts_on' => '2026-10-10', 'is_report_owner' => false]);
    }

    private function group(Student $student, ?Teacher $teacher = null, array $membership = [], array $extra = []): ClassGroup
    {
        $group = ClassGroup::create($extra + ['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'default_teacher_id' => ($teacher ?? $this->dipta)->id, 'code' => Str::random(12), 'name' => 'Kelas Dipta', 'is_active' => true]);
        ClassGroupMembership::create($membership + ['id' => (string) Str::uuid(), 'student_id' => $student->id, 'class_group_id' => $group->id, 'starts_on' => '2026-10-10']);

        return $group;
    }

    private function lesson(Student $student, ?Teacher $teacher = null, array $extra = []): LessonSession
    {
        $session = LessonSession::create($extra + ['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => ($teacher ?? $this->dipta)->id, 'program_id' => $this->program->id, 'planned_start_at' => '2026-10-10 09:00:00', 'planned_end_at' => '2026-10-10 10:00:00', 'teaching_location' => 'branch', 'status' => 'scheduled']);
        SessionStudent::create(['session_id' => $session->id, 'student_id' => $student->id]);

        return $session;
    }

    private function preparation(Student $student): TestResponse
    {
        return $this->actingAs($this->admin)->get(route('admin.student-progress.preparation', ['student' => $student, 'month' => '2026-10']))->assertOk();
    }

    private function submit(Student $student, Teacher $teacher, array $extra = []): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('admin.student-progress.prepare', $student), $extra + ['month' => '2026-10', 'teacher_id' => $teacher->id, 'video_target' => 2]);
    }

    private function xpath(TestResponse $response): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());

        return new \DOMXPath($dom);
    }

    public function test_ketut_starting_before_teacher_branch_assignment_gets_form_and_dipta_suggestion(): void
    {
        $student = $this->student('Ketut Regeg', '2026-10-09');
        $assignment = $this->assignment($student);
        $response = $this->preparation($student)->assertSee('Dipta')->assertSee('09 Oct 2026')->assertSee('Teacher siswa ini')
            ->assertViewHas('suggestedTeacher', $this->dipta->id);
        $xpath = $this->xpath($response);
        $this->assertSame(1, $xpath->query('//select[@name="teacher_id"]/optgroup[@label="Teacher siswa ini"]/option[@selected and @value="'.$this->dipta->id.'"]')->length);
        $this->submit($student, $this->dipta)->assertRedirect();
        $report = MonthlyReport::firstOrFail();
        $this->assertSame($this->dipta->id, $report->teacher_id);
        $this->assertSame('2026-10-01', $report->cycle->start_month->toDateString());
        $this->assertSame('2026-12-01', $report->cycle->end_month->toDateString());
        $this->assertFalse($assignment->fresh()->is_report_owner);
        $this->assertSame('2026-10-10', $this->dipta->branchAssignments()->first()->starts_on->toDateString());
        $this->assertSame('2026-10-09', $student->enrollments()->first()->starts_on->toDateString());
    }

    public function test_tes_one_without_assigned_teacher_can_choose_branch_teacher_who_started_later_that_month(): void
    {
        $student = $this->student('Tes 1', '2026-10-01');
        $response = $this->preparation($student)->assertSee('Dipta')->assertViewHas('suggestedTeacher', null)
            ->assertSee('Belum ada Teacher siswa yang aktif pada periode ini');
        $xpath = $this->xpath($response);
        $this->assertSame(0, $xpath->query('//select[@name="teacher_id"]//option[@selected and @value!=""]')->length);
        $this->submit($student, $this->dipta)->assertRedirect();
        $this->assertSame($this->dipta->id, MonthlyReport::firstOrFail()->teacher_id);
        $this->assertDatabaseCount('student_teacher_assignments', 0);
    }

    public function test_wayan_class_teacher_is_preselected_without_separate_branch_assignment_and_other_teacher_stays_available(): void
    {
        $student = $this->student('Wayan King', '2026-10-10');
        $this->dipta->branchAssignments()->delete();
        $this->group($student);
        $other = $this->teacher('Teacher Nama Saya', '2026-10-01');
        $response = $this->preparation($student)->assertSee('Dipta')->assertSee($other->user->full_name)
            ->assertViewHas('suggestedTeacher', $this->dipta->id);
        $xpath = $this->xpath($response);
        $this->assertSame(1, $xpath->query('//select[@name="teacher_id"]/optgroup[@label="Teacher siswa ini"]/option[@value="'.$this->dipta->id.'" and @selected]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="teacher_id"]/optgroup[@label="Teacher cabang lainnya"]/option[@value="'.$other->id.'" and not(@selected)]')->length);
        $this->submit($student, $this->dipta)->assertRedirect();
        $this->assertSame($this->dipta->id, MonthlyReport::firstOrFail()->teacher_id);
        $this->assertDatabaseCount('teacher_branch_assignments', 1);
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_direct_assignment_without_report_owner_or_branch_record_is_valid_for_this_student_only(): void
    {
        $student = $this->student('Siswa Private');
        $other = $this->student('Siswa Tidak Ditugaskan');
        $this->dipta->branchAssignments()->delete();
        $this->assignment($student);
        $this->preparation($student)->assertViewHas('suggestedTeacher', $this->dipta->id);
        $this->preparation($other)->assertViewHas('teachers', fn ($teachers) => $teachers->isEmpty());
        $this->submit($other, $this->dipta)->assertSessionHasErrors('teacher_id');
        $this->assertDatabaseCount('monthly_reports', 0);
        $this->submit($student, $this->dipta)->assertRedirect();
        $this->assertDatabaseCount('teacher_branch_assignments', 0);
    }

    public function test_private_session_teacher_is_suggested_without_creating_attendance_or_branch_assignment(): void
    {
        $student = $this->student('Private Dipta');
        $this->dipta->branchAssignments()->delete();
        $session = $this->lesson($student);
        $this->preparation($student)->assertViewHas('suggestedTeacher', $this->dipta->id);
        $this->submit($student, $this->dipta)->assertRedirect();
        $record = $session->sessionStudents()->firstOrFail();
        $this->assertNull($record->attendance_status);
        $this->assertNull($record->recorded_at);
        $this->assertDatabaseCount('session_students', 1);
        $this->assertDatabaseCount('teacher_branch_assignments', 0);
    }

    public function test_class_session_teacher_can_be_suggested_without_an_explicit_participant_record(): void
    {
        $student = $this->student('Anggota Kelas');
        $this->dipta->branchAssignments()->delete();
        $inactiveMain = $this->teacher('Teacher Utama Nonaktif');
        $inactiveMain->update(['is_active' => false]);
        $group = $this->group($student, $inactiveMain);
        $session = $this->lesson($student, null, ['class_group_id' => $group->id]);
        $session->sessionStudents()->delete();
        $this->preparation($student)->assertViewHas('suggestedTeacher', $this->dipta->id);
        $this->submit($student, $this->dipta)->assertRedirect();
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_existing_report_owner_assignment_has_priority_over_other_teaching_assignments(): void
    {
        $student = $this->student('Dua Teacher');
        $owner = $this->teacher('Pemilik Laporan');
        $this->assignment($student);
        $this->assignment($student, $owner, ['is_report_owner' => true]);
        $this->preparation($student)->assertViewHas('suggestedTeacher', $owner->id)
            ->assertViewHas('assignedTeachers', fn ($teachers) => $teachers->count() === 2);
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_multiple_teachers_without_unique_owner_require_admin_choice(): void
    {
        $student = $this->student('Dua Teacher');
        $other = $this->teacher('Teacher Kedua');
        $this->assignment($student);
        $this->assignment($student, $other);
        $this->preparation($student)->assertViewHas('suggestedTeacher', null)->assertSee('Lebih dari satu Teacher mengajar siswa ini');
        $this->submit($student, $this->dipta, ['teacher_id' => ''])->assertSessionHasErrors('teacher_id');
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public static function invalidTeachers(): array
    {
        return [['expired-before-join'], ['starts-next-month'], ['enrollment-ended'], ['inactive-profile'], ['inactive-user'], ['wrong-role'], ['other-branch']];
    }

    #[DataProvider('invalidTeachers')]
    public function test_unrelated_or_inactive_branch_teachers_cannot_be_selected(string $case): void
    {
        $student = $this->student('Siswa Valid');
        $this->dipta->branchAssignments()->delete();
        $starts = $case === 'starts-next-month' ? '2026-11-01' : '2026-10-01';
        $ends = $case === 'expired-before-join' ? '2026-10-08' : null;
        $teacher = $this->teacher('Teacher Tidak Berlaku', $starts, $ends);
        if ($case === 'enrollment-ended') {
            $student->enrollments()->update(['ends_on' => '2026-10-09']);
            $teacher->branchAssignments()->update(['starts_on' => '2026-10-10']);
        } elseif ($case === 'inactive-profile') {
            $teacher->update(['is_active' => false]);
        } elseif ($case === 'inactive-user') {
            $teacher->user->update(['is_active' => false]);
        } elseif ($case === 'wrong-role') {
            $teacher->user->update(['role_code' => 'admin']);
        } elseif ($case === 'other-branch') {
            $branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Lain', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
            $teacher->branchAssignments()->update(['branch_id' => $branch->id]);
        }
        $this->preparation($student)->assertViewHas('teachers', fn ($teachers) => $teachers->isEmpty());
        $this->submit($student, $teacher)->assertSessionHasErrors('teacher_id');
        $this->assertDatabaseCount('monthly_reports', 0);
        $this->assertDatabaseCount('report_cycles', 0);
    }

    public static function invalidStudentLinks(): array
    {
        return [['expired-assignment'], ['future-assignment'], ['enrollment-no-overlap'], ['expired-membership'], ['wrong-program'], ['class-other-branch'], ['class-inactive'], ['cancelled-session'], ['superseded-session'], ['invalid-session-roster'], ['session-other-month'], ['session-other-branch'], ['session-actual-other-month'], ['session-before-join']];
    }

    #[DataProvider('invalidStudentLinks')]
    public function test_student_teaching_links_require_valid_dates_program_and_roster(string $case): void
    {
        $student = $this->student('Penugasan Tidak Berlaku');
        $this->dipta->branchAssignments()->delete();
        if (str_contains($case, 'assignment') || $case === 'enrollment-no-overlap') {
            $extra = match ($case) {
                'expired-assignment' => ['starts_on' => '2026-10-01', 'ends_on' => '2026-10-08'],
                'future-assignment' => ['starts_on' => '2026-11-01'],
                default => [],
            };
            $this->assignment($student, null, $extra);
            if ($case === 'enrollment-no-overlap') {
                $student->enrollments()->update(['ends_on' => '2026-10-09']);
            }
        } elseif (in_array($case, ['expired-membership', 'wrong-program', 'class-other-branch', 'class-inactive'], true)) {
            $membership = $case === 'expired-membership' ? ['starts_on' => '2026-10-01', 'ends_on' => '2026-10-08'] : [];
            $extra = [];
            if ($case === 'wrong-program') {
                $program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Program Lain', 'class_type' => 'regular', 'is_active' => true]);
                $extra['program_id'] = $program->id;
            } elseif ($case === 'class-other-branch') {
                $branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Lain', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
                $extra['branch_id'] = $branch->id;
            } elseif ($case === 'class-inactive') {
                $extra['is_active'] = false;
            }
            $this->group($student, null, $membership, $extra);
        } else {
            $extra = match ($case) {
                'cancelled-session' => ['status' => 'cancelled'],
                'session-other-month' => ['planned_start_at' => '2026-11-10 09:00:00', 'planned_end_at' => '2026-11-10 10:00:00'],
                'session-actual-other-month' => ['actual_start_at' => '2026-11-10 09:00:00', 'actual_end_at' => '2026-11-10 10:00:00'],
                'session-before-join' => ['planned_start_at' => '2026-10-08 09:00:00', 'planned_end_at' => '2026-10-08 10:00:00'],
                default => [],
            };
            if ($case === 'session-other-branch') {
                $branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Lain', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
                $extra['branch_id'] = $branch->id;
            }
            $session = $this->lesson($student, null, $extra);
            if ($case === 'invalid-session-roster') {
                $student->enrollments()->update(['starts_on' => '2026-10-11']);
            } elseif ($case === 'superseded-session') {
                $this->lesson($student, null, ['rescheduled_from_session_id' => $session->id, 'planned_start_at' => '2026-11-10 09:00:00', 'planned_end_at' => '2026-11-10 10:00:00']);
            }
        }
        $this->preparation($student)->assertViewHas('teachers', fn ($teachers) => $teachers->isEmpty());
        $this->submit($student, $this->dipta)->assertSessionHasErrors('teacher_id');
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_empty_teacher_list_keeps_form_visible_with_disabled_selection_and_help(): void
    {
        $student = $this->student('Belum Punya Teacher');
        $this->dipta->branchAssignments()->delete();
        $response = $this->preparation($student)->assertSee('Belum ada Teacher yang dapat dipilih')->assertSee('Buka Data Guru');
        $xpath = $this->xpath($response);
        $this->assertSame(1, $xpath->query('//select[@name="teacher_id" and @disabled]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="video_target"]')->length);
        $this->assertSame(1, $xpath->query('//form//button[@type="submit" and @disabled]')->length);
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_inactive_assigned_teacher_is_explained_without_being_available_or_suggested(): void
    {
        $student = $this->student('Teacher Nonaktif');
        $this->assignment($student);
        $this->dipta->update(['is_active' => false]);
        $other = $this->teacher('Teacher Aktif', '2026-10-01');
        $this->preparation($student)->assertSee('Dipta')->assertSee('Periksa status profil dan akun Teacher')
            ->assertViewHas('teachers', fn ($teachers) => $teachers->pluck('id')->all() === [$other->id])
            ->assertViewHas('suggestedTeacher', null);
    }

    public function test_teacher_choice_is_revalidated_after_assignment_changes_between_form_and_save(): void
    {
        $student = $this->student('Penugasan Berubah');
        $this->dipta->branchAssignments()->delete();
        $assignment = $this->assignment($student);
        $this->preparation($student)->assertViewHas('suggestedTeacher', $this->dipta->id);
        $assignment->delete();
        $this->submit($student, $this->dipta)->assertSessionHasErrors('teacher_id');
        $this->assertDatabaseCount('monthly_reports', 0);
        $this->assertDatabaseCount('monthly_progress_audits', 0);
    }

    public function test_admin_from_other_branch_cannot_use_student_teaching_relationship_to_prepare_report(): void
    {
        $student = $this->student('Siswa Cabang Lain');
        $this->assignment($student);
        $otherAdmin = $this->user('admin', 'Admin Tanpa Cabang');
        $this->actingAs($otherAdmin)->get(route('admin.student-progress.preparation', ['student' => $student, 'month' => '2026-10']))->assertForbidden();
        $this->post(route('admin.student-progress.prepare', $student), ['month' => '2026-10', 'teacher_id' => $this->dipta->id, 'video_target' => 2])->assertForbidden();
        $this->assertDatabaseCount('monthly_reports', 0);
    }
}
