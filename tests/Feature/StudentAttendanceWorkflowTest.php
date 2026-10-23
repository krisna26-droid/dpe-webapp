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
use App\Models\StudentAttendanceAudit;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LessonSessionService;
use App\Services\SessionStudentService;
use App\Services\StudentAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentAttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $teacherUser;

    private Teacher $teacher;

    private Branch $branch;

    private Program $program;

    private ClassGroup $group;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-10 18:00:00', 'Asia/Makassar'));
        $this->teacherUser = $this->user('teacher');
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->teacherUser->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Utama', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'English Reguler', 'class_type' => 'regular', 'is_active' => true]);
        $this->group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'default_teacher_id' => $this->teacher->id, 'code' => Str::random(12), 'name' => 'Kelas Utama', 'is_active' => true]);
        $this->student = $this->enrolledStudent('Siswa Utama');
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $this->group->id, 'student_id' => $this->student->id, 'starts_on' => '2026-10-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(14), 'password_hash' => 'fixture-hash', 'full_name' => ucfirst($role).' Fixture', 'role_code' => $role, 'is_active' => true]);
    }

    private function enrolledStudent(string $name = 'Siswa Tambahan', array $attributes = []): Student
    {
        $student = Student::create($attributes + ['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student')->id, 'full_name' => $name, 'began_on' => '2026-10-01', 'status' => 'active', 'special_notes_internal' => 'SECRET_INTERNAL_ONLY']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-10-01']);

        return $student;
    }

    private function makeLesson(array $attributes = []): LessonSession
    {
        return LessonSession::create($attributes + ['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'class_group_id' => $this->group->id, 'planned_start_at' => '2026-10-01 09:00:00', 'planned_end_at' => '2026-10-01 10:30:00', 'teaching_location' => 'branch', 'status' => 'scheduled']);
    }

    private function saveUrl(LessonSession $session, ?Student $student = null, string $role = 'teacher'): string
    {
        return route($role.'.student-attendance.save', [$session, $student ?? $this->student]);
    }

    private function payload(?string $status = 'present', int $version = 0, array $extra = []): array
    {
        return $extra + ['attendance_status' => $status, 'expected_version' => $version];
    }

    private function ledger(LessonSession $session, ?Student $student = null): SessionStudent
    {
        return SessionStudent::where('session_id', $session->id)->where('student_id', ($student ?? $this->student)->id)->firstOrFail();
    }

    private function indexUrl(array $extra = [], string $role = 'teacher'): string
    {
        return route($role.'.student-attendance.index', $extra + ['period' => '2026-10', 'month' => '2026-10', 'class_group_id' => $this->group->id]);
    }

    public function test_teacher_records_derived_class_roster_once_with_server_time_and_audit(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload('present', 0, ['recorded_at' => '2030-01-01']))->assertRedirect();
        $record = $this->ledger($session);
        $this->assertSame('present', $record->attendance_status);
        $this->assertSame('2026-10-10 18:00:00', $record->recorded_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->teacherUser->id, $record->attendance_recorded_by_user_id);
        $this->assertSame(1, $record->attendance_version);
        $event = StudentAttendanceAudit::firstOrFail();
        $this->assertSame('2026-10-01', $event->after_snapshot['meeting_date']);
        $this->assertNull($event->before_snapshot);
        $this->assertSame($this->teacherUser->id, $event->actor_user_id);
        $this->postJson($this->saveUrl($session), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('expected_version');
        $this->assertDatabaseCount('session_students', 1);
        $this->assertDatabaseCount('student_attendance_audits', 1);
    }

    public function test_same_value_with_current_version_is_idempotent(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload())->assertRedirect();
        $this->post($this->saveUrl($session), $this->payload('present', 1))->assertRedirect();
        $this->assertDatabaseCount('student_attendance_audits', 1);
        $this->assertSame(1, $this->ledger($session)->attendance_version);
    }

    public function test_blank_cells_are_pending_and_never_become_absent_on_read(): void
    {
        $this->makeLesson();
        $this->actingAs($this->teacherUser)->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s['unrecorded'] === 1 && $s['absent'] === 0 && $s['meeting_count'] === 1)->assertDontSee('SECRET_INTERNAL_ONLY');
        $this->assertDatabaseCount('session_students', 0);
        $this->assertDatabaseCount('student_attendance_audits', 0);
    }

    public static function attendanceStatuses(): array
    {
        return [['present', 1, 1], ['late', 1, 1], ['absent', 0, 1], ['sick', 0, 1], ['permission', 0, 1], ['rescheduled', 0, 0]];
    }

    #[DataProvider('attendanceStatuses')]
    public function test_each_status_has_a_distinct_summary_and_late_is_attended(string $status, int $attended, int $meetings): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload($status))->assertRedirect();
        $this->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s[$status] === 1 && $s['attended_count'] === $attended && $s['meeting_count'] === $meetings && $s['unrecorded'] === 0);
    }

    public function test_summary_keeps_every_student_in_the_same_session(): void
    {
        $second = $this->enrolledStudent('Siswa Kedua');
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $this->group->id, 'student_id' => $second->id, 'starts_on' => '2026-10-01']);
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload())->assertRedirect();
        $this->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s['present'] === 1 && $s['unrecorded'] === 1 && $s['meeting_count'] === 2);
    }

    public function test_before_join_date_is_disabled_and_rejected_even_for_existing_participant(): void
    {
        $this->student->update(['began_on' => '2026-10-05']);
        $session = $this->makeLesson();
        SessionStudent::create(['session_id' => $session->id, 'student_id' => $this->student->id]);
        $this->actingAs($this->teacherUser)->postJson($this->saveUrl($session), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('attendance_status');
        $this->get($this->indexUrl())->assertOk()->assertSee('Sebelum mulai les')->assertViewHas('summary', fn ($s) => $s['meeting_count'] === 0);
        $this->assertNull($this->ledger($session)->attendance_status);
    }

    public function test_future_meeting_and_future_time_today_cannot_be_marked(): void
    {
        foreach (['2026-10-11 09:00:00', '2026-10-10 19:00:00'] as $time) {
            $session = $this->makeLesson(['planned_start_at' => $time, 'planned_end_at' => Carbon::parse($time)->addHour()->toDateTimeString()]);
            $this->actingAs($this->teacherUser)->postJson($this->saveUrl($session), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('attendance_status');
        }
        $this->get($this->indexUrl())->assertOk()->assertSee('Belum berlangsung')->assertViewHas('summary', fn ($s) => $s['meeting_count'] === 0 && $s['unrecorded'] === 0);
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_cancelled_session_cannot_count_as_a_meeting(): void
    {
        $session = $this->makeLesson(['status' => 'cancelled']);
        $this->actingAs($this->teacherUser)->postJson($this->saveUrl($session), $this->payload())->assertUnprocessable();
        $this->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s['meeting_count'] === 0);
    }

    public function test_actual_meeting_date_controls_calendar_month(): void
    {
        $session = $this->makeLesson(['planned_start_at' => '2026-09-30 09:00:00', 'planned_end_at' => '2026-09-30 10:00:00', 'actual_start_at' => '2026-10-02 09:00:00']);
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload())->assertRedirect();
        $this->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s['present'] === 1);
        $this->assertSame('2026-10-02', StudentAttendanceAudit::firstOrFail()->after_snapshot['meeting_date']);
    }

    public function test_branch_timezone_is_used_even_when_app_timezone_differs(): void
    {
        config(['app.timezone' => 'UTC']);
        $session = $this->makeLesson(['planned_start_at' => '2026-10-10 18:00:00', 'planned_end_at' => '2026-10-10 19:00:00']);
        Carbon::setTestNow(Carbon::parse('2026-10-10 09:55:00', 'UTC'));
        $this->actingAs($this->teacherUser)->postJson($this->saveUrl($session), $this->payload())->assertUnprocessable();
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:05:00', 'UTC'));
        $this->post($this->saveUrl($session), $this->payload())->assertRedirect();
    }

    public function test_teacher_cannot_access_another_teachers_session_even_in_same_branch(): void
    {
        $otherUser = $this->user('teacher');
        $other = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $otherUser->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        $session = $this->makeLesson(['teacher_id' => $other->id]);
        $this->actingAs($this->teacherUser)->get(route('teacher.student-attendance.show', $session))->assertForbidden();
        $this->postJson($this->saveUrl($session), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_teacher_cannot_attach_an_unassigned_private_student(): void
    {
        $session = $this->makeLesson(['class_group_id' => null]);
        $this->actingAs($this->teacherUser)->postJson($this->saveUrl($session), $this->payload())->assertForbidden();
        $this->putJson(route('admin.student-attendance.roster', $session), ['student_ids' => [$this->student->id], 'expected_roster' => app(StudentAttendanceService::class)->rosterSignature($session)])->assertForbidden();
    }

    public function test_teacher_index_does_not_expose_other_classes_or_students(): void
    {
        $this->makeLesson();
        $other = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher')->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        $secretGroup = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'code' => Str::random(12), 'name' => 'SECRET_OTHER_CLASS', 'is_active' => true]);
        $this->makeLesson(['teacher_id' => $other->id, 'class_group_id' => $secretGroup->id]);
        $this->actingAs($this->teacherUser)->get($this->indexUrl())->assertOk()->assertDontSee('SECRET_OTHER_CLASS');
        $this->get($this->indexUrl(['class_group_id' => $secretGroup->id]))->assertForbidden();
    }

    public function test_inactive_teacher_and_child_account_cannot_record_or_read_internal_attendance(): void
    {
        $session = $this->makeLesson();
        $this->teacher->update(['is_active' => false]);
        $this->actingAs($this->teacherUser)->postJson($this->saveUrl($session), $this->payload())->assertForbidden();
        $this->actingAs($this->user('student'))->get(route('teacher.student-attendance.index'))->assertForbidden();
        $this->postJson($this->saveUrl($session), $this->payload())->assertForbidden();
    }

    public function test_teacher_without_profile_sees_empty_page_and_guest_is_blocked(): void
    {
        $this->getJson(route('teacher.student-attendance.index'))->assertUnauthorized();
        $this->actingAs($this->user('teacher'))->get(route('teacher.student-attendance.index'))->assertOk()->assertSee('Belum ada peserta');
    }

    public function test_admin_scope_uses_current_branch_assignment(): void
    {
        $session = $this->makeLesson();
        $admin = $this->user('admin');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'admin_user_id' => $admin->id, 'starts_on' => '2026-10-01']);
        $this->actingAs($admin)->get($this->indexUrl([], 'admin'))->assertOk();
        $this->post($this->saveUrl($session, role: 'admin'), $this->payload())->assertRedirect();
        $otherBranch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'SECRET_BRANCH', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
        $other = $this->makeLesson(['branch_id' => $otherBranch->id, 'class_group_id' => null]);
        $this->get(route('admin.student-attendance.show', $other))->assertForbidden();
        $this->get($this->indexUrl(['branch_id' => $otherBranch->id], 'admin'))->assertForbidden();
        $this->get($this->indexUrl([], 'admin'))->assertDontSee('SECRET_BRANCH');
        BranchAdminAssignment::where('admin_user_id', $admin->id)->update(['ends_on' => '2026-10-09']);
        $this->postJson($this->saveUrl($session, role: 'admin'), $this->payload('late', 1, ['correction_reason' => 'Review']))->assertForbidden();
    }

    public function test_correction_requires_reason_preserves_first_time_and_logs_actual_operator(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload('late'))->assertRedirect();
        $this->postJson($this->saveUrl($session), $this->payload('present', 1))->assertUnprocessable()->assertJsonValidationErrors('correction_reason');
        Carbon::setTestNow(Carbon::parse('2026-10-10 18:20:00', 'Asia/Makassar'));
        $sa = $this->user('superadmin');
        $this->actingAs($sa)->post($this->saveUrl($session, role: 'superadmin'), $this->payload('present', 1, ['correction_reason' => 'Jam datang sudah diperiksa']))->assertRedirect();
        $record = $this->ledger($session);
        $this->assertSame('18:00:00', $record->recorded_at->format('H:i:s'));
        $this->assertSame('18:20:00', $record->attendance_updated_at->format('H:i:s'));
        $this->assertSame($this->teacherUser->id, $record->attendance_recorded_by_user_id);
        $audit = StudentAttendanceAudit::orderByDesc('id')->firstOrFail();
        $this->assertSame('late', $audit->before_snapshot['attendance_status']);
        $this->assertSame('present', $audit->after_snapshot['attendance_status']);
        $this->assertSame($sa->id, $audit->actor_user_id);
        $this->assertSame('Jam datang sudah diperiksa', $audit->reason);
    }

    public function test_clear_is_audited_and_never_deletes_the_original_record(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload())->assertRedirect();
        $this->post($this->saveUrl($session), $this->payload(null, 1, ['correction_reason' => 'Salah mencatat']))->assertRedirect();
        $record = $this->ledger($session);
        $this->assertNull($record->attendance_status);
        $this->assertNotNull($record->recorded_at);
        $this->assertDatabaseCount('student_attendance_audits', 2);
        $this->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s['unrecorded'] === 1 && $s['absent'] === 0);
        $this->actingAs($this->user('superadmin'))->deleteJson(route('superadmin.session-students.destroy', [$session->id, $this->student->id]))->assertUnprocessable();
    }

    public function test_legacy_unknown_status_and_missing_timestamp_are_preserved_in_correction_history(): void
    {
        $session = $this->makeLesson();
        SessionStudent::create(['session_id' => $session->id, 'student_id' => $this->student->id, 'attendance_status' => 'old_custom_status']);
        $this->actingAs($this->teacherUser)->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s['unknown'] === 1);
        $this->post($this->saveUrl($session), $this->payload('present', 0, ['correction_reason' => 'Validasi catatan lama']))->assertRedirect();
        $record = $this->ledger($session);
        $this->assertNull($record->recorded_at);
        $this->assertNull($record->attendance_recorded_by_user_id);
        $this->assertSame('old_custom_status', StudentAttendanceAudit::firstOrFail()->before_snapshot['attendance_status']);
    }

    public function test_private_roster_is_selected_by_admin_and_teacher_can_record_afterwards(): void
    {
        $session = $this->makeLesson(['class_group_id' => null]);
        $this->actingAs($this->user('superadmin'))->put(route('superadmin.student-attendance.roster', $session), ['student_ids' => [$this->student->id], 'expected_roster' => app(StudentAttendanceService::class)->rosterSignature($session)])->assertRedirect();
        $this->assertNull($this->ledger($session)->attendance_status);
        $this->assertNull($this->ledger($session)->recorded_at);
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload())->assertRedirect();
        $this->assertSame('present', $this->ledger($session)->attendance_status);
        $this->assertDatabaseCount('student_attendance_audits', 2);
    }

    public function test_roster_rejects_invalid_enrollment_and_rolls_back_all_additions(): void
    {
        $session = $this->makeLesson(['class_group_id' => null]);
        $invalid = $this->enrolledStudent('Siswa belum mulai', ['began_on' => '2026-10-20']);
        $this->actingAs($this->user('superadmin'))->putJson(route('superadmin.student-attendance.roster', $session), ['student_ids' => [$this->student->id, $invalid->id], 'expected_roster' => app(StudentAttendanceService::class)->rosterSignature($session)])->assertUnprocessable();
        $this->assertDatabaseCount('session_students', 0);
        $this->assertDatabaseCount('student_attendance_audits', 0);
    }

    public function test_stale_roster_does_not_erase_a_new_attendance_record(): void
    {
        $session = $this->makeLesson(['class_group_id' => null]);
        $sa = $this->user('superadmin');
        $attendance = app(StudentAttendanceService::class);
        $attendance->setRoster($sa, $session, [$this->student->id], $attendance->rosterSignature($session));
        $signature = $attendance->rosterSignature($session);
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload())->assertRedirect();
        $this->actingAs($sa)->putJson(route('superadmin.student-attendance.roster', $session), ['student_ids' => [], 'expected_roster' => $signature])->assertUnprocessable();
        $this->assertSame('present', $this->ledger($session)->attendance_status);
        $this->putJson(route('superadmin.student-attendance.roster', $session), ['student_ids' => [], 'expected_roster' => $attendance->rosterSignature($session)])->assertUnprocessable();
    }

    public function test_blank_private_participant_can_be_removed_with_history(): void
    {
        $session = $this->makeLesson(['class_group_id' => null]);
        $attendance = app(StudentAttendanceService::class);
        $sa = $this->user('superadmin');
        $attendance->setRoster($sa, $session, [$this->student->id], $attendance->rosterSignature($session));
        $attendance->setRoster($sa, $session, [], $attendance->rosterSignature($session));
        $this->assertDatabaseCount('session_students', 0);
        $this->assertDatabaseCount('student_attendance_audits', 2);
        $this->assertSame('participant_removed', StudentAttendanceAudit::orderByDesc('id')->firstOrFail()->event_type);
    }

    public function test_validation_rejects_unknown_status_long_note_and_missing_version(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->postJson($this->saveUrl($session), $this->payload('arbitrary'))->assertUnprocessable()->assertJsonValidationErrors('attendance_status');
        $this->postJson($this->saveUrl($session), $this->payload('present', 0, ['individual_learning_note' => str_repeat('x', 256)]))->assertUnprocessable()->assertJsonValidationErrors('individual_learning_note');
        $this->postJson($this->saveUrl($session), ['attendance_status' => 'present'])->assertUnprocessable()->assertJsonValidationErrors('expected_version');
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_class_membership_dates_and_program_enrollment_are_enforced(): void
    {
        $session = $this->makeLesson();
        ClassGroupMembership::where('student_id', $this->student->id)->update(['starts_on' => '2026-10-02']);
        $this->actingAs($this->teacherUser)->postJson($this->saveUrl($session), $this->payload())->assertForbidden();
        ClassGroupMembership::where('student_id', $this->student->id)->update(['starts_on' => '2026-10-01']);
        StudentEnrollment::where('student_id', $this->student->id)->update(['ends_on' => '2026-09-30']);
        $this->postJson($this->saveUrl($session), $this->payload())->assertForbidden();
    }

    public function test_ninth_meeting_is_not_capped_and_table_columns_align(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            $ninth = $this->makeLesson(['planned_start_at' => sprintf('2026-10-%02d 09:00:00', $i), 'planned_end_at' => sprintf('2026-10-%02d 10:00:00', $i)]);
        }
        $response = $this->actingAs($this->teacherUser)->get($this->indexUrl())->assertOk()->assertSee('P9')->assertViewHas('summary', fn ($s) => $s['unrecorded'] === 9 && $s['meeting_count'] === 9);
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        $this->assertSame(11, $xpath->query('//table[contains(@class,"dpe-student-attendance-grid")]/thead/tr/th')->length);
        $this->assertSame(11, $xpath->query('//table[contains(@class,"dpe-student-attendance-grid")]/tbody/tr[1]/td')->length);
        $this->post($this->saveUrl($ninth), $this->payload())->assertRedirect();
        $this->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s['present'] === 1 && $s['unrecorded'] === 8 && $s['meeting_count'] === 9);
    }

    public function test_empty_month_keeps_eight_initial_meeting_columns_for_class_members(): void
    {
        $this->makeLesson(['planned_start_at' => '2026-11-01 09:00:00', 'planned_end_at' => '2026-11-01 10:00:00']);
        $response = $this->actingAs($this->teacherUser)->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s['meeting_count'] === 0);
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        libxml_clear_errors();
        $headers = (new \DOMXPath($dom))->query('//table[contains(@class,"dpe-student-attendance-grid")]/thead/tr/th');
        $this->assertSame(10, $headers->length);
        $this->assertSame('P8', trim($headers->item(8)->firstChild->textContent));
    }

    public function test_notes_and_reasons_are_escaped_on_detail_and_history_pages(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload('present', 0, ['individual_learning_note' => '<script>alert(1)</script>']))->assertRedirect();
        $this->get(route('teacher.student-attendance.edit', [$session, $this->student]))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('SECRET_INTERNAL_ONLY');
    }

    public function test_old_superadmin_api_cannot_bypass_correction_or_delete_a_record(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload())->assertRedirect();
        $this->actingAs($this->user('superadmin'))->putJson(route('superadmin.session-students.update', [$session->id, $this->student->id]), ['session_id' => $session->id, 'student_id' => $this->student->id, 'attendance_status' => 'absent', 'expected_version' => 1])->assertUnprocessable()->assertJsonValidationErrors('correction_reason');
        $this->deleteJson(route('superadmin.session-students.destroy', [$session->id, $this->student->id]))->assertUnprocessable();
        $this->assertSame('present', $this->ledger($session)->attendance_status);
        $this->expectException(ValidationException::class);
        app(SessionStudentService::class)->delete($session->id, $this->student->id);
    }

    public function test_record_identity_cannot_be_moved_by_old_api(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload())->assertRedirect();
        $this->actingAs($this->user('superadmin'))->putJson(route('superadmin.session-students.update', [$session->id, $this->student->id]), ['session_id' => $session->id, 'student_id' => $this->enrolledStudent()->id, 'attendance_status' => 'present', 'expected_version' => 1])->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('session_students', 1);
    }

    private function replacementPayload(LessonSession $source, string $date): array
    {
        return ['branch_id' => $source->branch_id, 'teacher_id' => $source->teacher_id, 'program_id' => $source->program_id, 'class_group_id' => $source->class_group_id, 'planned_start_at' => $date.' 09:00:00', 'planned_end_at' => $date.' 10:00:00', 'status' => 'scheduled', 'rescheduled_from_session_id' => $source->id];
    }

    public function test_rescheduled_private_meeting_transfers_roster_and_counts_only_actual_month(): void
    {
        $source = $this->makeLesson(['class_group_id' => null]);
        $sa = $this->user('superadmin');
        $attendance = app(StudentAttendanceService::class);
        $attendance->setRoster($sa, $source, [$this->student->id], $attendance->rosterSignature($source));
        $this->actingAs($this->teacherUser)->post($this->saveUrl($source), $this->payload('rescheduled'))->assertRedirect();
        $this->actingAs($sa);
        $replacement = app(LessonSessionService::class)->create($this->replacementPayload($source, '2026-11-01'));
        $this->assertNull($this->ledger($replacement)->attendance_status);
        $this->actingAs($this->teacherUser)->get($this->indexUrl(['class_group_id' => 'private']))->assertOk()->assertViewHas('summary', fn ($s) => $s['attended_count'] === 0 && $s['meeting_count'] === 0 && $s['rescheduled'] === 1);
        $this->postJson($this->saveUrl($source), $this->payload('present', 1, ['correction_reason' => 'Tidak boleh']))->assertUnprocessable();
        Carbon::setTestNow(Carbon::parse('2026-11-02 18:00:00', 'Asia/Makassar'));
        $this->post($this->saveUrl($replacement), $this->payload())->assertRedirect();
        $this->get($this->indexUrl(['period' => '2026-11', 'month' => '2026-11', 'class_group_id' => 'private']))->assertOk()->assertViewHas('summary', fn ($s) => $s['present'] === 1 && $s['meeting_count'] === 1);
    }

    public function test_legacy_source_attendance_is_not_double_counted_or_overwritten(): void
    {
        $source = $this->makeLesson();
        SessionStudent::create(['session_id' => $source->id, 'student_id' => $this->student->id, 'attendance_status' => 'present', 'recorded_at' => '2026-10-01 12:00:00']);
        $replacement = $this->makeLesson(['rescheduled_from_session_id' => $source->id, 'planned_start_at' => '2026-11-01 09:00:00', 'planned_end_at' => '2026-11-01 10:00:00']);
        $this->actingAs($this->teacherUser)->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s['attended_count'] === 0 && $s['rescheduled'] === 1);
        $this->assertSame('present', $this->ledger($source)->attendance_status);
        $this->assertSame('2026-10-01 12:00:00', $this->ledger($source)->recorded_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('student_attendance_audits', 0);
    }

    public function test_rescheduling_attended_session_requires_correction_first(): void
    {
        $source = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($source), $this->payload())->assertRedirect();
        $this->expectException(ValidationException::class);
        app(LessonSessionService::class)->create($this->replacementPayload($source, '2026-11-01'));
    }

    public function test_cancelled_replacement_does_not_hide_original_meeting(): void
    {
        $source = $this->makeLesson();
        $this->makeLesson(['rescheduled_from_session_id' => $source->id, 'status' => 'cancelled']);
        $this->actingAs($this->teacherUser)->post($this->saveUrl($source), $this->payload())->assertRedirect();
        $this->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s['present'] === 1 && $s['meeting_count'] === 1);
    }

    public function test_session_date_cannot_be_moved_after_student_attendance(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload())->assertRedirect();
        $data = $this->replacementPayload($session, '2026-10-05');
        $data['rescheduled_from_session_id'] = null;
        $this->expectException(ValidationException::class);
        app(LessonSessionService::class)->update($session, $data);
    }

    public function test_replacement_cannot_duplicate_a_source(): void
    {
        $source = $this->makeLesson();
        app(LessonSessionService::class)->create($this->replacementPayload($source, '2026-11-01'));
        $this->expectException(ValidationException::class);
        app(LessonSessionService::class)->create($this->replacementPayload($source, '2026-11-02'));
    }

    public function test_empty_submission_keeps_a_class_cell_unrecorded_without_inventing_history(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload(null))->assertRedirect();
        $this->assertDatabaseCount('session_students', 0);
        $this->assertDatabaseCount('student_attendance_audits', 0);
    }

    public function test_legacy_api_uses_server_time_and_blank_participants_have_no_recording_timestamp(): void
    {
        $session = $this->makeLesson(['class_group_id' => null]);
        $this->actingAs($this->user('superadmin'))->postJson(route('superadmin.session-students.store'), ['session_id' => $session->id, 'student_id' => $this->student->id, 'attendance_status' => null, 'recorded_at' => '2030-01-01 09:00:00'])->assertCreated();
        $this->assertNull($this->ledger($session)->recorded_at);
        $this->assertSame(0, $this->ledger($session)->attendance_version);
        $this->postJson(route('superadmin.session-students.store'), ['session_id' => $session->id, 'student_id' => $this->student->id, 'attendance_status' => 'present', 'recorded_at' => '2030-01-01 09:00:00', 'expected_version' => 0])->assertCreated();
        $this->assertSame('2026-10-10 18:00:00', $this->ledger($session)->recorded_at->format('Y-m-d H:i:s'));
    }

    public function test_cross_branch_reschedule_is_rejected_without_creating_replacement(): void
    {
        $source = $this->makeLesson();
        $branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Other', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
        $data = $this->replacementPayload($source, '2026-11-01');
        $data['branch_id'] = $branch->id;
        try {
            app(LessonSessionService::class)->create($data);
            $this->fail('Cross-branch replacement should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rescheduled_from_session_id', $exception->errors());
        }
        $this->assertDatabaseCount('lesson_sessions', 1);
    }

    public function test_month_outside_three_month_range_is_rejected(): void
    {
        $this->actingAs($this->teacherUser)->getJson($this->indexUrl(['month' => '2027-01']))->assertUnprocessable()->assertJsonValidationErrors('month');
    }

    public function test_note_only_api_update_preserves_attendance_status(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload())->assertRedirect();
        $this->actingAs($this->user('superadmin'))->putJson(route('superadmin.session-students.update', [$session->id, $this->student->id]), ['session_id' => $session->id, 'student_id' => $this->student->id, 'individual_learning_note' => 'Catatan ditambahkan.', 'expected_version' => 1, 'correction_reason' => 'Melengkapi keterangan.'])->assertOk()->assertJsonPath('attendance_status', 'present');
        $this->assertSame('present', $this->ledger($session)->attendance_status);
    }

    public function test_unrelated_legacy_replacement_does_not_hide_attendance(): void
    {
        $source = $this->makeLesson();
        $this->makeLesson(['rescheduled_from_session_id' => $source->id, 'class_group_id' => null]);
        $this->actingAs($this->teacherUser)->post($this->saveUrl($source), $this->payload())->assertRedirect();
        $this->get($this->indexUrl())->assertOk()->assertViewHas('summary', fn ($s) => $s['present'] === 1 && $s['rescheduled'] === 0);
    }

    public function test_class_roster_and_fresh_edit_pages_render_without_writing_attendance(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->get(route('teacher.student-attendance.show', $session))->assertOk()->assertSee($this->student->full_name)->assertDontSee('Kelola peserta private');
        $this->get(route('teacher.student-attendance.edit', [$session, $this->student]))->assertOk()->assertSee('Belum dicatat')->assertDontSee('SECRET_INTERNAL_ONLY');
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_private_manager_and_recorded_participant_protection_render_for_superadmin(): void
    {
        $session = $this->makeLesson(['class_group_id' => null]);
        $sa = $this->user('superadmin');
        $this->actingAs($sa)->get(route('superadmin.student-attendance.show', $session))->assertOk()->assertSee('Kelola peserta private')->assertSee($this->student->full_name);
        $service = app(StudentAttendanceService::class);
        $service->setRoster($sa, $session, [$this->student->id], $service->rosterSignature($session));
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload())->assertRedirect();
        $this->get(route('teacher.student-attendance.show', $session))->assertOk()->assertDontSee('Kelola peserta private');
        $this->actingAs($sa)->get(route('superadmin.student-attendance.show', $session))->assertOk()->assertSee('Memiliki catatan')->assertDontSee('SECRET_INTERNAL_ONLY');
    }

    public function test_legacy_unknown_status_is_visible_and_requires_explicit_new_choice_on_edit(): void
    {
        $session = $this->makeLesson();
        SessionStudent::create(['session_id' => $session->id, 'student_id' => $this->student->id, 'attendance_status' => 'legacy_status']);
        $this->actingAs($this->teacherUser)->get(route('teacher.student-attendance.edit', [$session, $this->student]))->assertOk()->assertSee('Status lama: legacy_status')->assertSee('pilih status');
        $this->postJson($this->saveUrl($session), ['expected_version' => 0, 'correction_reason' => 'Alasan'])->assertUnprocessable()->assertJsonValidationErrors('attendance_status');
        $this->assertSame('legacy_status', $this->ledger($session)->attendance_status);
    }

    private function attendanceXPath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        return new \DOMXPath($dom);
    }

    public function test_teacher_session_lists_class_students_with_aligned_attendance_checkboxes(): void
    {
        $second = $this->enrolledStudent('Siswa Kedua');
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $this->group->id, 'student_id' => $second->id, 'starts_on' => '2026-10-01']);
        $session = $this->makeLesson();
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.student-attendance.show', $session))->assertOk()->assertSee($this->student->full_name)->assertSee($second->full_name)->assertDontSee('SECRET_INTERNAL_ONLY');
        $xpath = $this->attendanceXPath($response->getContent());
        $table = '//table[contains(@class,"dpe-student-attendance-session-table")]';
        $this->assertSame(6, $xpath->query($table.'/thead/tr/th')->length);
        $this->assertSame('Hadir', trim($xpath->query($table.'/thead/tr/th[2]')->item(0)->textContent));
        $this->assertSame('Mulai les', trim($xpath->query($table.'/thead/tr/th[3]')->item(0)->textContent));
        $this->assertSame(2, $xpath->query($table.'/tbody/tr')->length);
        foreach ($xpath->query($table.'/tbody/tr') as $row) {
            $this->assertSame(6, $xpath->query('td', $row)->length);
            $checkbox = $xpath->query('td[2]//input[@type="checkbox"]', $row)->item(0);
            $this->assertNotNull($checkbox);
            $this->assertSame('attendance_status', $checkbox->getAttribute('name'));
            $this->assertSame('present', $checkbox->getAttribute('value'));
            $this->assertFalse($checkbox->hasAttribute('disabled'));
            $this->assertFalse($checkbox->hasAttribute('checked'));
            $this->assertSame(1, $xpath->query('td[6]/a', $row)->length);
        }
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_teacher_private_session_shows_only_selected_students_and_their_checkboxes(): void
    {
        $second = $this->enrolledStudent('Peserta Private Kedua');
        $unassigned = $this->enrolledStudent('Siswa Tidak Dipilih');
        $session = $this->makeLesson(['class_group_id' => null]);
        $service = app(StudentAttendanceService::class);
        $service->setRoster($this->user('superadmin'), $session, [$this->student->id, $second->id], $service->rosterSignature($session));
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.student-attendance.show', $session))->assertOk()->assertSee($this->student->full_name)->assertSee($second->full_name)->assertDontSee($unassigned->full_name)->assertDontSee('Kelola peserta private');
        $this->assertSame(2, $this->attendanceXPath($response->getContent())->query('//input[@data-attendance-check]')->length);
        $this->assertNull($this->ledger($session)->attendance_status);
    }

    #[DataProvider('attendanceStatuses')]
    public function test_session_checkbox_reflects_saved_status_and_requires_correction(string $status, int $attended, int $meetings): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->post($this->saveUrl($session), $this->payload($status))->assertRedirect();
        $response = $this->get(route('teacher.student-attendance.show', $session))->assertOk();
        $xpath = $this->attendanceXPath($response->getContent());
        $form = $xpath->query('//form[@data-attendance-quick]')->item(0);
        $checkbox = $xpath->query('.//input[@data-attendance-check]', $form)->item(0);
        $this->assertSame($attended === 1, $checkbox->hasAttribute('checked'));
        $this->assertSame('1', $form->getAttribute('data-requires-reason'));
        $this->assertSame(route('teacher.student-attendance.edit', [$session, $this->student]), $form->getAttribute('data-edit-url'));
        $this->assertSame('1', $xpath->query('.//input[@name="expected_version"]', $form)->item(0)->getAttribute('value'));
        $this->assertSame($status, $this->ledger($session)->attendance_status);
    }

    public function test_future_session_displays_student_name_but_disables_attendance_checkbox(): void
    {
        $session = $this->makeLesson(['planned_start_at' => '2026-11-01 09:00:00', 'planned_end_at' => '2026-11-01 10:00:00']);
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.student-attendance.show', $session))->assertOk()->assertSee($this->student->full_name)->assertSee('Belum berlangsung');
        $checkbox = $this->attendanceXPath($response->getContent())->query('//input[@data-attendance-check]')->item(0);
        $this->assertTrue($checkbox->hasAttribute('disabled'));
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_superadmin_enrollment_and_class_member_forms_make_student_visible_to_teacher(): void
    {
        ClassGroupMembership::where('student_id', $this->student->id)->delete();
        StudentEnrollment::where('student_id', $this->student->id)->delete();
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->get(route('teacher.student-attendance.show', $session))->assertOk()->assertSee('Minta Super Admin')->assertDontSee($this->student->full_name);
        $this->actingAs($this->user('superadmin'))->get(route('superadmin.student-attendance.show', $session))->assertOk()->assertSee(route('superadmin.class-group-memberships.create', ['class_group_id' => $this->group->id]), false)->assertSee(route('superadmin.student-enrollments.index'), false);
        $this->post(route('superadmin.student-enrollments.store'), ['student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-10-01'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('superadmin.class-group-memberships.store'), ['student_id' => $this->student->id, 'class_group_id' => $this->group->id, 'starts_on' => '2026-10-01'])->assertSessionHasNoErrors()->assertRedirect();
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.student-attendance.show', $session))->assertOk()->assertSee($this->student->full_name);
        $checkbox = $this->attendanceXPath($response->getContent())->query('//input[@data-attendance-check]')->item(0);
        $this->assertNotNull($checkbox);
        $this->assertFalse($checkbox->hasAttribute('disabled'));
    }

    #[DataProvider('attendanceStatuses')]
    public function test_ajax_saves_status_with_canonical_cell_and_matching_monthly_recap(string $status, int $attended, int $meetings): void
    {
        $session = $this->makeLesson();
        $response = $this->actingAs($this->teacherUser)->postJson($this->saveUrl($session), $this->payload($status))
            ->assertOk()->assertJsonPath('message', 'Kehadiran siswa berhasil disimpan.')
            ->assertJsonPath('cell.session_id', $session->id)->assertJsonPath('cell.student_id', $this->student->id)
            ->assertJsonPath('cell.status', $status)->assertJsonPath('cell.version', 1)
            ->assertJsonPath('cell.requires_reason', true)->assertJsonPath('cell.can_record', true)
            ->assertJsonPath('cell.is_attending', $attended === 1)
            ->assertJsonPath('cell.contribution.attended_count', $attended)
            ->assertJsonPath('cell.contribution.meeting_count', $meetings)
            ->assertJsonPath('cell.contribution.unrecorded', 0)
            ->assertJsonPath('cell.recorded_at_label', '10 Oct 2026 18:00')
            ->assertDontSee('SECRET_INTERNAL_ONLY');
        $this->assertFalse($response->headers->has('Location'));
        $this->assertArrayNotHasKey('record', $response->json('cell'));
        $this->assertDatabaseCount('student_attendance_audits', 1);
        $this->assertSame($status, $this->ledger($session)->attendance_status);
        $monthly = $this->get($this->indexUrl())->assertOk();
        $this->assertSame($monthly->viewData('summary'), $response->json('cell.contribution'));
    }

    public function test_ajax_correction_requires_reason_preserves_notes_and_first_recording_time(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->postJson($this->saveUrl($session), $this->payload('present', 0, ['individual_learning_note' => 'Belajar reading']))->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-10-10 19:00:00', 'Asia/Makassar'));
        $this->postJson($this->saveUrl($session), $this->payload('late', 1))->assertUnprocessable()->assertJsonValidationErrors('correction_reason');
        $this->assertDatabaseCount('student_attendance_audits', 1);
        $this->assertSame('present', $this->ledger($session)->attendance_status);
        $this->postJson($this->saveUrl($session), $this->payload('late', 1, ['correction_reason' => 'Siswa datang terlambat']))
            ->assertOk()->assertJsonPath('cell.version', 2)->assertJsonPath('cell.status', 'late')
            ->assertJsonPath('cell.is_attending', true)->assertJsonPath('cell.recorded_at_label', '10 Oct 2026 18:00')
            ->assertJsonPath('cell.contribution.present', 0)->assertJsonPath('cell.contribution.late', 1);
        $this->postJson($this->saveUrl($session), $this->payload(null, 2, ['correction_reason' => 'Perlu diperiksa ulang']))
            ->assertOk()->assertJsonPath('cell.version', 3)->assertJsonPath('cell.status', null)
            ->assertJsonPath('cell.is_attending', false)->assertJsonPath('cell.requires_reason', true)
            ->assertJsonPath('cell.contribution.absent', 0)->assertJsonPath('cell.contribution.unrecorded', 1)
            ->assertJsonPath('cell.recorded_at_label', '10 Oct 2026 18:00');
        $this->assertSame('Belajar reading', $this->ledger($session)->individual_learning_note);
        $this->assertDatabaseCount('student_attendance_audits', 3);
    }

    public function test_ajax_stale_version_returns_latest_cell_without_overwriting_or_adding_audit(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->teacherUser)->postJson($this->saveUrl($session), $this->payload('late'))->assertOk();
        $this->postJson($this->saveUrl($session), $this->payload('absent', 1, ['correction_reason' => 'Koreksi operator']))->assertOk();
        $this->postJson($this->saveUrl($session), $this->payload('present', 1, ['correction_reason' => 'Halaman lama']))
            ->assertUnprocessable()->assertJsonValidationErrors('expected_version')
            ->assertJsonPath('cell.status', 'absent')->assertJsonPath('cell.version', 2)
            ->assertJsonPath('cell.is_attending', false)->assertJsonPath('cell.contribution.absent', 1)
            ->assertJsonPath('cell.requires_reason', true)->assertDontSee('SECRET_INTERNAL_ONLY');
        $this->assertSame('absent', $this->ledger($session)->attendance_status);
        $this->assertDatabaseCount('student_attendance_audits', 2);
    }

    public function test_ajax_still_rejects_unassigned_teacher(): void
    {
        $session = $this->makeLesson();
        $this->actingAs($this->user('teacher'))->postJson($this->saveUrl($session), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('session_students', 0);
        $this->assertDatabaseCount('student_attendance_audits', 0);
    }

    public function test_confirmation_markup_and_initial_recap_are_available_in_both_teacher_views(): void
    {
        $this->student->update(['full_name' => 'Ayu <script> & "Putri"']);
        $session = $this->makeLesson();
        foreach ([$this->indexUrl(), route('teacher.student-attendance.show', $session)] as $url) {
            $response = $this->actingAs($this->teacherUser)->get($url)->assertOk()->assertDontSee('SECRET_INTERNAL_ONLY');
            $xpath = $this->attendanceXPath($response->getContent());
            $this->assertSame(1, $xpath->query('//*[@data-attendance-modal]')->length);
            $this->assertSame(1, $xpath->query('//form[@data-attendance-confirm-form]')->length);
            $this->assertSame(7, $xpath->query('//select[@data-attendance-confirm-status]/option')->length);
            $form = $xpath->query('//form[@data-attendance-quick]')->item(0);
            $this->assertSame($this->student->full_name, $form->getAttribute('data-student-name'));
            $cell = json_decode($form->getAttribute('data-attendance-cell'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(0, $cell['version']);
            $this->assertSame(1, $cell['contribution']['unrecorded']);
            $this->assertSame('button', $xpath->query('.//button[@data-attendance-open]', $form)->item(0)->getAttribute('type'));
        }
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_empty_class_month_guides_superadmin_without_showing_management_links_to_teacher(): void
    {
        ClassGroupMembership::where('student_id', $this->student->id)->delete();
        $this->makeLesson();
        $this->actingAs($this->user('superadmin'))->get($this->indexUrl([], 'superadmin'))->assertOk()->assertSee('Menyiapkan daftar siswa')->assertSee(route('superadmin.class-group-memberships.create', ['class_group_id' => $this->group->id]), false);
        $this->actingAs($this->teacherUser)->get($this->indexUrl())->assertOk()->assertSee('Minta Super Admin')->assertDontSee(route('superadmin.class-group-memberships.create', ['class_group_id' => $this->group->id]), false);
    }
}
