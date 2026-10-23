<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\PortalPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentPortalAttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;
    private User $superadmin;
    private Student $student;
    private Branch $branch;
    private Program $program;
    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00:00', 'Asia/Makassar'));
        $this->parent = $this->account('student');
        $this->superadmin = $this->account('superadmin');
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->account('teacher')->id,
            'whatsapp_number' => '081234567890', 'is_active' => true]);
        $this->branch = $this->branch('Cabang Utama Portal');
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(10),
            'name' => 'Program Portal', 'class_type' => 'private', 'is_active' => true]);
        $this->student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->parent->id,
            'full_name' => 'Siswa Portal', 'began_on' => '2026-07-09', 'status' => 'active']);
        $this->enroll($this->student, $this->branch);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $role): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'full_name' => 'Akun '.$role,
            'password_hash' => 'fixture-only', 'role_code' => $role, 'is_active' => true]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => $name,
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function enroll(Student $student, Branch $branch, string $startsOn = '2026-07-09'): StudentEnrollment
    {
        return StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id,
            'branch_id' => $branch->id, 'program_id' => $this->program->id, 'starts_on' => $startsOn]);
    }

    private function lesson(string $date, ?string $status = 'present', array $extra = [], ?Student $student = null, bool $attach = true): LessonSession
    {
        $session = LessonSession::create(array_replace(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id,
            'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'class_group_id' => null,
            'planned_start_at' => $date.' 16:00:00', 'planned_end_at' => $date.' 17:00:00', 'status' => 'scheduled',
            'topic' => 'TOPIK INTERNAL', 'material' => 'MATERI INTERNAL', 'activity' => 'AKTIVITAS INTERNAL'], $extra));
        if ($attach) {
            SessionStudent::create(['session_id' => $session->id, 'student_id' => ($student ?? $this->student)->id,
                'attendance_status' => $status, 'individual_learning_note' => 'RAHASIA CATATAN PER SESI']);
        }

        return $session;
    }

    private function attendance(array $months = ['2026-08', '2026-09', '2026-10'])
    {
        return $this->actingAs($this->parent)->get(route('student.attendance', ['months' => $months]));
    }

    private function setVisibility(Branch $branch, bool $visible): void
    {
        $settings = app(PortalPaymentService::class);
        $settings->saveVisibility($this->superadmin, $branch, ['visible' => $visible, 'expected_snapshot' => $settings->settingSignature($branch)]);
    }

    public function test_default_months_and_student_navigation_have_three_immediate_choices_without_a_filter_button(): void
    {
        $response = $this->actingAs($this->parent)->get(route('student.attendance'))->assertOk()
            ->assertViewHas('months', ['2026-08', '2026-09', '2026-10'])
            ->assertSee('href="'.route('student.attendance').'"', false)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(3, $xpath->query('//form[@data-portal-attendance-months]//input[@type="month" and @name="months[]"]')->length);
        $this->assertSame(0, $xpath->query('//form[@data-portal-attendance-months]//button')->length);
        $this->assertSame(3, $xpath->query('//article[@data-attendance-period]')->length);
        $this->get(route('student.dashboard'))->assertSee('Absensi Tiga Bulan');
    }

    public function test_default_months_cross_the_year_boundary_without_month_overflow(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-31 23:00:00', 'Asia/Makassar'));
        $this->actingAs($this->parent)->get(route('student.attendance'))->assertOk()
            ->assertViewHas('months', ['2026-11', '2026-12', '2027-01']);
    }

    public function test_three_independent_months_keep_the_selected_order_and_expose_only_the_logged_in_students_attendance(): void
    {
        $july = $this->lesson('2026-07-10');
        $this->lesson('2026-08-11');
        $september = $this->lesson('2026-09-12', 'late');
        $other = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->account('student')->id,
            'full_name' => 'Siswa Rahasia', 'began_on' => '2026-07-09', 'status' => 'active']);
        $this->enroll($other, $this->branch);
        $this->lesson('2026-07-13', 'absent', [], $other);
        $response = $this->actingAs($this->parent)->get(route('student.attendance', [
            'months' => ['2026-09', '2026-07', '2025-12'], 'student_id' => $other->id,
        ]))->assertOk()->assertViewHas('months', ['2026-09', '2026-07', '2025-12'])
            ->assertDontSee('13 Juli 2026')->assertDontSee('11 Agustus 2026')->assertDontSee('Siswa Rahasia')
            ->assertDontSee('RAHASIA CATATAN PER SESI')->assertDontSee('TOPIK INTERNAL');
        $rows = $response->viewData('attendanceMonths');
        $this->assertSame([$september->id], $rows[0]['sessions']->pluck('id')->all());
        $this->assertSame([$july->id], $rows[1]['sessions']->pluck('id')->all());
        $this->assertCount(0, $rows[2]['sessions']);
        $this->assertDatabaseCount('monthly_charges', 0);
    }

    public function test_visibility_applies_to_each_sessions_branch_and_hidden_records_never_contribute_to_totals(): void
    {
        $first = $this->lesson('2026-08-11');
        $secondBranch = $this->branch('Cabang Kedua Portal');
        StudentEnrollment::where('student_id', $this->student->id)->update(['ends_on' => '2026-08-11']);
        $this->enroll($this->student, $secondBranch, '2026-08-12');
        $second = $this->lesson('2026-08-12', 'late', ['branch_id' => $secondBranch->id]);
        $settings = app(PortalPaymentService::class);
        $set = function (Branch $branch, int $visible) use ($settings) {
            $this->actingAs($this->superadmin)->put(route('superadmin.portal-settings.save', $branch),
                ['visible' => $visible, 'expected_snapshot' => $settings->settingSignature($branch)])->assertRedirect();
        };
        $set($this->branch, 0);
        $response = $this->attendance()->assertOk()->assertDontSee('Cabang Utama Portal')->assertDontSee('11 Agustus 2026');
        $august = $response->viewData('attendanceMonths')[0];
        $this->assertSame([$second->id], $august['sessions']->pluck('id')->all());
        $this->assertSame(1, $august['summary']['meeting_count']);
        $this->assertSame(1, $august['summary']['late']);
        $set($secondBranch, 0);
        $response = $this->attendance()->assertOk()->assertDontSee('Cabang Kedua Portal')->assertDontSee('12 Agustus 2026');
        $this->assertCount(0, $response->viewData('attendanceMonths')[0]['sessions']);
        $this->assertSame(0, array_sum($response->viewData('attendanceMonths')[0]['summary']));
        $set($this->branch, 1);
        $response = $this->attendance()->assertOk()->assertSee('11 Agustus 2026')->assertDontSee('12 Agustus 2026');
        $this->assertSame([$first->id], $response->viewData('attendanceMonths')[0]['sessions']->pluck('id')->all());
    }

    public function test_recaps_use_existing_attendance_rules_for_unrecorded_cancelled_and_rescheduled_lessons(): void
    {
        foreach (['present', 'late', 'absent', 'sick', 'permission', null, 'rescheduled'] as $index => $status) {
            $this->lesson('2026-08-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), $status);
        }
        $this->lesson('2026-08-08', 'present', ['status' => 'cancelled']);
        $original = $this->lesson('2026-08-09');
        $this->lesson('2026-08-10', 'present', ['rescheduled_from_session_id' => $original->id]);
        $this->lesson('2026-10-22');
        $response = $this->attendance()->assertOk()->assertSee('Belum dicatat')->assertSee('Sesi dibatalkan')
            ->assertSee('Dijadwalkan Ulang')->assertDontSee('22 Oktober 2026');
        $august = $response->viewData('attendanceMonths')[0];
        $this->assertCount(10, $august['sessions']);
        $this->assertSame(['present' => 2, 'late' => 1, 'absent' => 1, 'sick' => 1, 'permission' => 1,
            'rescheduled' => 2, 'unrecorded' => 1, 'unknown' => 0, 'meeting_count' => 7, 'attended_count' => 3], $august['summary']);
        $this->assertCount(0, $response->viewData('attendanceMonths')[2]['sessions']);
    }

    public function test_membership_enrollment_start_date_and_actual_lesson_month_remain_authoritative(): void
    {
        $this->lesson('2026-07-08');
        $actual = $this->lesson('2026-07-31', 'present', ['actual_start_at' => '2026-08-01 16:00:00']);
        $group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id,
            'program_id' => $this->program->id, 'default_teacher_id' => $this->teacher->id,
            'code' => Str::random(10), 'name' => 'Kelas Portal', 'is_active' => true]);
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id,
            'class_group_id' => $group->id, 'starts_on' => '2026-08-10', 'ends_on' => '2026-08-20']);
        $this->lesson('2026-08-09', null, ['class_group_id' => $group->id], attach: false);
        $member = $this->lesson('2026-08-15', null, ['class_group_id' => $group->id], attach: false);
        $this->lesson('2026-08-21', null, ['class_group_id' => $group->id], attach: false);
        $this->lesson('2026-08-16', null, attach: false);
        StudentEnrollment::where('student_id', $this->student->id)->update(['ends_on' => '2026-08-18']);
        $this->lesson('2026-08-19');
        $response = $this->attendance(['2026-07', '2026-08', '2026-10'])->assertOk();
        $rows = $response->viewData('attendanceMonths');
        $this->assertCount(0, $rows[0]['sessions']);
        $this->assertSame([$actual->id, $member->id], $rows[1]['sessions']->pluck('id')->all());
        $this->assertSame(1, $rows[1]['summary']['unrecorded']);
    }

    #[DataProvider('invalidMonths')]
    public function test_invalid_or_duplicate_months_are_rejected_before_reading_attendance(array $query): void
    {
        $this->actingAs($this->parent)->getJson(route('student.attendance', $query))->assertUnprocessable();
    }

    public static function invalidMonths(): array
    {
        return [
            [['months' => '2026-07']],
            [['months' => ['2026-07', '2026-08']]],
            [['months' => ['2026-07', '2026-08', '2026-09', '2026-10']]],
            [['months' => ['2026-07', '2026-07', '2026-09']]],
            [['months' => ['2026-00', '2026-08', '2026-09']]],
            [['months' => ['2026-13', '2026-08', '2026-09']]],
            [['months' => ['2026-07-01', '2026-08', '2026-09']]],
            [['months' => ['0000-01', '2026-08', '2026-09']]],
            [['months' => ['10000-01', '2026-08', '2026-09']]],
            [['months' => ['first' => '2026-07', 'second' => '2026-08', 'third' => '2026-09']]],
            [['months' => [['2026-07'], '2026-08', '2026-09']]],
        ];
    }

    #[DataProvider('blockedRoles')]
    public function test_other_roles_and_inactive_accounts_cannot_open_the_student_attendance_page(string $role): void
    {
        $actor = $this->account($role === 'inactive' ? 'student' : $role);
        if ($role === 'inactive') $actor->update(['is_active' => false]);
        $this->actingAs($actor)->get(route('student.attendance'))->assertForbidden();
    }

    public static function blockedRoles(): array
    {
        return [['admin'], ['teacher'], ['superadmin'], ['inactive']];
    }

    public function test_guest_unlinked_or_reassigned_accounts_cannot_select_a_student(): void
    {
        $this->get(route('student.attendance'))->assertRedirect(route('login'));
        $unlinked = $this->account('student');
        $this->actingAs($unlinked)->get(route('student.attendance', ['student_id' => $this->student->id]))->assertForbidden();
        $this->student->update(['portal_user_id' => $unlinked->id]);
        $this->attendance()->assertForbidden();
    }

    public function test_attendance_gets_do_not_write_or_fill_missing_attendance_records(): void
    {
        $this->lesson('2026-08-01', null);
        $before = DB::table('session_students')->get()->toJson();
        $events = DB::table('student_portal_events')->count();
        $this->attendance()->assertOk();
        $this->attendance(['2026-07', '2026-09', '2026-12'])->assertOk();
        $this->assertSame($before, DB::table('session_students')->get()->toJson());
        $this->assertSame($events, DB::table('student_portal_events')->count());
    }

    public function test_dashboard_keeps_four_cards_and_replaces_active_programs_with_zero_of_eight_recorded_meetings(): void
    {
        $response = $this->actingAs($this->parent)->get(route('student.dashboard'))->assertOk()
            ->assertSee('Pertemuan bulan ini')->assertSee('0/8')->assertSee('Oktober 2026')->assertDontSee('Program aktif');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(4, $xpath->query('//article[contains(concat(" ", @class, " "), " dpe-dashboard-metric ")]')->length);
        $this->assertSame(0, $response->viewData('meetingProgress')['recorded_count']);
        $this->assertSame(8, $response->viewData('meetingProgress')['target']);
        $this->setVisibility($this->branch, false);
        $hidden = $this->get(route('student.dashboard'))->assertOk()->assertDontSee('0/8')->assertSee('Absensi disembunyikan oleh cabang');
        $this->assertNull($hidden->viewData('meetingProgress')['recorded_count']);
    }

    public function test_dashboard_counts_recorded_statuses_and_reflects_corrections_without_counting_blank_future_cancelled_or_replaced_sessions(): void
    {
        $this->actingAs($this->parent);
        $this->lesson('2026-10-01');
        $this->get(route('student.dashboard'))->assertOk()->assertSee('1/8');
        $this->lesson('2026-10-02', 'late');
        $this->get(route('student.dashboard'))->assertOk()->assertSee('2/8');
        $this->lesson('2026-10-03', 'absent');
        $this->lesson('2026-10-04', 'sick');
        $this->lesson('2026-10-05', 'permission');
        $blank = $this->lesson('2026-10-06', null);
        $this->lesson('2026-10-07', 'present', ['status' => 'cancelled']);
        $original = $this->lesson('2026-10-08');
        $this->lesson('2026-10-09', 'present', ['rescheduled_from_session_id' => $original->id]);
        $this->lesson('2026-10-10', 'legacy');
        $this->lesson('2026-10-11', '');
        $this->lesson('2026-10-22');
        $this->lesson('2026-09-30');
        $response = $this->get(route('student.dashboard'))->assertOk()->assertSee('6/8');
        $this->assertSame(6, $response->viewData('meetingProgress')['recorded_count']);
        SessionStudent::where('session_id', $blank->id)->update(['attendance_status' => 'present']);
        $this->get(route('student.dashboard'))->assertOk()->assertSee('7/8');
        SessionStudent::where('session_id', $blank->id)->update(['attendance_status' => null]);
        $this->get(route('student.dashboard'))->assertOk()->assertSee('6/8');
    }

    public function test_dashboard_does_not_cap_extra_meetings_and_automatically_starts_zero_in_the_next_month(): void
    {
        for ($day = 1; $day <= 10; $day++) $this->lesson('2026-10-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT));
        $this->actingAs($this->parent)->get(route('student.dashboard'))->assertOk()->assertSee('10/8');
        Carbon::setTestNow(Carbon::parse('2026-11-01 00:00:00', 'Asia/Makassar'));
        $this->get(route('student.dashboard'))->assertOk()->assertSee('0/8')->assertSee('November 2026');
    }

    public function test_dashboard_counts_only_visible_branch_records_and_never_returns_hidden_totals(): void
    {
        $this->lesson('2026-10-08');
        $this->lesson('2026-10-09');
        StudentEnrollment::where('student_id', $this->student->id)->update(['ends_on' => '2026-10-10']);
        $second = $this->branch('Cabang Kedua Portal');
        $this->enroll($this->student, $second, '2026-10-11');
        $this->lesson('2026-10-12', 'late', ['branch_id' => $second->id]);
        $this->actingAs($this->parent)->get(route('student.dashboard'))->assertOk()->assertSee('3/8');
        $this->setVisibility($this->branch, false);
        $response = $this->get(route('student.dashboard'))->assertOk()->assertSee('1/8')->assertSee('Catatan cabang yang ditampilkan');
        $this->assertSame(1, $response->viewData('meetingProgress')['recorded_count']);
        $this->setVisibility($second, false);
        $response = $this->get(route('student.dashboard'))->assertOk()->assertSee('Absensi disembunyikan oleh cabang')
            ->assertDontSee('3/8')->assertDontSee('1/8');
        $this->assertSame('—', $response->viewData('meetingProgress')['value']);
        $this->assertNull($response->viewData('meetingProgress')['recorded_count']);
        $this->setVisibility($this->branch, true);
        $this->get(route('student.dashboard'))->assertOk()->assertSee('2/8');
    }

    public function test_dashboard_meeting_progress_cannot_be_changed_to_another_student_through_query_parameters(): void
    {
        $this->lesson('2026-10-01');
        $other = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->account('student')->id,
            'full_name' => 'Siswa Rahasia', 'began_on' => '2026-07-09', 'status' => 'active']);
        $this->enroll($other, $this->branch);
        $this->lesson('2026-10-02', 'present', [], $other);
        $this->lesson('2026-10-03', 'present', [], $other);
        $response = $this->actingAs($this->parent)->get(route('student.dashboard', ['student_id' => $other->id, 'month' => '2026-09']))
            ->assertOk()->assertSee('1/8')->assertDontSee('Siswa Rahasia');
        $this->assertSame(1, $response->viewData('meetingProgress')['recorded_count']);
    }
}
