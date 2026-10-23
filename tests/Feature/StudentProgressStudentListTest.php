<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\StudentProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentProgressStudentListTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Teacher $teacher;

    private Branch $branch;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-11 18:00:00', 'Asia/Makassar'));
        $this->actor = $this->user('teacher');
        $this->teacher = $this->newTeacher($this->actor);
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Penugasan', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'English Penugasan', 'class_type' => 'regular', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'password_hash' => 'fixture-hash', 'full_name' => ucfirst($role).' Penugasan', 'role_code' => $role, 'is_active' => true]);
    }

    private function newTeacher(User $user): Teacher
    {
        return Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
    }

    private function student(string $name = 'Siswa Ditugaskan', string $join = '2026-10-05'): Student
    {
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student')->id, 'full_name' => $name, 'began_on' => $join, 'status' => 'active', 'special_notes_internal' => 'RAHASIA PROFIL']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => $join]);

        return $student;
    }

    private function assignment(Student $student, array $extra = []): StudentTeacherAssignment
    {
        return StudentTeacherAssignment::create($extra + ['id' => (string) Str::uuid(), 'student_id' => $student->id, 'teacher_id' => $this->teacher->id, 'starts_on' => '2026-10-05', 'is_report_owner' => false]);
    }

    private function group(Student $student, array $membership = [], array $extra = []): ClassGroup
    {
        $group = ClassGroup::create($extra + ['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'default_teacher_id' => $this->teacher->id, 'code' => Str::random(12), 'name' => 'Kelas Ditugaskan', 'is_active' => true]);
        ClassGroupMembership::create($membership + ['id' => (string) Str::uuid(), 'class_group_id' => $group->id, 'student_id' => $student->id, 'starts_on' => '2026-10-05']);

        return $group;
    }

    private function lesson(?Student $student = null, array $extra = []): LessonSession
    {
        $session = LessonSession::create($extra + ['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'planned_start_at' => '2026-10-06 09:00:00', 'planned_end_at' => '2026-10-06 10:00:00', 'teaching_location' => 'branch', 'status' => 'scheduled']);
        if ($student) {
            SessionStudent::create(['session_id' => $session->id, 'student_id' => $student->id]);
        }

        return $session;
    }

    private function index(array $filters = []): TestResponse
    {
        return $this->actingAs($this->actor)->get(route('teacher.student-progress.index', $filters + ['month' => '2026-10']))->assertOk();
    }

    public function test_teaching_assignment_without_report_owner_flag_lists_student_before_report_is_prepared(): void
    {
        $student = $this->student();
        $assignment = $this->assignment($student);
        $this->student('Siswa Tidak Ditugaskan');
        $this->index()->assertSee($student->full_name)->assertDontSee('Siswa Tidak Ditugaskan')->assertDontSee('RAHASIA PROFIL')
            ->assertSee('Anda ditugaskan mengajar')->assertSee('Menunggu Admin menyiapkan periode')
            ->assertViewHas('students', fn ($students) => $students->total() === 1);
        $this->assertFalse($assignment->fresh()->is_report_owner);
        $this->assertDatabaseCount('monthly_reports', 0);
        $this->assertDatabaseCount('report_cycles', 0);
        $this->assertDatabaseCount('monthly_progress_audits', 0);
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_default_class_teacher_sees_valid_members_without_session_or_individual_assignment(): void
    {
        $student = $this->student();
        $this->group($student);
        $this->index()->assertSee($student->full_name)->assertViewHas('students', fn ($students) => $students->total() === 1);
        $this->assertDatabaseCount('lesson_sessions', 0);
        $this->assertDatabaseCount('session_students', 0);
        $this->assertDatabaseCount('student_teacher_assignments', 0);
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_teacher_sees_explicit_private_participants_without_marking_attendance(): void
    {
        $student = $this->student();
        $session = $this->lesson($student);
        $this->student('Pendaftar Bukan Peserta');
        $this->index()->assertSee($student->full_name)->assertDontSee('Pendaftar Bukan Peserta')
            ->assertViewHas('students', fn ($students) => $students->total() === 1);
        $record = $session->sessionStudents()->firstOrFail();
        $this->assertNull($record->attendance_status);
        $this->assertNull($record->recorded_at);
        $this->assertNull($record->individual_learning_note);
        $this->assertDatabaseCount('session_students', 1);
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_session_teacher_sees_class_members_even_when_default_teacher_is_someone_else(): void
    {
        $student = $this->student();
        $other = $this->newTeacher($this->user('teacher'));
        $group = $this->group($student, [], ['default_teacher_id' => $other->id]);
        $this->lesson(null, ['class_group_id' => $group->id]);
        $this->index()->assertSee($student->full_name);
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_monthly_branch_filter_includes_students_without_prepared_report(): void
    {
        $student = $this->student();
        $this->assignment($student);
        $otherBranch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Tidak Ditugaskan', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
        $response = $this->index(['branch_id' => $this->branch->id, 'q' => 'Ditugaskan'])->assertSee($student->full_name);
        $response->assertViewHas('branches', fn ($branches) => $branches->pluck('id')->all() === [$this->branch->id]);
        $this->index(['branch_id' => $otherBranch->id])->assertDontSee($student->full_name);
        $this->index(['q' => 'Nama Tidak Ada'])->assertDontSee($student->full_name);
    }

    public static function invalidAssignments(): array
    {
        return [
            'expired' => [['starts_on' => '2026-09-01', 'ends_on' => '2026-09-30'], '2026-09-01'],
            'future-month' => [['starts_on' => '2026-11-01'], '2026-10-05'],
            'ended-before-join' => [['starts_on' => '2026-10-01', 'ends_on' => '2026-10-04'], '2026-10-05'],
            'student-not-started' => [['starts_on' => '2026-10-01'], '2026-11-05'],
        ];
    }

    #[DataProvider('invalidAssignments')]
    public function test_assignment_dates_and_student_start_limit_selected_month(array $assignment, string $join): void
    {
        $student = $this->student('Siswa Di Luar Periode', $join);
        $this->assignment($student, $assignment);
        $this->index()->assertDontSee($student->full_name)->assertViewHas('students', fn ($students) => $students->total() === 0);
    }

    public function test_assignment_starting_at_month_end_is_included_once_despite_duplicate_paths(): void
    {
        $student = $this->student();
        $this->assignment($student, ['starts_on' => '2026-10-31', 'ends_on' => '2026-10-31']);
        $this->group($student);
        $this->lesson($student);
        $this->index()->assertViewHas('students', fn ($students) => $students->total() === 1);
    }

    public static function invalidMemberships(): array
    {
        return [['expired'], ['not-yet-started'], ['no-enrollment'], ['wrong-program'], ['wrong-branch'], ['nonoverlapping-periods'], ['inactive-class']];
    }

    #[DataProvider('invalidMemberships')]
    public function test_class_membership_requires_matching_enrollment_and_overlapping_dates(string $case): void
    {
        $student = $this->student('Anggota Tidak Berlaku');
        $membership = match ($case) {
            'expired' => ['starts_on' => '2026-09-01', 'ends_on' => '2026-09-30'],
            'not-yet-started' => ['starts_on' => '2026-11-01'],
            'nonoverlapping-periods' => ['starts_on' => '2026-10-01', 'ends_on' => '2026-10-07'],
            default => [],
        };
        $extra = $case === 'inactive-class' ? ['is_active' => false] : [];
        $this->group($student, $membership, $extra);
        if ($case === 'no-enrollment') {
            $student->enrollments()->delete();
        } elseif ($case === 'wrong-program') {
            $program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Program Lain', 'class_type' => 'regular', 'is_active' => true]);
            $student->enrollments()->update(['program_id' => $program->id]);
        } elseif ($case === 'wrong-branch') {
            $branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Lain', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
            $student->enrollments()->update(['branch_id' => $branch->id]);
        } elseif ($case === 'nonoverlapping-periods') {
            $student->enrollments()->update(['starts_on' => '2026-10-08']);
        }
        $this->index()->assertDontSee($student->full_name)->assertViewHas('students', fn ($students) => $students->total() === 0);
    }

    public static function invalidSessions(): array
    {
        return [['cancelled'], ['superseded'], ['before-join'], ['outside-month'], ['actual-date-outside-month'], ['invalid-enrollment'], ['other-teacher']];
    }

    #[DataProvider('invalidSessions')]
    public function test_private_participant_requires_valid_own_session_in_selected_month(string $case): void
    {
        $student = $this->student('Peserta Sesi Tidak Berlaku');
        $extra = match ($case) {
            'cancelled' => ['status' => 'cancelled'],
            'before-join' => ['planned_start_at' => '2026-10-04 09:00:00', 'planned_end_at' => '2026-10-04 10:00:00'],
            'outside-month' => ['planned_start_at' => '2026-11-06 09:00:00', 'planned_end_at' => '2026-11-06 10:00:00'],
            'actual-date-outside-month' => ['actual_start_at' => '2026-11-06 09:00:00', 'actual_end_at' => '2026-11-06 10:00:00'],
            'other-teacher' => ['teacher_id' => $this->newTeacher($this->user('teacher'))->id],
            default => [],
        };
        $session = $this->lesson($student, $extra);
        if ($case === 'superseded') {
            $this->lesson(null, ['rescheduled_from_session_id' => $session->id, 'planned_start_at' => '2026-11-06 09:00:00', 'planned_end_at' => '2026-11-06 10:00:00']);
        } elseif ($case === 'invalid-enrollment') {
            $student->enrollments()->update(['starts_on' => '2026-10-07']);
        }
        $this->index()->assertDontSee($student->full_name)->assertViewHas('students', fn ($students) => $students->total() === 0);
    }

    public function test_future_session_participants_are_visible_for_planning_with_empty_attendance(): void
    {
        $student = $this->student();
        $session = $this->lesson($student, ['planned_start_at' => '2026-10-20 09:00:00', 'planned_end_at' => '2026-10-20 10:00:00']);
        $this->index()->assertSee($student->full_name);
        $this->assertNull($session->sessionStudents()->firstOrFail()->attendance_status);
    }

    public function test_assignment_to_another_teacher_and_inactive_profile_do_not_grant_student_visibility(): void
    {
        $student = $this->student();
        $other = $this->newTeacher($this->user('teacher'));
        $this->assignment($student, ['teacher_id' => $other->id]);
        $this->group($student, [], ['default_teacher_id' => $other->id]);
        $this->index()->assertDontSee($student->full_name);
        $this->assignment($student);
        $this->teacher->update(['is_active' => false]);
        $this->index()->assertDontSee($student->full_name)->assertViewHas('branches', fn ($branches) => $branches->isEmpty());
    }

    public function test_assigned_student_with_other_report_owner_is_listed_without_exposing_or_changing_report(): void
    {
        $student = $this->student();
        $this->assignment($student);
        $admin = $this->user('admin');
        $owner = $this->newTeacher($this->user('teacher'));
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-10-01']);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $owner->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-10-01']);
        $report = app(StudentProgressService::class)->prepare($admin, $student, CarbonImmutable::parse('2026-10-01'), ['teacher_id' => $owner->id, 'video_target' => 2]);
        $report->update(['parent_message' => 'PESAN RAHASIA LAPORAN', 'internal_teacher_note' => 'CATATAN RAHASIA LAPORAN']);
        $this->index()->assertSee($student->full_name)->assertSee('Dikelola Teacher lain')->assertSee('Hubungi Admin untuk penetapan pemilik laporan')
            ->assertDontSee('PESAN RAHASIA LAPORAN')->assertDontSee('CATATAN RAHASIA LAPORAN')
            ->assertDontSee(route('teacher.student-progress.edit', $report), false);
        $this->get(route('teacher.student-progress.edit', $report))->assertForbidden();
        $this->put(route('teacher.student-progress.save', $report), [])->assertForbidden();
        $this->assertSame($owner->id, $report->fresh()->teacher_id);
        $this->assertDatabaseCount('monthly_progress_audits', 1);
    }
}
