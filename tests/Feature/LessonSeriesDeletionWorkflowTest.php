<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LessonScheduleSeries;
use App\Models\LessonScheduleSeriesChange;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\ClassLearningSessionService;
use App\Services\LessonSeriesDeletionService;
use App\Services\LessonSeriesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LessonSeriesDeletionWorkflowTest extends TestCase
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

    private function data(array $extra = []): array
    {
        return $extra + ['branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id,
            'program_id' => $this->program->id, 'class_group_id' => $this->group->id, 'teaching_location' => 'branch',
            'starts_on' => '2026-10-26', 'ends_on' => '2026-11-09', 'weekdays' => [1], 'starts_at' => '10:00', 'ends_at' => '11:30',
            'topic' => 'Percakapan awal', 'material' => null, 'activity' => null];
    }

    private function service(): LessonSeriesService
    {
        return app(LessonSeriesService::class);
    }

    private function preview(array $extra = [], ?User $actor = null): array
    {
        return $this->service()->previewCreate($actor ?? $this->admin, $this->data($extra));
    }

    private function createSeries(array $extra = [], ?User $actor = null): LessonScheduleSeries
    {
        $preview = $this->preview($extra, $actor);

        return $this->service()->create($actor ?? $this->admin, ['preview_token' => $preview['previewToken'], 'excluded_dates' => $extra['excluded_dates'] ?? []]);
    }

    private function existing(string $date, string $start = '10:30', string $end = '12:00', array $extra = []): LessonSession
    {
        return LessonSession::create($extra + ['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id,
            'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'class_group_id' => $this->group->id,
            'teaching_location' => 'branch', 'planned_start_at' => $date.' '.$start.':00', 'planned_end_at' => $date.' '.$end.':00', 'status' => 'scheduled']);
    }

    private function invalid(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail('Expected validation error for '.$field);
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
        }
    }

    private function futureData(LessonSession $anchor, array $extra = []): array
    {
        return $extra + ['teacher_id' => $anchor->teacher_id, 'planned_start_at' => $anchor->planned_start_at->format('Y-m-d').'T13:00',
            'planned_end_at' => $anchor->planned_start_at->format('Y-m-d').'T14:30', 'status' => 'scheduled',
            'topic' => 'Jadwal baru', 'material' => null, 'activity' => null];
    }

    private function deletionVersion(LessonScheduleSeries $series, ?User $actor = null): string
    {
        return app(LessonSeriesDeletionService::class)->preview($actor ?? $this->admin, $series->fresh())['version'];
    }

    public static function deletingRoles(): array
    {
        return [['admin'], ['superadmin']];
    }

    #[DataProvider('deletingRoles')]
    public function test_authorized_staff_can_delete_all_meetings_and_only_the_selected_series(string $prefix): void
    {
        $actor = $prefix === 'admin' ? $this->admin : $this->superadmin;
        $series = $this->createSeries();
        $ids = $series->sessions()->pluck('id');
        $other = $this->createSeries(['starts_at' => '13:00', 'ends_at' => '14:00']);
        $groupBefore = $this->group->fresh()->getRawOriginal();
        $membershipBefore = ClassGroupMembership::firstOrFail()->getRawOriginal();
        $response = $this->actingAs($actor)->delete(route($prefix.'.lesson-series.destroy', $series), ['deletion_version' => $this->deletionVersion($series, $actor)]);
        $response->assertSessionHasNoErrors()->assertRedirect(route($prefix.'.lesson-sessions.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('lesson_schedule_series', ['id' => $series->id]);
        $this->assertSame(0, LessonSession::whereIn('id', $ids)->count());
        $this->assertSame(3, $other->sessions()->count());
        $this->assertSame($groupBefore, $this->group->fresh()->getRawOriginal());
        $this->assertSame($membershipBefore, ClassGroupMembership::firstOrFail()->getRawOriginal());
        $this->get(route($prefix.'.lesson-series.show', $series))->assertNotFound();
        $this->actingAs($this->teacher->user)->get(route('teacher.schedule.show', $ids->first()))->assertNotFound();
        $event = DB::table('operational_deletion_events')->where('record_id', $series->id)->first();
        $snapshot = json_decode($event->before_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(3, $snapshot['sessions']);
        $this->assertSame($actor->id, $event->actor_user_id);
    }

    public function test_delete_preview_is_read_only_and_explains_scope_and_confirmation(): void
    {
        $series = $this->createSeries();
        $this->actingAs($this->admin)->get(route('admin.lesson-series.show', $series))->assertOk()
            ->assertSee('Hapus seluruh jadwal seri')->assertSee('data-confirm-form', false)->assertSee('name="deletion_version"', false)
            ->assertViewHas('deletion', fn ($d) => $d['count'] === 3 && strlen($d['version']) === 64);
        $this->assertSame(3, $series->sessions()->count());
        $this->assertDatabaseCount('operational_deletion_events', 0);
    }

    public function test_delete_removes_all_pages_of_a_long_series(): void
    {
        $series = $this->createSeries(['starts_on' => '2026-10-26', 'ends_on' => '2026-11-30', 'weekdays' => [1, 2, 3, 4, 5, 6, 7]]);
        $this->assertSame(36, $series->sessions()->count());
        $this->actingAs($this->admin)->get(route('admin.lesson-series.show', $series))->assertOk()
            ->assertViewHas('sessions', fn ($rows) => $rows->count() === 30 && $rows->total() === 36)->assertViewHas('deletion', fn ($d) => $d['count'] === 36);
        $this->delete(route('admin.lesson-series.destroy', $series), ['deletion_version' => $this->deletionVersion($series)])->assertSessionHasNoErrors();
        $this->assertSame(0, LessonSession::where('schedule_series_id', $series->id)->count());
        $snapshot = json_decode(DB::table('operational_deletion_events')->where('record_id', $series->id)->value('before_snapshot'), true);
        $this->assertCount(36, $snapshot['sessions']);
    }

    public function test_empty_participant_links_and_series_changes_are_captured_before_deletion(): void
    {
        $series = $this->createSeries();
        $lesson = $series->sessions()->firstOrFail();
        SessionStudent::create(['session_id' => $lesson->id, 'student_id' => $this->student->id]);
        LessonScheduleSeriesChange::create(['id' => (string) Str::uuid(), 'series_id' => $series->id, 'actor_user_id' => $this->admin->id, 'request_key' => (string) Str::uuid(), 'before_snapshot' => [['topic' => 'Lama']], 'after_snapshot' => [['topic' => 'Baru']], 'created_at' => now()]);
        $aliasId = $series->class_learning_session_id;
        app(ClassLearningSessionService::class)->select(ClassGroupMembership::firstOrFail(), [$aliasId], '2026-10-23');
        $selectedBefore = DB::table('class_learning_session_enrollments')->get()->toJson();
        $count = app(LessonSeriesDeletionService::class)->delete($this->admin, $series, $this->deletionVersion($series));
        $this->assertSame(3, $count);
        $this->assertDatabaseMissing('session_students', ['session_id' => $lesson->id]);
        $this->assertDatabaseMissing('lesson_schedule_series_changes', ['series_id' => $series->id]);
        $this->assertDatabaseHas('class_learning_sessions', ['id' => $aliasId]);
        $this->assertSame($selectedBefore, DB::table('class_learning_session_enrollments')->get()->toJson());
        $snapshot = json_decode(DB::table('operational_deletion_events')->where('record_id', $series->id)->value('before_snapshot'), true);
        $this->assertCount(1, $snapshot['participants']);
        $this->assertCount(1, $snapshot['changes']);
    }

    public static function protectedRecords(): array
    {
        return array_map(fn ($type) => [$type], ['teacher_attendance', 'student_attendance', 'student_note', 'student_recorded_at', 'student_recorded_by', 'student_updated_at', 'student_version', 'attendance_audit', 'roster_audit', 'learning_audit', 'video', 'challenge', 'message', 'actual_start', 'actual_end', 'completed', 'ongoing', 'replacement_child', 'cancelled_replacement_child', 'replacement_source']);
    }

    private function protect(LessonSession $lesson, string $type): void
    {
        if (in_array($type, ['student_attendance', 'student_note', 'student_recorded_at', 'student_recorded_by', 'student_updated_at', 'student_version'], true)) {
            $fields = match ($type) {
                'student_attendance' => ['attendance_status' => 'present'],
                'student_note' => ['individual_learning_note' => 'Catatan siswa'],
                'student_recorded_at' => ['recorded_at' => now()],
                'student_recorded_by' => ['attendance_recorded_by_user_id' => $this->admin->id],
                'student_updated_at' => ['attendance_updated_at' => now()],
                'student_version' => ['attendance_version' => 1],
            };
            SessionStudent::create(['session_id' => $lesson->id, 'student_id' => $this->student->id] + $fields);

            return;
        }
        if ($type === 'teacher_attendance') {
            TeacherAttendance::create(['id' => (string) Str::uuid(), 'session_id' => $lesson->id, 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id, 'status' => 'in_progress']);

            return;
        }
        if (in_array($type, ['attendance_audit', 'roster_audit'], true)) {
            DB::table('student_attendance_audits')->insert(['session_id' => $lesson->id, 'student_id' => $this->student->id, 'actor_user_id' => $this->admin->id, 'event_type' => $type === 'roster_audit' ? 'roster' : 'record', 'happened_at' => now()]);

            return;
        }
        if ($type === 'learning_audit') {
            DB::table('lesson_learning_audits')->insert(['session_id' => $lesson->id, 'actor_user_id' => $this->admin->id, 'event_type' => 'record', 'before_snapshot' => '{}', 'after_snapshot' => '{}', 'happened_at' => now()]);

            return;
        }
        if ($type === 'video') {
            DB::table('learning_videos')->insert(['id' => (string) Str::uuid(), 'session_id' => $lesson->id, 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'video_on' => '2026-10-26', 'topic' => 'Video uji', 'video_url' => 'https://example.test/video']);

            return;
        }
        if ($type === 'challenge') {
            DB::table('student_challenges')->insert(['id' => (string) Str::uuid(), 'session_id' => $lesson->id, 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'category_name' => 'Uji', 'logged_on' => '2026-10-26']);

            return;
        }
        if ($type === 'message') {
            DB::table('teacher_messages')->insert(['id' => (string) Str::uuid(), 'session_id' => $lesson->id, 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'message_body' => 'Pesan uji']);

            return;
        }
        if (in_array($type, ['actual_start', 'actual_end'], true)) {
            $lesson->update([$type.'_at' => now()]);

            return;
        }
        if (in_array($type, ['completed', 'ongoing'], true)) {
            $lesson->update(['status' => $type]);

            return;
        }
        if (in_array($type, ['replacement_child', 'cancelled_replacement_child'], true)) {
            $this->existing('2026-11-10', '10:00', '11:30', ['rescheduled_from_session_id' => $lesson->id, 'status' => $type === 'cancelled_replacement_child' ? 'cancelled' : 'scheduled']);

            return;
        }
        $source = $this->existing('2026-10-25', '10:00', '11:30');
        $lesson->update(['rescheduled_from_session_id' => $source->id]);
    }

    #[DataProvider('protectedRecords')]
    public function test_a_single_protected_meeting_prevents_the_entire_series_deletion(string $type): void
    {
        $series = $this->createSeries();
        $lesson = $series->sessions()->orderByDesc('planned_start_at')->firstOrFail();
        $this->protect($lesson, $type);
        $before = $series->sessions()->orderBy('id')->get()->map(fn ($s) => $s->getRawOriginal())->all();
        $this->actingAs($this->admin)->from(route('admin.lesson-series.show', $series))
            ->delete(route('admin.lesson-series.destroy', $series), ['deletion_version' => $this->deletionVersion($series)])
            ->assertRedirect(route('admin.lesson-series.show', $series))->assertSessionHasErrors('delete_series');
        $this->assertDatabaseHas('lesson_schedule_series', ['id' => $series->id]);
        $this->assertSame($before, $series->sessions()->orderBy('id')->get()->map(fn ($s) => $s->getRawOriginal())->all());
        $this->assertDatabaseCount('operational_deletion_events', 0);
        $this->get(route('admin.lesson-series.show', $series))->assertOk()->assertSee('Seluruh jadwal tetap tersimpan.');
    }

    public function test_edited_meetings_invalidate_a_stale_delete_form(): void
    {
        $series = $this->createSeries();
        $version = $this->deletionVersion($series);
        $series->sessions()->firstOrFail()->update(['topic' => 'Diubah petugas lain']);
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy', $series), ['deletion_version' => $version])->assertSessionHasErrors('deletion_version');
        $this->assertSame(3, $series->sessions()->count());
        $this->assertDatabaseCount('operational_deletion_events', 0);
    }

    public function test_attendance_added_after_opening_the_page_is_checked_again(): void
    {
        $series = $this->createSeries();
        $version = $this->deletionVersion($series);
        $this->protect($series->sessions()->firstOrFail(), 'student_attendance');
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy', $series), ['deletion_version' => $version])->assertSessionHasErrors('delete_series');
        $this->assertSame(3, $series->sessions()->count());
    }

    public function test_admin_cannot_delete_another_branch_series_even_with_valid_version(): void
    {
        $foreignTeacher = $this->teacher('Guru luar');
        $this->assign($foreignTeacher, $this->otherBranch);
        $series = $this->createSeries(['branch_id' => $this->otherBranch->id, 'teacher_id' => $foreignTeacher->id, 'class_group_id' => $this->otherGroup->id], $this->superadmin);
        $version = $this->deletionVersion($series, $this->superadmin);
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy', $series), ['deletion_version' => $version])->assertForbidden();
        $this->assertSame(3, $series->sessions()->count());
    }

    public function test_expired_branch_assignment_cannot_authorize_deletion(): void
    {
        $series = $this->createSeries();
        $version = $this->deletionVersion($series);
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-10-22']);
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy', $series), ['deletion_version' => $version])->assertForbidden();
        $this->assertSame(3, $series->sessions()->count());
    }

    public static function forbiddenDeletingRoles(): array
    {
        return [['teacher'], ['student']];
    }

    #[DataProvider('forbiddenDeletingRoles')]
    public function test_teachers_and_students_cannot_delete_schedules(string $role): void
    {
        $series = $this->createSeries();
        $this->actingAs($this->user($role))->delete(route('admin.lesson-series.destroy', $series), ['deletion_version' => $this->deletionVersion($series)])->assertForbidden();
        $this->assertSame(3, $series->sessions()->count());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $series = $this->createSeries();
        $this->delete(route('admin.lesson-series.destroy', $series), ['deletion_version' => $this->deletionVersion($series)])->assertRedirect(route('login'));
        $this->assertSame(3, $series->sessions()->count());
    }

    public function test_missing_confirmation_version_does_not_delete_any_meeting(): void
    {
        $series = $this->createSeries();
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy', $series))->assertSessionHasErrors('deletion_version');
        $this->assertSame(3, $series->sessions()->count());
    }

    public function test_inactive_actor_is_rechecked_by_service(): void
    {
        $series = $this->createSeries();
        $version = $this->deletionVersion($series);
        DB::table('users')->where('id', $this->admin->id)->update(['is_active' => false]);
        try {
            app(LessonSeriesDeletionService::class)->delete($this->admin, $series, $version);
            $this->fail('Inactive actor must be rejected');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(3, $series->sessions()->count());
    }

    public function test_replayed_creation_preview_cannot_recreate_a_deleted_series(): void
    {
        $preview = $this->preview();
        $series = $this->service()->create($this->admin, ['preview_token' => $preview['previewToken']]);
        app(LessonSeriesDeletionService::class)->delete($this->admin, $series, $this->deletionVersion($series));
        $this->invalid(fn () => $this->service()->create($this->admin, ['preview_token' => $preview['previewToken']]), 'preview_token');
        $this->assertDatabaseCount('lesson_schedule_series', 0);
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public function test_a_new_preview_can_reuse_the_same_time_and_existing_session_name(): void
    {
        $old = $this->createSeries(['learning_session_name' => 'Sesi 1']);
        $aliasId = $old->class_learning_session_id;
        app(LessonSeriesDeletionService::class)->delete($this->admin, $old, $this->deletionVersion($old));
        $new = $this->createSeries(['class_learning_session_id' => $aliasId]);
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame(3, $new->sessions()->count());
        $this->assertSame($aliasId, $new->class_learning_session_id);
    }

    public function test_already_cancelled_empty_series_can_be_deleted(): void
    {
        $series = $this->createSeries();
        $series->sessions()->update(['status' => 'cancelled']);
        $this->assertSame(3, app(LessonSeriesDeletionService::class)->delete($this->admin, $series, $this->deletionVersion($series)));
        $this->assertDatabaseMissing('lesson_schedule_series', ['id' => $series->id]);
    }

    public function test_past_mistaken_dates_without_operational_records_can_be_deleted(): void
    {
        $series = $this->createSeries();
        Carbon::setTestNow(Carbon::parse('2026-12-01', 'Asia/Makassar'));
        $this->assertSame(3, app(LessonSeriesDeletionService::class)->delete($this->admin, $series, $this->deletionVersion($series)));
        $this->assertDatabaseMissing('lesson_schedule_series', ['id' => $series->id]);
    }

    public function test_repeated_delete_does_not_create_duplicate_audit_events(): void
    {
        $series = $this->createSeries();
        $version = $this->deletionVersion($series);
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy', $series), ['deletion_version' => $version])->assertSessionHasNoErrors();
        $this->delete(route('admin.lesson-series.destroy', $series), ['deletion_version' => $version])->assertNotFound();
        $this->assertDatabaseCount('operational_deletion_events', 1);
    }

    private function singleDeletionVersion(LessonScheduleSeries $series, LessonSession $session, ?User $actor = null): string
    {
        return app(LessonSeriesDeletionService::class)->preview($actor ?? $this->admin, $series->fresh())['meeting_versions'][$session->id];
    }

    #[DataProvider('deletingRoles')]
    public function test_staff_can_delete_only_the_selected_meeting_without_changing_other_dates(string $prefix): void
    {
        $actor = $prefix === 'admin' ? $this->admin : $this->superadmin;
        $series = $this->createSeries(['learning_session_name' => 'Sesi 1']);
        $target = $series->sessions()->orderBy('planned_start_at')->get()[1];
        $others = $series->sessions()->whereKeyNot($target->id)->orderBy('id')->get()->map(fn ($s) => $s->getRawOriginal())->all();
        $another = $this->createSeries(['starts_at' => '13:00', 'ends_at' => '14:00']);
        $seriesBefore = $series->fresh()->getRawOriginal();
        $this->actingAs($actor)->delete(route($prefix.'.lesson-series.destroy-meeting', [$series, $target]),
            ['deletion_version' => $this->singleDeletionVersion($series, $target, $actor)])
            ->assertSessionHasNoErrors()->assertRedirect(route($prefix.'.lesson-series.show', [$series, 'page' => 1]).'#series-meetings')
            ->assertSessionHas('success', fn ($message) => str_contains($message, '02/11/2026'));
        $this->assertDatabaseMissing('lesson_sessions', ['id' => $target->id]);
        $this->assertSame($others, $series->sessions()->orderBy('id')->get()->map(fn ($s) => $s->getRawOriginal())->all());
        $this->assertSame(3, $another->sessions()->count());
        $this->assertSame($seriesBefore, $series->fresh()->getRawOriginal());
        $this->assertDatabaseHas('class_learning_sessions', ['id' => $series->class_learning_session_id]);
        $this->actingAs($this->teacher->user)->get(route('teacher.schedule.show', $target))->assertNotFound();
    }

    public function test_series_page_has_a_distinct_confirmation_for_each_meeting_without_mutating_data(): void
    {
        $series = $this->createSeries(['learning_session_name' => 'Sesi 1']);
        $response = $this->actingAs($this->admin)->get(route('admin.lesson-series.show', $series))->assertOk()
            ->assertSee('Hapus pertemuan ini')->assertSee('data-confirm-title="Hapus pertemuan ini?"', false)
            ->assertSee('Teacher Utama')->assertSee('Kelas A')->assertSee('Sesi 1')->assertSee('02 November 2026')->assertSee('10:00–11:30');
        $preview = $response->viewData('deletion');
        $this->assertCount(3, $preview['meeting_versions']);
        $this->assertCount(3, array_unique($preview['meeting_versions']));
        foreach ($series->sessions()->get() as $session) {
            $response->assertSee(route('admin.lesson-series.destroy-meeting', [$series, $session, 'page' => 1]), false);
            $this->assertSame(64, strlen($preview['meeting_versions'][$session->id]));
            $this->assertNotSame($preview['version'], $preview['meeting_versions'][$session->id]);
        }
        $this->assertSame(3, $series->sessions()->count());
        $this->assertDatabaseCount('operational_deletion_events', 0);
    }

    #[DataProvider('protectedRecords')]
    public function test_single_meeting_deletion_preserves_every_protected_record(string $type): void
    {
        $series = $this->createSeries();
        $target = $series->sessions()->orderBy('planned_start_at')->get()[1];
        $this->protect($target, $type);
        $before = $series->sessions()->orderBy('id')->get()->map(fn ($s) => $s->getRawOriginal())->all();
        $this->actingAs($this->admin)->from(route('admin.lesson-series.show', $series))
            ->delete(route('admin.lesson-series.destroy-meeting', [$series, $target]), ['deletion_version' => $this->singleDeletionVersion($series, $target)])
            ->assertRedirect(route('admin.lesson-series.show', $series))->assertSessionHasErrors('delete_meeting');
        $this->assertSame($before, $series->sessions()->orderBy('id')->get()->map(fn ($s) => $s->getRawOriginal())->all());
        $this->assertDatabaseCount('operational_deletion_events', 0);
        $this->get(route('admin.lesson-series.show', $series))->assertOk()->assertSee('Pertemuan tidak dapat dihapus')->assertSee('Jadwal tetap tersimpan.');
    }

    public function test_a_recorded_sibling_does_not_block_deleting_an_unrecorded_meeting(): void
    {
        $series = $this->createSeries();
        [$target, $recorded] = $series->sessions()->orderBy('planned_start_at')->get()->all();
        $version = $this->singleDeletionVersion($series, $target);
        $this->protect($recorded, 'student_attendance');
        $recorded->update(['topic' => 'Catatan jadwal lain']);
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy-meeting', [$series, $target]), ['deletion_version' => $version])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('lesson_sessions', ['id' => $target->id]);
        $this->assertDatabaseHas('lesson_sessions', ['id' => $recorded->id, 'topic' => 'Catatan jadwal lain']);
        $this->assertDatabaseHas('session_students', ['session_id' => $recorded->id, 'attendance_status' => 'present']);
        $this->assertSame(2, $series->sessions()->count());
    }

    public function test_one_meeting_deletion_audit_preserves_participants_and_leaves_class_memberships_and_history_intact(): void
    {
        $series = $this->createSeries();
        [$target, $sibling] = $series->sessions()->orderBy('planned_start_at')->get()->all();
        SessionStudent::create(['session_id' => $target->id, 'student_id' => $this->student->id]);
        SessionStudent::create(['session_id' => $sibling->id, 'student_id' => $this->student->id]);
        app(ClassLearningSessionService::class)->select(ClassGroupMembership::firstOrFail(), [$series->class_learning_session_id], '2026-10-23');
        $selectedBefore = DB::table('class_learning_session_enrollments')->get()->toJson();
        $groupBefore = $this->group->fresh()->getRawOriginal();
        $change = LessonScheduleSeriesChange::create(['id' => (string) Str::uuid(), 'series_id' => $series->id, 'actor_user_id' => $this->admin->id, 'request_key' => (string) Str::uuid(), 'before_snapshot' => [['id' => $target->id]], 'after_snapshot' => [['id' => $target->id]], 'created_at' => now()]);
        app(LessonSeriesDeletionService::class)->deleteMeeting($this->admin, $series, $target, $this->singleDeletionVersion($series, $target));
        $event = DB::table('operational_deletion_events')->where('record_table', 'lesson_sessions')->where('record_id', $target->id)->first();
        $this->assertNotNull($event);
        $this->assertSame($this->admin->id, $event->actor_user_id);
        $snapshot = json_decode($event->before_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($series->id, $snapshot['series']['id']);
        $this->assertSame([$target->id], array_column($snapshot['sessions'], 'id'));
        $this->assertSame($this->student->id, $snapshot['participants'][0]['student_id']);
        $this->assertDatabaseMissing('session_students', ['session_id' => $target->id]);
        $this->assertDatabaseHas('session_students', ['session_id' => $sibling->id, 'student_id' => $this->student->id]);
        $this->assertDatabaseHas('lesson_schedule_series_changes', ['id' => $change->id]);
        $this->assertDatabaseHas('class_group_memberships', ['student_id' => $this->student->id, 'class_group_id' => $this->group->id]);
        $this->assertSame($groupBefore, $this->group->fresh()->getRawOriginal());
        $this->assertSame($selectedBefore, DB::table('class_learning_session_enrollments')->get()->toJson());
    }

    public function test_attendance_added_after_a_single_meeting_confirmation_is_rechecked(): void
    {
        $series = $this->createSeries();
        $target = $series->sessions()->firstOrFail();
        $version = $this->singleDeletionVersion($series, $target);
        $this->protect($target, 'student_attendance');
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy-meeting', [$series, $target]), ['deletion_version' => $version])->assertSessionHasErrors('delete_meeting');
        $this->assertSame(3, $series->sessions()->count());
        $this->assertDatabaseHas('session_students', ['session_id' => $target->id, 'attendance_status' => 'present']);
    }

    public function test_an_edited_meeting_invalidates_its_single_deletion_confirmation(): void
    {
        $series = $this->createSeries();
        $target = $series->sessions()->firstOrFail();
        $version = $this->singleDeletionVersion($series, $target);
        $target->update(['topic' => 'Diubah setelah halaman dibuka']);
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy-meeting', [$series, $target]), ['deletion_version' => $version])->assertSessionHasErrors('deletion_version');
        $this->assertSame(3, $series->sessions()->count());
        $this->assertDatabaseCount('operational_deletion_events', 0);
    }

    public function test_a_version_for_a_different_meeting_cannot_delete_the_target(): void
    {
        $series = $this->createSeries();
        [$one, $two] = $series->sessions()->get()->all();
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy-meeting', [$series, $two]), ['deletion_version' => $this->singleDeletionVersion($series, $one)])->assertSessionHasErrors('deletion_version');
        $this->assertSame(3, $series->sessions()->count());
        $this->assertDatabaseCount('operational_deletion_events', 0);
    }

    public function test_single_deletion_rejects_a_meeting_from_another_series(): void
    {
        $series = $this->createSeries();
        $another = $this->createSeries(['starts_at' => '13:00', 'ends_at' => '14:00']);
        $foreign = $another->sessions()->firstOrFail();
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy-meeting', [$series, $foreign]), ['deletion_version' => $this->singleDeletionVersion($another, $foreign)])->assertNotFound();
        $this->assertSame(3, $another->sessions()->count());
        $this->assertSame(3, $series->sessions()->count());
    }

    public function test_single_deletion_rejects_a_foreign_branch_even_with_a_valid_confirmation(): void
    {
        $teacher = $this->teacher('Guru luar');
        $this->assign($teacher, $this->otherBranch);
        $series = $this->createSeries(['branch_id' => $this->otherBranch->id, 'teacher_id' => $teacher->id, 'class_group_id' => $this->otherGroup->id], $this->superadmin);
        $target = $series->sessions()->firstOrFail();
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy-meeting', [$series, $target]), ['deletion_version' => $this->singleDeletionVersion($series, $target, $this->superadmin)])->assertForbidden();
        $this->assertSame(3, $series->sessions()->count());
    }

    #[DataProvider('forbiddenDeletingRoles')]
    public function test_teachers_and_students_cannot_delete_single_meetings(string $role): void
    {
        $series = $this->createSeries();
        $target = $series->sessions()->firstOrFail();
        $this->actingAs($this->user($role))->delete(route('admin.lesson-series.destroy-meeting', [$series, $target]), ['deletion_version' => $this->singleDeletionVersion($series, $target)])->assertForbidden();
        $this->assertSame(3, $series->sessions()->count());
    }

    public function test_single_meeting_delete_requires_authentication_and_an_explicit_confirmation(): void
    {
        $series = $this->createSeries();
        $target = $series->sessions()->firstOrFail();
        $url = route('admin.lesson-series.destroy-meeting', [$series, $target]);
        $this->delete($url)->assertRedirect(route('login'));
        $this->actingAs($this->admin)->delete($url)->assertSessionHasErrors('deletion_version');
        $this->get($url)->assertStatus(405);
        $this->assertSame(3, $series->sessions()->count());
    }

    public function test_single_meeting_delete_rechecks_the_current_actor(): void
    {
        $series = $this->createSeries();
        $target = $series->sessions()->firstOrFail();
        $version = $this->singleDeletionVersion($series, $target);
        DB::table('users')->where('id', $this->admin->id)->update(['is_active' => false]);
        try {
            app(LessonSeriesDeletionService::class)->deleteMeeting($this->admin, $series, $target, $version);
            $this->fail('Inactive actor must be rejected');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertSame(3, $series->sessions()->count());
    }

    public function test_expired_branch_assignment_cannot_delete_one_meeting(): void
    {
        $series = $this->createSeries();
        $target = $series->sessions()->firstOrFail();
        $version = $this->singleDeletionVersion($series, $target);
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-10-22']);
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy-meeting', [$series, $target]), ['deletion_version' => $version])->assertForbidden();
        $this->assertSame(3, $series->sessions()->count());
    }

    public function test_deleting_one_meeting_invalidates_an_older_whole_series_confirmation(): void
    {
        $series = $this->createSeries();
        $target = $series->sessions()->firstOrFail();
        $wholeVersion = $this->deletionVersion($series);
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy-meeting', [$series, $target]), ['deletion_version' => $this->singleDeletionVersion($series, $target)])->assertSessionHasNoErrors();
        $this->delete(route('admin.lesson-series.destroy', $series), ['deletion_version' => $wholeVersion])->assertSessionHasErrors('deletion_version');
        $this->assertSame(2, $series->sessions()->count());
        $this->assertDatabaseCount('operational_deletion_events', 1);
    }

    public function test_replaying_an_original_creation_preview_does_not_restore_a_deleted_meeting(): void
    {
        $preview = $this->preview();
        $series = $this->service()->create($this->admin, ['preview_token' => $preview['previewToken']]);
        $target = $series->sessions()->orderBy('planned_start_at')->get()[1];
        app(LessonSeriesDeletionService::class)->deleteMeeting($this->admin, $series, $target, $this->singleDeletionVersion($series, $target));
        $retry = $this->service()->create($this->admin, ['preview_token' => $preview['previewToken']]);
        $this->assertSame($series->id, $retry->id);
        $this->assertSame(2, $retry->sessions()->count());
        $this->assertDatabaseMissing('lesson_sessions', ['schedule_series_id' => $series->id, 'series_occurrence_on' => '2026-11-02']);
    }

    public function test_a_stale_bulk_edit_cannot_overwrite_a_single_deletion_and_a_fresh_edit_keeps_the_gap(): void
    {
        $series = $this->createSeries();
        [$anchor, $target] = $series->sessions()->orderBy('planned_start_at')->get()->all();
        $preview = $this->service()->previewFuture($this->admin, $series, $anchor, $this->futureData($anchor));
        app(LessonSeriesDeletionService::class)->deleteMeeting($this->admin, $series, $target, $this->singleDeletionVersion($series, $target));
        $this->invalid(fn () => $this->service()->updateFuture($this->admin, $series, $anchor, ['preview_token' => $preview['previewToken']]), 'preview_token');
        $fresh = $this->service()->previewFuture($this->admin, $series, $anchor, $this->futureData($anchor));
        $this->service()->updateFuture($this->admin, $series, $anchor, ['preview_token' => $fresh['previewToken']]);
        $this->assertSame(2, $series->sessions()->count());
        $this->assertDatabaseMissing('lesson_sessions', ['schedule_series_id' => $series->id, 'series_occurrence_on' => '2026-11-02']);
        $this->assertSame(2, $series->sessions()->whereTime('planned_start_at', '13:00:00')->count());
    }

    public function test_a_repeated_single_delete_returns_not_found_and_only_one_audit_event(): void
    {
        $series = $this->createSeries();
        $target = $series->sessions()->firstOrFail();
        $version = $this->singleDeletionVersion($series, $target);
        $url = route('admin.lesson-series.destroy-meeting', [$series, $target]);
        $this->actingAs($this->admin)->delete($url, ['deletion_version' => $version])->assertSessionHasNoErrors();
        $this->delete($url, ['deletion_version' => $version])->assertNotFound();
        $this->assertSame(2, $series->sessions()->count());
        $this->assertDatabaseCount('operational_deletion_events', 1);
    }

    public function test_past_and_cancelled_unrecorded_meetings_can_be_deleted_individually(): void
    {
        $series = $this->createSeries();
        [$past, $cancelled] = $series->sessions()->orderBy('planned_start_at')->get()->all();
        $cancelled->update(['status' => 'cancelled']);
        Carbon::setTestNow(Carbon::parse('2026-12-01', 'Asia/Makassar'));
        foreach ([$past, $cancelled] as $target) {
            $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy-meeting', [$series, $target]), ['deletion_version' => $this->singleDeletionVersion($series, $target)])->assertSessionHasNoErrors();
        }
        $this->assertSame(1, $series->sessions()->count());
        $this->assertDatabaseCount('operational_deletion_events', 2);
    }

    public function test_deleting_the_final_meeting_keeps_the_series_history_and_shows_an_empty_state(): void
    {
        $series = $this->createSeries(['ends_on' => '2026-10-26']);
        $target = $series->sessions()->firstOrFail();
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy-meeting', [$series, $target]), ['deletion_version' => $this->singleDeletionVersion($series, $target)])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lesson_schedule_series', ['id' => $series->id]);
        $this->get(route('admin.lesson-series.show', $series))->assertOk()->assertSee('Tidak ada pertemuan tersisa dalam seri ini.')
            ->assertViewHas('deletion', fn ($preview) => $preview['count'] === 0 && $preview['meeting_versions'] === []);
    }

    public static function deletionPages(): array
    {
        return [['2026-11-25', 31, 1], ['2026-11-30', 36, 2]];
    }

    #[DataProvider('deletionPages')]
    public function test_deletion_from_the_second_page_returns_to_an_existing_page(string $end, int $total, int $expectedPage): void
    {
        $series = $this->createSeries(['ends_on' => $end, 'weekdays' => [1, 2, 3, 4, 5, 6, 7]]);
        $this->assertSame($total, $series->sessions()->count());
        $target = $series->sessions()->orderByDesc('planned_start_at')->firstOrFail();
        $this->actingAs($this->admin)->delete(route('admin.lesson-series.destroy-meeting', [$series, $target, 'page' => 2]), ['deletion_version' => $this->singleDeletionVersion($series, $target)])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.lesson-series.show', [$series, 'page' => $expectedPage]).'#series-meetings');
        $this->assertSame($total - 1, $series->sessions()->count());
        $this->get(route('admin.lesson-series.show', [$series, 'page' => $expectedPage]))->assertOk()
            ->assertViewHas('sessions', fn ($rows) => $rows->isNotEmpty());
    }
}
