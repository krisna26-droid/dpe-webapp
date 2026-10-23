<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LessonLearningAudit;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LessonLearningService;
use App\Services\LessonSessionService;
use App\Services\StudentAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LessonLearningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Teacher $teacher;

    private Branch $branch;

    private Program $program;

    private ClassGroup $group;

    private Student $student;

    private LessonSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-10 18:00:00', 'Asia/Makassar'));
        $this->actor = $this->user('teacher');
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => 'Cabang Catatan', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => 'English Regular', 'class_type' => 'regular', 'is_active' => true]);
        $this->group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'default_teacher_id' => $this->teacher->id, 'code' => Str::random(10), 'name' => 'Kelas Catatan', 'is_active' => true]);
        $this->student = $this->newStudent('Siswa Catatan');
        $this->addMember($this->student);
        $this->session = $this->newSession();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(15), 'password_hash' => 'fixture-hash', 'full_name' => ucfirst($role).' Catatan', 'role_code' => $role, 'is_active' => true]);
    }

    private function newStudent(string $name, array $attributes = []): Student
    {
        $student = Student::create($attributes + ['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student')->id, 'full_name' => $name, 'began_on' => '2026-10-01', 'status' => 'active']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => $student->began_on->toDateString()]);

        return $student;
    }

    private function addMember(Student $student, array $attributes = []): void
    {
        ClassGroupMembership::create($attributes + ['id' => (string) Str::uuid(), 'class_group_id' => $this->group->id, 'student_id' => $student->id, 'starts_on' => $student->began_on->toDateString()]);
    }

    private function newSession(array $attributes = []): LessonSession
    {
        return LessonSession::create($attributes + ['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'class_group_id' => $this->group->id, 'planned_start_at' => '2026-10-01 09:00:00', 'planned_end_at' => '2026-10-01 10:30:00', 'teaching_location' => 'branch', 'status' => 'scheduled']);
    }

    private function url(string $action = 'save', string $role = 'teacher', ?LessonSession $session = null): string
    {
        return route($role.'.learning-notes.'.$action, $action === 'index' ? ['month' => '2026-10'] : ($session ?? $this->session));
    }

    private function payload(array $extra = [], ?LessonSession $session = null): array
    {
        $session = ($session ?? $this->session)->fresh();
        $service = app(LessonLearningService::class);

        return $extra + [
            'expected_snapshot' => $service->signature($session, $service->participants($session)),
            'topic' => 'Daily conversation', 'material' => 'Worksheet unit 3', 'activity' => 'Role play',
        ];
    }

    private function record(?string $status = 'present', ?string $note = null): SessionStudent
    {
        return app(StudentAttendanceService::class)->record($this->actor, $this->session, $this->student, ['attendance_status' => $status, 'individual_learning_note' => $note, 'expected_version' => 0]);
    }

    private function ledger(): SessionStudent
    {
        return SessionStudent::where('session_id', $this->session->id)->where('student_id', $this->student->id)->firstOrFail();
    }

    public function test_index_and_form_use_real_roster_and_staff_navigation_without_creating_records(): void
    {
        $this->actingAs($this->actor)->get($this->url('index'))->assertOk()->assertSee('Kelas Catatan')->assertViewHas('total', 1)->assertViewHas('pending', 1);
        $response = $this->get($this->url('edit'))->assertOk()->assertSee('Siswa Catatan')->assertSee('Kembali ke Catatan Belajar')->assertSee('Buka absensi siswa');
        $response->assertViewHas('participants', fn ($rows) => $rows->keys()->all() === [$this->student->id]);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//input[@name="expected_snapshot"]')->length);
        $this->assertSame(1, $xpath->query('//textarea[@name="student_notes['.$this->student->id.']"]')->length);
        $this->assertDatabaseCount('session_students', 0);
        $this->assertDatabaseCount('lesson_learning_audits', 0);
        $this->assertDatabaseCount('student_attendance_audits', 0);
    }

    public function test_first_learning_save_keeps_attendance_blank_and_logs_operator_and_snapshots(): void
    {
        $this->actingAs($this->actor)->put($this->url(), $this->payload(['student_notes' => [$this->student->id => 'Mampu menyapa dengan lancar.'], 'attendance_status' => 'present', 'recorded_at' => '2030-01-01']))
            ->assertRedirect($this->url('edit'))->assertSessionHas('success', 'Catatan belajar berhasil disimpan.');
        $this->assertSame('Daily conversation', $this->session->fresh()->topic);
        $record = $this->ledger();
        $this->assertSame('Mampu menyapa dengan lancar.', $record->individual_learning_note);
        $this->assertNull($record->attendance_status);
        $this->assertNull($record->recorded_at);
        $this->assertNull($record->attendance_recorded_by_user_id);
        $this->assertSame(1, $record->attendance_version);
        $event = LessonLearningAudit::firstOrFail();
        $this->assertSame($this->actor->id, $event->actor_user_id);
        $this->assertSame('recorded', $event->event_type);
        $this->assertSame('2026-10-10 18:00:00', $event->happened_at->format('Y-m-d H:i:s'));
        $this->assertNull($event->before_snapshot['topic']);
        $this->assertSame($record->individual_learning_note, $event->after_snapshot['student_notes'][$this->student->id]);
        $this->assertDatabaseCount('student_attendance_audits', 1);
        $this->assertDatabaseCount('teacher_messages', 0);
        $this->assertDatabaseCount('student_challenges', 0);
        $this->get(route('teacher.student-attendance.show', $this->session))->assertOk()->assertSee($this->url('edit'), false)->assertSee('Belum dicatat');
    }

    public static function statuses(): array
    {
        return [['present'], ['late'], ['absent'], ['sick'], ['permission'], ['rescheduled']];
    }

    #[DataProvider('statuses')]
    public function test_note_changes_preserve_each_attendance_status_first_time_and_recorder(string $status): void
    {
        $before = $this->record($status);
        Carbon::setTestNow(Carbon::parse('2026-10-10 20:00:00', 'Asia/Makassar'));
        $this->actingAs($this->actor)->put($this->url(), $this->payload(['student_notes' => [$this->student->id => 'Catatan tambahan'], 'correction_reason' => 'Melengkapi evaluasi.']))->assertRedirect();
        $after = $this->ledger();
        $this->assertSame($status, $after->attendance_status);
        $this->assertSame($before->getRawOriginal('recorded_at'), $after->getRawOriginal('recorded_at'));
        $this->assertSame($before->attendance_recorded_by_user_id, $after->attendance_recorded_by_user_id);
        $this->assertSame(2, $after->attendance_version);
        $this->assertSame('Catatan tambahan', $after->individual_learning_note);
        $this->actingAs($this->actor)->postJson(route('teacher.student-attendance.save', [$this->session, $this->student]), ['attendance_status' => 'present', 'expected_version' => 1, 'correction_reason' => 'Perubahan absensi'])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_version')->assertJsonPath('cell.version', 2);
        $this->assertSame('Catatan tambahan', $this->ledger()->individual_learning_note);
    }

    public function test_missing_reason_prevents_all_changes_and_correction_keeps_the_old_values(): void
    {
        $this->record('present', 'Catatan awal');
        $this->session->update(['topic' => 'Topik awal']);
        $payload = $this->payload(['student_notes' => [$this->student->id => 'Revisi catatan']]);
        $this->actingAs($this->actor)->putJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors('correction_reason');
        $this->assertSame('Topik awal', $this->session->fresh()->topic);
        $this->assertSame('Catatan awal', $this->ledger()->individual_learning_note);
        $this->assertDatabaseCount('lesson_learning_audits', 0);
        $this->assertDatabaseCount('student_attendance_audits', 1);
        $this->put($this->url(), $payload + ['correction_reason' => 'Memeriksa hasil latihan.'])->assertRedirect();
        $event = LessonLearningAudit::firstOrFail();
        $this->assertSame('corrected', $event->event_type);
        $this->assertSame('Topik awal', $event->before_snapshot['topic']);
        $this->assertSame('Catatan awal', $event->before_snapshot['student_notes'][$this->student->id]);
        $this->get($this->url('edit'))->assertOk()->assertSee('Topik awal')->assertSee('Catatan awal')->assertSee('Memeriksa hasil latihan.');
    }

    public function test_same_values_do_not_require_reason_or_make_an_audit_event(): void
    {
        $this->session->update(['topic' => 'Topik awal']);
        $this->actingAs($this->actor)->put($this->url(), $this->payload(['topic' => 'Topik awal', 'material' => null, 'activity' => null, 'student_notes' => [$this->student->id => null]]))->assertRedirect()->assertSessionHas('success', 'Tidak ada perubahan catatan belajar.');
        $this->assertDatabaseCount('lesson_learning_audits', 0);
        $this->assertDatabaseCount('session_students', 0);
    }

    public static function concurrentChanges(): array
    {
        return [['topic'], ['attendance'], ['roster']];
    }

    #[DataProvider('concurrentChanges')]
    public function test_stale_form_cannot_overwrite_new_notes_attendance_or_roster(string $change): void
    {
        $payload = $this->payload(['student_notes' => [$this->student->id => 'Isian form lama']]);
        if ($change === 'topic') {
            $this->session->update(['topic' => 'Isian terbaru dari Super Admin']);
        } elseif ($change === 'attendance') {
            $this->record();
        } else {
            $this->addMember($this->newStudent('Peserta tambahan'));
        }
        $this->actingAs($this->actor)->putJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        $this->assertSame($change === 'topic' ? 'Isian terbaru dari Super Admin' : null, $this->session->fresh()->topic);
        $this->assertDatabaseCount('lesson_learning_audits', 0);
        $this->assertFalse(SessionStudent::where('individual_learning_note', 'Isian form lama')->exists());
    }

    public function test_html_stale_response_retains_input_and_requires_reload_before_saving(): void
    {
        $payload = $this->payload();
        $this->record();
        $this->actingAs($this->actor)->from($this->url('edit'))->put($this->url(), $payload)->assertRedirect($this->url('edit'))->assertSessionHasErrors('expected_snapshot');
        $response = $this->get($this->url('edit'))->assertOk()->assertSee('Muat data terbaru')->assertSee('Daily conversation')->assertDontSee('Simpan catatan belajar');
        $this->assertStringContainsString('readonly', $response->getContent());
        $this->get($this->url('edit'))->assertOk()->assertSee('Simpan catatan belajar');
    }

    public function test_non_participant_injected_into_notes_is_rejected_without_any_write(): void
    {
        $outside = $this->newStudent('Bukan peserta');
        $this->actingAs($this->actor)->putJson($this->url(), $this->payload(['student_notes' => [$this->student->id => 'Valid', $outside->id => 'Tidak sah']]))->assertForbidden();
        $this->assertNull($this->session->fresh()->topic);
        $this->assertDatabaseCount('session_students', 0);
        $this->assertDatabaseCount('lesson_learning_audits', 0);
    }

    public function test_legacy_unknown_status_is_readable_but_cannot_be_changed_by_learning_form(): void
    {
        SessionStudent::create(['session_id' => $this->session->id, 'student_id' => $this->student->id, 'attendance_status' => 'legacy-unknown', 'individual_learning_note' => 'Catatan lama']);
        $this->actingAs($this->actor)->get($this->url('edit'))->assertOk()->assertSee('Status lama: legacy-unknown')->assertSee('Catatan lama');
        $this->putJson($this->url(), $this->payload(['student_notes' => [$this->student->id => 'Baru'], 'correction_reason' => 'Koreksi']))->assertUnprocessable()->assertJsonValidationErrors('student_notes.'.$this->student->id);
        $this->assertSame('legacy-unknown', $this->ledger()->attendance_status);
        $this->assertSame('Catatan lama', $this->ledger()->individual_learning_note);
    }

    public static function blockedSessions(): array
    {
        return [['future'], ['cancelled'], ['replacement']];
    }

    #[DataProvider('blockedSessions')]
    public function test_future_cancelled_and_superseded_sessions_only_allow_viewing(string $kind): void
    {
        if ($kind === 'future') {
            $this->session->update(['planned_start_at' => '2026-10-11 09:00:00', 'planned_end_at' => '2026-10-11 10:30:00']);
        } elseif ($kind === 'cancelled') {
            $this->session->update(['status' => 'cancelled']);
        } else {
            $this->newSession(['rescheduled_from_session_id' => $this->session->id, 'planned_start_at' => '2026-10-02 09:00:00', 'planned_end_at' => '2026-10-02 10:30:00']);
        }
        $this->actingAs($this->actor)->get($this->url('edit'))->assertOk()->assertDontSee('Simpan catatan belajar');
        $this->putJson($this->url(), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->assertNull($this->session->fresh()->topic);
        $this->assertDatabaseCount('lesson_learning_audits', 0);
    }

    public function test_before_join_student_is_read_only_and_a_bad_second_note_prevents_partial_save(): void
    {
        $late = $this->newStudent('Mulai kemudian', ['began_on' => '2026-10-05']);
        SessionStudent::create(['session_id' => $this->session->id, 'student_id' => $late->id, 'individual_learning_note' => 'Riwayat tetap ada']);
        $this->actingAs($this->actor)->get($this->url('edit'))->assertOk()->assertSee('Sebelum mulai les')->assertSee('Riwayat tetap ada');
        $this->putJson($this->url(), $this->payload(['student_notes' => [$this->student->id => 'Catatan valid', $late->id => 'Tidak sah'], 'correction_reason' => 'Perubahan']))->assertUnprocessable()->assertJsonValidationErrors('student_notes.'.$late->id);
        $this->assertNull($this->session->fresh()->topic);
        $this->assertFalse(SessionStudent::where('student_id', $this->student->id)->exists());
        $this->assertDatabaseCount('lesson_learning_audits', 0);
    }

    public function test_private_roster_is_explicit_and_empty_roster_does_not_create_dummy_students(): void
    {
        $private = $this->newSession(['class_group_id' => null, 'teaching_location' => 'student_home']);
        $this->actingAs($this->actor)->get($this->url('edit', 'teacher', $private))->assertOk()->assertViewHas('participants', fn ($rows) => $rows->isEmpty())->assertSee('Belum ada peserta');
        $this->put($this->url('save', 'teacher', $private), $this->payload([], $private))->assertRedirect();
        $this->assertDatabaseCount('session_students', 0);
        SessionStudent::create(['session_id' => $private->id, 'student_id' => $this->student->id]);
        $this->get($this->url('edit', 'teacher', $private))->assertOk()->assertViewHas('participants', fn ($rows) => $rows->count() === 1)->assertSee('Siswa Catatan');
    }

    public function test_teacher_cannot_view_other_teacher_sessions_or_options(): void
    {
        $otherUser = $this->user('teacher');
        $other = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $otherUser->id, 'whatsapp_number' => '081234567891', 'is_active' => true]);
        $session = $this->newSession(['teacher_id' => $other->id, 'topic' => 'PRIVATE_OTHER_TEACHER']);
        $this->actingAs($this->actor)->get($this->url('index'))->assertOk()->assertDontSee('PRIVATE_OTHER_TEACHER');
        $this->get($this->url('edit', 'teacher', $session))->assertForbidden();
        $this->putJson($this->url('save', 'teacher', $session), $this->payload([], $session))->assertForbidden();
        $this->assertDatabaseCount('lesson_learning_audits', 0);
    }

    public function test_admin_is_scoped_to_active_branch_and_superadmin_can_manage_all(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get($this->url('index', 'admin'))->assertOk()->assertViewHas('total', 0);
        $this->get($this->url('edit', 'admin'))->assertForbidden();
        $this->putJson($this->url('save', 'admin'), $this->payload())->assertForbidden();
        $assignment = BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-10-01']);
        $this->get($this->url('edit', 'admin'))->assertOk();
        $this->put($this->url('save', 'admin'), $this->payload())->assertRedirect($this->url('edit', 'admin'));
        $this->assertSame($admin->id, LessonLearningAudit::firstOrFail()->actor_user_id);
        $assignment->update(['ends_on' => '2026-10-09']);
        $this->get($this->url('edit', 'admin'))->assertForbidden();
        $superadmin = $this->user('superadmin');
        $this->actingAs($superadmin)->get($this->url('edit', 'superadmin'))->assertOk()->assertSee('Daily conversation');
        $this->put($this->url('save', 'superadmin'), $this->payload(['topic' => 'Evaluasi owner', 'correction_reason' => 'Peninjauan']))->assertRedirect();
        $this->assertSame($superadmin->id, LessonLearningAudit::latest('id')->firstOrFail()->actor_user_id);
    }

    public function test_child_and_inactive_profiles_cannot_read_or_save_notes(): void
    {
        $this->actingAs($this->user('student'))->get($this->url('edit'))->assertForbidden();
        $this->putJson($this->url(), $this->payload())->assertForbidden();
        $this->teacher->update(['is_active' => false]);
        $this->actingAs($this->actor)->get($this->url('index'))->assertOk()->assertViewHas('total', 0);
        $this->get($this->url('edit'))->assertForbidden();
        $this->putJson($this->url(), $this->payload())->assertForbidden();
        $this->teacher->update(['is_active' => true]);
        $this->actor->update(['is_active' => false]);
        $this->get($this->url('index'))->assertForbidden();
    }

    public function test_filters_date_boundaries_completeness_and_action_column(): void
    {
        $this->newSession(['topic' => 'Conversation lengkap', 'material' => 'Materi', 'activity' => 'Aktivitas']);
        $this->newSession(['planned_start_at' => '2026-09-30 09:00:00', 'planned_end_at' => '2026-09-30 10:30:00', 'topic' => 'BULAN_LAIN']);
        $this->newSession(['planned_start_at' => '2026-11-01 09:00:00', 'planned_end_at' => '2026-11-01 10:30:00']);
        $url = $this->url('index').'&completion=complete&q=Conversation';
        $response = $this->actingAs($this->actor)->get($url)->assertOk()->assertViewHas('total', 1)->assertViewHas('complete', 1)->assertViewHas('sessions', fn ($rows) => $rows->total() === 1)->assertDontSee('BULAN_LAIN');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(7, $xpath->query('//table[contains(@class,"dpe-lesson-learning-table")]/thead/tr/th')->length);
        $this->assertSame(1, $xpath->query('//table[contains(@class,"dpe-lesson-learning-table")]/tbody/tr/td[7]/a')->length);
        $this->assertSame(0, $xpath->query('//table[contains(@class,"dpe-lesson-learning-table")]/tbody/tr/td[3]/a')->length);
        $this->get($this->url('index').'&completion=pending')->assertOk()->assertViewHas('sessions', fn ($rows) => $rows->total() === 1);
        $this->getJson($this->url('index').'&month=invalid')->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->getJson($this->url('index').'&branch_id='.Str::uuid())->assertForbidden();
        $this->getJson($this->url('index').'&class_group_id='.Str::uuid())->assertForbidden();
    }

    public function test_invalid_lengths_and_missing_snapshot_cannot_mutate_data(): void
    {
        $payload = $this->payload(['topic' => str_repeat('x', 256), 'student_notes' => [$this->student->id => str_repeat('y', 256)], 'expected_snapshot' => 'invalid']);
        $this->actingAs($this->actor)->putJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors(['topic', 'student_notes.'.$this->student->id, 'expected_snapshot']);
        $this->assertNull($this->session->fresh()->topic);
        $this->assertDatabaseCount('lesson_learning_audits', 0);
        $this->assertDatabaseCount('session_students', 0);
    }

    public function test_repeat_post_is_rejected_and_partial_drafts_are_allowed(): void
    {
        $payload = $this->payload(['topic' => 'Topik saja', 'material' => null, 'activity' => null]);
        $this->actingAs($this->actor)->put($this->url(), $payload)->assertRedirect();
        $this->putJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        $this->assertDatabaseCount('lesson_learning_audits', 1);
        $this->get($this->url('index'))->assertOk()->assertViewHas('pending', 1)->assertViewHas('complete', 0);
    }

    public function test_values_and_student_names_are_escaped_and_zero_note_is_not_erased(): void
    {
        $this->student->update(['full_name' => '<script>STUDENT_SCRIPT</script>']);
        $this->actingAs($this->actor)->put($this->url(), $this->payload(['topic' => '<script>TOPIC_SCRIPT</script>', 'student_notes' => [$this->student->id => '0']]))->assertRedirect();
        $this->assertSame('0', $this->ledger()->individual_learning_note);
        $this->get($this->url('edit'))->assertOk()->assertSee('<script>STUDENT_SCRIPT</script>')->assertDontSee('<script>STUDENT_SCRIPT</script>', false)->assertDontSee('<script>TOPIC_SCRIPT</script>', false);
    }

    public function test_learning_history_prevents_moving_identity_or_meeting_date_without_attendance(): void
    {
        $this->actingAs($this->actor)->put($this->url(), $this->payload())->assertRedirect();
        $this->assertDatabaseCount('session_students', 0);
        $data = $this->session->fresh()->getAttributes();
        $data['planned_start_at'] = '2026-10-03 09:00:00';
        $data['planned_end_at'] = '2026-10-03 10:30:00';
        try {
            app(LessonSessionService::class)->update($this->session, $data);
            $this->fail('Riwayat pembelajaran tidak boleh dipindahkan ke jadwal lain.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('planned_start_at', $exception->errors());
        }
        $this->assertSame('2026-10-01 09:00:00', $this->session->fresh()->getRawOriginal('planned_start_at'));
    }

    public function test_additive_migration_preserves_old_notes_attendance_and_refuses_used_history_rollback(): void
    {
        $this->session->update(['topic' => 'Topik lama', 'material' => 'Materi lama', 'activity' => 'Aktivitas lama']);
        $record = $this->record('late', 'Catatan lama');
        Schema::drop('lesson_learning_audits');
        $migration = require database_path('migrations/2026_10_10_060000_create_lesson_learning_audits.php');
        $migration->up();
        $this->assertSame('Topik lama', $this->session->fresh()->topic);
        $this->assertSame('late', $this->ledger()->attendance_status);
        $this->assertSame($record->getRawOriginal('recorded_at'), $this->ledger()->getRawOriginal('recorded_at'));
        $this->assertSame('Catatan lama', $this->ledger()->individual_learning_note);
        $this->actingAs($this->actor)->put($this->url(), $this->payload(['correction_reason' => 'Memperbarui topik.']))->assertRedirect();
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_session_pagination_keeps_filters_and_only_lists_accessible_results(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->newSession();
        }
        $url = route('teacher.learning-notes.index', ['month' => '2026-10', 'q' => 'Kelas', 'page' => 2]);
        $this->actingAs($this->actor)->get($url)->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->total() === 17 && $sessions->currentPage() === 2 && $sessions->count() === 2)
            ->assertSee('month=2026-10')->assertSee('q=Kelas');
        $this->get(route('teacher.learning-notes.index', ['month' => '']))->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['month'] === '2026-10');
    }

    public function test_history_pagination_keeps_the_edit_form_and_renders_the_requested_changes(): void
    {
        for ($i = 0; $i < 11; $i++) {
            LessonLearningAudit::create([
                'session_id' => $this->session->id, 'actor_user_id' => $this->actor->id,
                'event_type' => 'corrected', 'happened_at' => now(),
                'before_snapshot' => ['topic' => 'Sebelum '.$i, 'student_notes' => []],
                'after_snapshot' => ['topic' => 'Sesudah '.$i, 'student_notes' => []],
                'reason' => 'Riwayat '.$i,
            ]);
        }
        $this->actingAs($this->actor)->get($this->url('edit'))->assertOk()->assertViewHas('historyRows', fn ($rows) => $rows->count() === 10)
            ->assertSee('Sesudah 10')->assertDontSee('Sesudah 0')->assertSee('history_page=2#learning-history', false);
        $this->get($this->url('edit').'?history_page=2')->assertOk()->assertViewHas('historyRows', fn ($rows) => $rows->count() === 1)
            ->assertSee('Sebelum 0')->assertSee('Sesudah 0')->assertSee('Simpan catatan belajar');
    }

    public function test_actual_start_controls_month_and_editing_instead_of_future_planned_date(): void
    {
        $this->session->update(['planned_start_at' => '2026-11-01 09:00:00', 'planned_end_at' => '2026-11-01 10:30:00', 'actual_start_at' => '2026-10-03 09:00:00']);
        $this->actingAs($this->actor)->get($this->url('index'))->assertOk()->assertViewHas('total', 1);
        $this->get($this->url('edit'))->assertOk()->assertViewHas('meetingAt', fn ($date) => $date->format('Y-m-d') === '2026-10-03')->assertSee('Simpan catatan belajar');
        $this->put($this->url(), $this->payload())->assertRedirect();
        $this->assertSame('Daily conversation', $this->session->fresh()->topic);
    }
}
