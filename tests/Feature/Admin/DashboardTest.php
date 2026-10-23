<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\LessonSession;
use App\Models\MonthlyReport;
use App\Models\Program;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_is_scoped_to_active_assigned_branches(): void
    {
        $admin = $this->createUser('admin', 'Admin Cabang');
        $visibleBranch = $this->createBranch('Cabang Terlihat');
        $hiddenBranch = $this->createBranch('Cabang Tersembunyi');

        BranchAdminAssignment::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $visibleBranch->id,
            'admin_user_id' => $admin->id,
            'starts_on' => Carbon::today()->subDay(),
            'ends_on' => null,
        ]);

        $program = Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'DASH-'.Str::upper(Str::random(6)),
            'name' => 'Program Dashboard',
            'class_type' => 'regular',
            'monthly_video_target_override' => null,
            'is_active' => true,
        ]);

        $visibleStudent = $this->createStudent('Siswa Terlihat');
        $hiddenStudent = $this->createStudent('Siswa Tersembunyi');
        $this->enrollStudent($visibleStudent, $visibleBranch, $program);
        $this->enrollStudent($hiddenStudent, $hiddenBranch, $program);

        $visibleTeacher = $this->createTeacher('Guru Terlihat');
        $hiddenTeacher = $this->createTeacher('Guru Tersembunyi');
        $this->assignTeacher($visibleTeacher, $visibleBranch);
        $this->assignTeacher($hiddenTeacher, $hiddenBranch);

        $today = Carbon::today();
        LessonSession::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $visibleBranch->id,
            'teacher_id' => $visibleTeacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => $today->copy()->setTime(9, 0),
            'planned_end_at' => $today->copy()->setTime(10, 0),
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'Sesi Terlihat',
            'material' => null,
            'activity' => null,
        ]);

        LessonSession::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $hiddenBranch->id,
            'teacher_id' => $hiddenTeacher->id,
            'program_id' => $program->id,
            'class_group_id' => null,
            'planned_start_at' => $today->copy()->setTime(11, 0),
            'planned_end_at' => $today->copy()->setTime(12, 0),
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'scheduled',
            'rescheduled_from_session_id' => null,
            'topic' => 'Sesi Tersembunyi',
            'material' => null,
            'activity' => null,
        ]);

        $visibleCycle = $this->createCycle($visibleStudent);
        $hiddenCycle = $this->createCycle($hiddenStudent);
        $this->createReport($visibleStudent, $visibleTeacher, $visibleBranch, $visibleCycle);
        $this->createReport($hiddenStudent, $hiddenTeacher, $hiddenBranch, $hiddenCycle);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Cabang Terlihat')
            ->assertSee('Sesi Terlihat')
            ->assertSee('Review rapor Siswa Terlihat')
            ->assertDontSee('Cabang Tersembunyi')
            ->assertDontSee('Sesi Tersembunyi')
            ->assertDontSee('Review rapor Siswa Tersembunyi');
    }

    private function createUser(string $role, string $name): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password_hash' => Hash::make('Password123!'),
            'full_name' => $name,
            'role_code' => $role,
            'is_active' => true,
        ]);
    }

    private function createBranch(string $name): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-'.Str::upper(Str::random(6)),
            'name' => $name,
            'address' => null,
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 1,
            'is_active' => true,
        ]);
    }

    private function createStudent(string $name): Student
    {
        $user = $this->createUser('student', $name.' Portal');

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $user->id,
            'full_name' => $name,
            'photo_file_id' => null,
            'school_name' => null,
            'grade_name' => null,
            'began_on' => Carbon::today()->subMonth(),
            'status' => 'active',
            'special_notes_internal' => null,
        ]);
    }

    private function enrollStudent(Student $student, Branch $branch, Program $program): void
    {
        StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'starts_on' => Carbon::today()->subMonth(),
            'ends_on' => null,
        ]);
    }

    private function createTeacher(string $name): Teacher
    {
        $user = $this->createUser('teacher', $name.' Portal');

        return Teacher::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'photo_file_id' => null,
            'is_active' => true,
        ]);
    }

    private function assignTeacher(Teacher $teacher, Branch $branch): void
    {
        TeacherBranchAssignment::query()->create([
            'id' => (string) Str::uuid(),
            'teacher_id' => $teacher->id,
            'branch_id' => $branch->id,
            'starts_on' => Carbon::today()->subMonth(),
            'ends_on' => null,
        ]);
    }

    private function createCycle(Student $student): ReportCycle
    {
        $month = Carbon::today()->startOfMonth();

        return ReportCycle::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'cycle_number' => 1,
            'start_month' => $month,
            'end_month' => $month->copy()->addMonths(2)->endOfMonth(),
            'share_due_on' => $month->copy()->addMonth()->endOfMonth(),
        ]);
    }

    private function createReport(
        Student $student,
        Teacher $teacher,
        Branch $branch,
        ReportCycle $cycle
    ): MonthlyReport {
        $month = Carbon::today()->startOfMonth();

        return MonthlyReport::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'cycle_id' => $cycle->id,
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'teacher_id' => $teacher->id,
            'report_month' => $month,
            'due_on' => $month->copy()->endOfMonth(),
            'video_target' => 1,
            'status' => 'submitted',
            'development_summary' => null,
            'parent_challenges_summary' => null,
            'parent_message' => null,
            'internal_teacher_note' => null,
            'submitted_at' => now(),
            'approved_at' => null,
            'approved_by_user_id' => null,
            'approved_snapshot' => null,
        ]);
    }
}
