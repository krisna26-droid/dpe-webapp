<?php

namespace Tests\Feature\Teacher;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\TeacherAttendanceExceptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherBranchVisitTest extends TestCase
{
    use RefreshDatabase;

    private User $teacherUser;

    private Teacher $teacher;

    private Branch $branch;

    private Program $program;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 10, 10, 0, 0, 'Asia/Makassar'));
        Storage::fake('local');
        $this->teacherUser = $this->user('teacher');
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->teacherUser->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        $this->branch = $this->branch();
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => 'PRG-'.Str::random(6), 'name' => 'English', 'class_type' => 'private', 'is_active' => true]);
        $this->admin = $this->user('admin');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'admin_user_id' => $this->admin->id, 'starts_on' => '2026-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => $role.Str::random(8), 'password_hash' => bcrypt('password'), 'full_name' => 'Pengguna '.$role, 'role_code' => $role, 'is_active' => true]);
    }

    private function branch(): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(8), 'name' => 'Cabang '.Str::random(5), 'address' => 'Alamat cabang', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function makeSession(string $location = 'branch', string $start = '2026-10-10 09:00:00'): LessonSession
    {
        return LessonSession::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'teaching_location' => $location, 'planned_start_at' => $start, 'planned_end_at' => Carbon::parse($start)->addHour(), 'status' => 'scheduled', 'topic' => 'Percakapan']);
    }

    private function payload(string $location = 'captured'): array
    {
        return ['photo' => UploadedFile::fake()->image('camera.jpg'), 'location_status' => $location, 'latitude' => $location === 'captured' ? '-8.65' : null, 'longitude' => $location === 'captured' ? '115.21' : null, 'accuracy' => 0, 'location_note' => $location === 'unavailable' ? 'GPS perangkat tidak tersedia.' : null];
    }

    private function arrive(): TeacherAttendance
    {
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.arrive', $this->branch), $this->payload())->assertRedirect()->assertSessionHasNoErrors();

        return TeacherAttendance::where('active_visit_teacher_id', $this->teacher->id)->firstOrFail();
    }

    public function test_visit_does_not_require_a_class_session_and_has_private_photo_and_audit(): void
    {
        $visit = $this->arrive();
        $this->assertNull($visit->session_id);
        $this->assertSame('branch_visit', $visit->attendance_mode);
        $this->assertSame('2026-10-10', $visit->attendance_date->toDateString());
        Storage::disk('local')->assertExists($visit->checkInPhoto->storage_key);
        $this->assertDatabaseHas('teacher_attendance_audit_logs', ['teacher_attendance_id' => $visit->id, 'actor_user_id' => $this->teacherUser->id, 'event_type' => 'arrived']);
    }

    public function test_two_visits_on_same_day_can_finish_without_waiting_for_review(): void
    {
        $first = $this->arrive();
        Carbon::setTestNow(now()->addHour());
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.depart', $first), $this->payload('unavailable'))->assertSessionHasNoErrors();
        $this->assertSame('pending_review', $first->refresh()->status);
        $this->assertNull($first->active_visit_teacher_id);
        $second = $this->arrive();
        $this->assertNotSame($first->id, $second->id);
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.depart', $second), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('teacher_attendances', 2);
        $this->assertDatabaseCount('teacher_attendance_audit_logs', 4);
    }

    public function test_duplicate_arrival_is_rejected_and_failed_photo_is_cleaned_up(): void
    {
        $this->arrive();
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.arrive', $this->branch), $this->payload())->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('teacher_attendances', 1);
        $this->assertDatabaseCount('file_assets', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('teacher-attendance'));
    }

    public function test_no_active_assignment_or_inactive_branch_cannot_receive_a_visit(): void
    {
        $other = $this->branch();
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.arrive', $other), $this->payload())->assertForbidden();
        $this->branch->update(['is_active' => false]);
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.arrive', $this->branch), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    public function test_another_teacher_cannot_close_a_visit(): void
    {
        $visit = $this->arrive();
        $other = $this->user('teacher');
        Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $other->id, 'whatsapp_number' => '081234567891', 'is_active' => true]);
        $this->actingAs($other)->post(route('teacher.attendance.depart', $visit), $this->payload())->assertForbidden();
        $this->assertNull($visit->refresh()->check_out_at);
        $this->assertDatabaseCount('file_assets', 1);
    }

    public function test_open_visit_remains_visible_and_can_close_after_midnight_and_assignment_expiry(): void
    {
        $visit = $this->arrive();
        $this->teacher->branchAssignments()->update(['ends_on' => '2026-10-10']);
        Carbon::setTestNow(now()->addDay());
        $this->actingAs($this->teacherUser)->get(route('teacher.attendance.index'))->assertOk()->assertSee('Absen Pulang');
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.depart', $visit), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('2026-10-10', $visit->refresh()->attendance_date->toDateString());
    }

    public function test_missing_photo_and_missing_gps_reason_are_rejected(): void
    {
        $payload = $this->payload('unavailable');
        unset($payload['photo'], $payload['location_note']);
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.arrive', $this->branch), $payload)->assertSessionHasErrors(['photo', 'location_note']);
        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    public function test_branch_class_cannot_be_clocked_per_session(): void
    {
        $session = $this->makeSession();
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.check-in', $session), $this->payload())->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    public function test_private_session_has_start_and_finish_and_does_not_hide_missing_branch_day(): void
    {
        $private = $this->makeSession('student_home');
        $this->makeSession();
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.check-in', $private), $this->payload())->assertSessionHasNoErrors();
        $record = TeacherAttendance::where('session_id', $private->id)->firstOrFail();
        $this->assertSame('private_session', $record->attendance_mode);
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.check-out', $private), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('pending_review', $record->refresh()->status);
        $days = app(TeacherAttendanceExceptionService::class)->dueBranchDays($this->admin, now()->startOfDay(), now()->endOfDay());
        $this->assertCount(1, $days);
    }

    public function test_future_private_session_cannot_start_today(): void
    {
        $session = $this->makeSession('student_home', '2026-10-11 09:00:00');
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.check-in', $session), $this->payload())->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    public function test_multiple_branch_classes_produce_one_missing_day_and_one_manual_exception(): void
    {
        $first = $this->makeSession();
        $second = $this->makeSession('branch', '2026-10-10 09:30:00');
        $service = app(TeacherAttendanceExceptionService::class);
        $this->assertCount(1, $service->dueBranchDays($this->admin, now()->startOfDay(), now()->endOfDay()));
        $this->actingAs($this->admin)->post(route('admin.teacher-attendance.sessions.mark', $first), ['attendance_type' => 'sick', 'manual_note' => 'Ada surat sakit.'])->assertSessionHasNoErrors();
        $record = TeacherAttendance::firstOrFail();
        $this->assertNull($record->session_id);
        $this->assertSame('branch_day', $record->attendance_mode);
        $this->assertCount(0, $service->dueBranchDays($this->admin, now()->startOfDay(), now()->endOfDay()));
        $this->actingAs($this->admin)->post(route('admin.teacher-attendance.sessions.mark', $second), ['attendance_type' => 'absent'])->assertSessionHasErrors('attendance');
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.arrive', $this->branch), $this->payload())->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('teacher_attendances', 1);
    }

    public function test_a_branch_visit_prevents_manual_absence_for_any_class_on_that_day(): void
    {
        $session = $this->makeSession();
        $this->arrive();
        $this->actingAs($this->admin)->post(route('admin.teacher-attendance.sessions.mark', $session), ['attendance_type' => 'absent'])->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('teacher_attendances', 1);
    }

    public function test_visit_monitoring_filters_review_audit_and_photo_access_are_scoped(): void
    {
        $visit = $this->arrive();
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.depart', $visit), $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get(route('admin.teacher-attendance.index', ['attendance_mode' => 'branch_visit', 'date_from' => '2026-10-10', 'date_to' => '2026-10-10']))->assertOk()->assertSee(route('admin.teacher-attendance.show', $visit), false);
        $this->actingAs($this->admin)->patch(route('admin.teacher-attendance.review', $visit), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('teacher_attendance_audit_logs', ['teacher_attendance_id' => $visit->id, 'event_type' => 'approved', 'actor_user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->get(route('admin.teacher-attendance.photo', [$visit, 'check-in']))->assertOk();
        $other = $this->user('admin');
        $this->actingAs($other)->get(route('admin.teacher-attendance.show', $visit))->assertForbidden();
        $this->actingAs($other)->get(route('admin.teacher-attendance.photo', [$visit, 'check-in']))->assertForbidden();
        $this->actingAs($this->teacherUser)->get(route('admin.teacher-attendance.photo', [$visit, 'check-in']))->assertForbidden();
    }

    public function test_legacy_open_branch_session_can_finish_without_being_converted_into_visit(): void
    {
        $session = $this->makeSession();
        $record = TeacherAttendance::create(['id' => (string) Str::uuid(), 'session_id' => $session->id, 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id, 'attendance_mode' => 'legacy_session', 'check_in_at' => now(), 'status' => 'in_progress']);
        $this->actingAs($this->teacherUser)->get(route('teacher.attendance.index'))->assertOk()->assertSee('Sesi lama')->assertSee('Absen Selesai');
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.check-out', $session), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('legacy_session', $record->refresh()->attendance_mode);
        $this->assertNotNull($record->check_out_at);
    }

    public function test_upgrade_preserves_old_record_and_rollback_with_new_data_is_blocked(): void
    {
        $migration = require database_path('migrations/2026_10_10_040000_support_branch_visits_and_private_attendance.php');
        $migration->down();
        $sessionId = (string) Str::uuid();
        DB::table('lesson_sessions')->insert(['id' => $sessionId, 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'planned_start_at' => '2026-09-15 09:00:00', 'planned_end_at' => '2026-09-15 10:00:00', 'status' => 'scheduled']);
        $id = (string) Str::uuid();
        DB::table('teacher_attendances')->insert(['id' => $id, 'session_id' => $sessionId, 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id, 'check_in_at' => '2026-09-15 09:05:00', 'check_out_at' => '2026-09-15 10:05:00', 'status' => 'approved', 'review_note' => 'Catatan awal.']);
        $migration->up();
        $old = TeacherAttendance::findOrFail($id);
        $this->assertSame('legacy_session', $old->attendance_mode);
        $this->assertSame('2026-09-15', $old->attendance_date->toDateString());
        $this->assertSame('Catatan awal.', $old->review_note);
        $this->assertSame('09:05', $old->check_in_at->format('H:i'));
        $this->arrive();
        try {
            $migration->down();
            $this->fail('Rollback dengan kunjungan baru harus ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback v27 ditolak', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('teacher_attendances', 'attendance_mode'));
        $this->assertDatabaseCount('teacher_attendances', 2);
    }

    public function test_superadmin_actions_record_the_actual_operator(): void
    {
        $superadmin = $this->user('superadmin');
        $session = $this->makeSession('student_home');
        $this->actingAs($superadmin)->post(route('teacher.attendance.check-in', $session), $this->payload())->assertSessionHasNoErrors();
        $record = TeacherAttendance::where('session_id', $session->id)->firstOrFail();
        $this->assertDatabaseHas('teacher_attendance_audit_logs', ['teacher_attendance_id' => $record->id, 'event_type' => 'started', 'actor_user_id' => $superadmin->id]);
        $visit = $this->arrive();
        $this->actingAs($superadmin)->post(route('teacher.attendance.depart', $visit), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('teacher_attendance_audit_logs', ['teacher_attendance_id' => $visit->id, 'event_type' => 'departed', 'actor_user_id' => $superadmin->id]);
    }

    public function test_legacy_open_session_must_finish_before_starting_a_new_branch_visit(): void
    {
        $session = $this->makeSession();
        TeacherAttendance::create(['id' => (string) Str::uuid(), 'session_id' => $session->id, 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id, 'attendance_mode' => 'legacy_session', 'check_in_at' => now(), 'status' => 'in_progress']);
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.arrive', $this->branch), $this->payload())->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('teacher_attendances', 1);
        $this->assertDatabaseCount('file_assets', 0);
    }

    public function test_two_teachers_can_visit_same_branch_at_the_same_time(): void
    {
        $first = $this->arrive();
        $otherUser = $this->user('teacher');
        $otherTeacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $otherUser->id, 'whatsapp_number' => '081234567891', 'is_active' => true]);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $otherTeacher->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->actingAs($otherUser)->post(route('teacher.attendance.arrive', $this->branch), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('teacher_attendances', 2);
        $this->actingAs($otherUser)->get(route('teacher.attendance.index'))->assertOk()->assertDontSee(route('teacher.attendance.depart', $first), false);
    }

    public function test_duplicate_departure_cannot_replace_the_original_evidence(): void
    {
        $visit = $this->arrive();
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.depart', $visit), $this->payload())->assertSessionHasNoErrors();
        $photoId = $visit->refresh()->check_out_photo_file_id;
        $this->actingAs($this->teacherUser)->post(route('teacher.attendance.depart', $visit), $this->payload())->assertSessionHasErrors('attendance');
        $this->assertSame($photoId, $visit->refresh()->check_out_photo_file_id);
        $this->assertDatabaseCount('file_assets', 2);
    }
}
