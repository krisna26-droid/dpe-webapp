<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LessonLearningAudit;
use App\Models\LessonScheduleSeries;
use App\Models\LessonScheduleSeriesChange;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherAvailability;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\BranchOperationsService;
use App\Services\LessonSeriesService;
use App\Services\LessonSessionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LessonSeriesWorkflowTest extends TestCase
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

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

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

    private function service(): LessonSeriesService { return app(LessonSeriesService::class); }
    private function preview(array $extra = [], ?User $actor = null): array { return $this->service()->previewCreate($actor ?? $this->admin, $this->data($extra)); }
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
        try { $callback(); $this->fail('Expected validation error for '.$field); }
        catch (ValidationException $error) { $this->assertArrayHasKey($field, $error->errors()); }
    }

    private function futureData(LessonSession $anchor, array $extra = []): array
    {
        return $extra + ['teacher_id' => $anchor->teacher_id, 'planned_start_at' => $anchor->planned_start_at->format('Y-m-d').'T13:00',
            'planned_end_at' => $anchor->planned_start_at->format('Y-m-d').'T14:30', 'status' => 'scheduled',
            'topic' => 'Jadwal baru', 'material' => null, 'activity' => null];
    }

    public function test_admin_and_superadmin_can_open_modes_without_creating_records(): void
    {
        foreach (['admin' => $this->admin, 'superadmin' => $this->superadmin] as $prefix => $actor) {
            $this->actingAs($actor)->get(route($prefix.'.lesson-sessions.index'))->assertOk()
                ->assertSee(route($prefix.'.lesson-sessions.create'))->assertDontSee(route($prefix.'.lesson-series.create'));
            $this->get(route($prefix.'.lesson-sessions.create'))->assertOk()->assertSee('Sekali pertemuan')->assertSee('Berulang mingguan');
            $page = $this->get(route($prefix.'.lesson-series.create'))->assertOk()->assertSee('Hari berulang')->assertSee('branch-operations.js');
            if ($prefix === 'admin') { $page->assertDontSee('Cabang Luar Rahasia')->assertDontSee('Kelas Luar Rahasia'); }
            else { $page->assertSee('Cabang Luar Rahasia'); }
        }
        $this->assertDatabaseCount('lesson_schedule_series', 0);
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public function test_preview_generates_real_weekdays_across_months_without_business_mutation(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.lesson-series.preview'), $this->data([
            'starts_on' => '2026-11-02', 'ends_on' => '2026-11-30', 'weekdays' => [1, 3]]))->assertOk();
        $this->assertSame(['2026-11-02', '2026-11-04', '2026-11-09', '2026-11-11', '2026-11-16',
            '2026-11-18', '2026-11-23', '2026-11-25', '2026-11-30'], array_column($response->viewData('rows'), 'date'));
        $response->assertSee('9 pertemuan dipilih')->assertSee('Senin, 02 November 2026')->assertSee('10:00–11:30');
        $this->get(route('admin.lesson-series.create', ['draft' => $response->viewData('formDraftId')]))->assertOk()->assertSee('value="2026-11-02"', false)->assertSee('value="10:00"', false);
        $this->assertDatabaseCount('lesson_sessions', 0);
        $this->assertDatabaseCount('lesson_schedule_series', 0);
        $this->assertDatabaseCount('session_students', 0);
        Http::assertNothingSent();
    }

    public static function schedulingRoles(): array { return [['admin'], ['superadmin']]; }

    #[DataProvider('schedulingRoles')]
    public function test_back_links_restore_all_form_fields_after_other_requests_and_repeated_visits(string $prefix): void
    {
        $actor = $prefix === 'admin' ? $this->admin : $this->superadmin;
        $input = $this->data(['weekdays' => [1, 3], 'teaching_location' => 'student_home', 'topic' => 'Topik & latihan',
            'material' => 'Buku 2', 'activity' => 'Percakapan']);
        $page = $this->actingAs($actor)->post(route($prefix.'.lesson-series.preview'), $input)->assertOk();
        $draftId = $page->viewData('formDraftId');
        $url = route($prefix.'.lesson-series.create', ['draft' => $draftId]);
        $dom = new \DOMDocument(); @$dom->loadHTML($page->getContent()); $xpath = new \DOMXPath($dom);
        foreach (['Kembali ke form', 'Kembali'] as $label) {
            $links = $xpath->query('//a[normalize-space(.)="'.$label.'"]');
            $this->assertSame(1, $links->length);
            $this->assertSame($url, $links->item(0)->getAttribute('href'));
        }
        $page->assertDontSee('Batal / kembali')->assertSee(route($prefix.'.lesson-series.cancel'));
        for ($i = 0; $i < 2; $i++) { $this->get(route($prefix.'.dashboard'))->assertOk(); }
        foreach ([1, 2] as $visit) {
            $form = $this->get($url)->assertOk()->assertViewHas('formValues', $input)->assertViewHas('formDraftId', $draftId);
            $form->assertSee('value="10:00"', false)->assertSee('value="11:30"', false)
                ->assertSee('value="Topik &amp; latihan"', false)->assertSee('value="Buku 2"', false)->assertSee('value="Percakapan"', false);
        }
        $this->assertDatabaseCount('lesson_schedule_series', 0);
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    #[DataProvider('schedulingRoles')]
    public function test_cancel_returns_initial_form_and_removes_only_that_preview_draft(string $prefix): void
    {
        $actor = $prefix === 'admin' ? $this->admin : $this->superadmin;
        $first = $this->actingAs($actor)->post(route($prefix.'.lesson-series.preview'), $this->data(['topic' => 'Isian pertama']))->assertOk();
        $second = $this->post(route($prefix.'.lesson-series.preview'), $this->data(['topic' => 'Isian kedua']))->assertOk();
        $one = $first->viewData('formDraftId'); $two = $second->viewData('formDraftId');
        $this->assertNotSame($one, $two);
        $reset = route($prefix.'.lesson-series.create', ['reset' => 1]);
        $this->post(route($prefix.'.lesson-series.cancel'), ['draft_id' => $one])->assertRedirect($reset);
        $form = $this->get($reset)->assertOk()->assertViewHas('formValues', [])->assertViewHas('formDraftId', null);
        $form->assertDontSee('Isian pertama')->assertSee('value=""', false);
        $dom = new \DOMDocument(); @$dom->loadHTML($form->getContent()); $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//input[@name="weekdays[]" and @checked]')->length);
        foreach (['teacher_id', 'program_id', 'class_group_id'] as $field) {
            $this->assertSame(0, $xpath->query('//select[@name="'.$field.'"]/option[@selected and @value!=""]')->length);
        }
        $this->get(route($prefix.'.lesson-series.create', ['draft' => $one]))->assertNotFound();
        $this->get(route($prefix.'.lesson-series.create', ['draft' => $two]))->assertOk()->assertSee('Isian kedua');
        $this->assertDatabaseCount('lesson_schedule_series', 0);
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    #[DataProvider('schedulingRoles')]
    public function test_refresh_and_repreview_keep_one_draft_and_updated_values(string $prefix): void
    {
        $actor = $prefix === 'admin' ? $this->admin : $this->superadmin;
        $first = $this->actingAs($actor)->post(route($prefix.'.lesson-series.preview'), $this->data())->assertOk();
        $id = $first->viewData('formDraftId');
        $refresh = $this->post(route($prefix.'.lesson-series.preview'), ['draft_id' => $id,
            'preview_token' => $first->viewData('previewToken'), 'excluded_dates' => ['2026-11-02']])->assertOk()->assertViewHas('formDraftId', $id);
        $this->get(route($prefix.'.lesson-series.create', ['draft' => $id]))->assertOk()->assertSee('value="10:00"', false);
        $updated = $this->post(route($prefix.'.lesson-series.preview'), $this->data(['draft_id' => $id, 'starts_at' => '12:00', 'ends_at' => '13:30', 'topic' => 'Isian diperbarui']))
            ->assertOk()->assertViewHas('formDraftId', $id);
        $this->get(route($prefix.'.lesson-series.create', ['draft' => $id]))->assertOk()->assertSee('value="12:00"', false)->assertSee('Isian diperbarui');
        $this->post(route($prefix.'.lesson-series.store'), ['draft_id' => $id, 'preview_token' => $updated->viewData('previewToken')])->assertRedirect();
        $this->assertDatabaseCount('lesson_sessions', 3);
    }

    public function test_invalid_form_after_back_retains_new_attempted_values_and_existing_draft(): void
    {
        $preview = $this->actingAs($this->admin)->post(route('admin.lesson-series.preview'), $this->data())->assertOk();
        $id = $preview->viewData('formDraftId');
        $url = route('admin.lesson-series.create', ['draft' => $id]);
        $this->post(route('admin.lesson-series.preview'), $this->data(['draft_id' => $id, 'ends_at' => '09:00', 'topic' => 'Koreksi terbaru']))
            ->assertRedirect($url)->assertSessionHasErrors('ends_at');
        $this->get($url)->assertOk()->assertSee('value="09:00"', false)->assertSee('Koreksi terbaru');
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public function test_draft_is_bound_to_account_role_prefix_and_operation(): void
    {
        $preview = $this->actingAs($this->admin)->post(route('admin.lesson-series.preview'), $this->data())->assertOk();
        $id = $preview->viewData('formDraftId');
        $this->actingAs($this->superadmin)->get(route('superadmin.lesson-series.create', ['draft' => $id]))->assertNotFound();
        $this->post(route('superadmin.lesson-series.cancel'), ['draft_id' => $id])->assertNotFound();
        $this->actingAs($this->teacher->user)->post(route('admin.lesson-series.cancel'), ['draft_id' => $id])->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.lesson-series.create', ['draft' => $id]))->assertOk();
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public function test_cancel_requires_csrf_and_keeps_draft_when_request_is_rejected(): void
    {
        $preview = $this->actingAs($this->admin)->post(route('admin.lesson-series.preview'), $this->data())->assertOk();
        $id = $preview->viewData('formDraftId');
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
                protected function runningUnitTests() { return false; }
            };
        });
        $this->withSession(['_token' => str_repeat('x', 40)]);
        $this->post(route('admin.lesson-series.cancel'), ['draft_id' => $id])->assertStatus(419);
        $this->get(route('admin.lesson-series.create', ['draft' => $id]))->assertOk()->assertSee('value="10:00"', false);
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public function test_future_preview_back_keeps_edits_and_cancel_restores_saved_session_defaults(): void
    {
        $series = $this->createSeries();
        $anchor = $series->sessions()->orderBy('planned_start_at')->first();
        $data = $this->futureData($anchor, ['topic' => null, 'material' => 'Materi baru']);
        $preview = $this->actingAs($this->admin)->post(route('admin.lesson-series.preview-future', [$series, $anchor]), $data)->assertOk();
        $id = $preview->viewData('formDraftId');
        $this->get(route('admin.dashboard'))->assertOk();
        $back = route('admin.lesson-series.edit', [$series, $anchor, 'draft' => $id]);
        $this->get($back)->assertOk()->assertViewHas('formValues', $data)->assertSee('value="2026-10-26T13:00"', false)->assertSee('value="Materi baru"', false)
            ->assertSee('id="series-topic" name="topic" maxlength="255" value=""', false);
        $reset = route('admin.lesson-series.edit', [$series, $anchor, 'reset' => 1]);
        $this->post(route('admin.lesson-series.cancel'), ['draft_id' => $id])->assertRedirect($reset);
        $this->get($reset)->assertOk()->assertViewHas('formValues', [])->assertSee('value="2026-10-26T10:00"', false)->assertSee('Percakapan awal')->assertDontSee('Materi baru');
        $this->assertSame('10:00', $anchor->fresh()->planned_start_at->format('H:i'));
        $this->assertDatabaseCount('lesson_schedule_series_changes', 0);
    }

    public function test_three_month_series_crosses_year_and_keeps_independent_sessions_and_exclusions(): void
    {
        $series = $this->createSeries(['starts_on' => '2026-11-01', 'ends_on' => '2027-01-31', 'excluded_dates' => ['2026-12-28']]);
        $sessions = $series->sessions()->orderBy('planned_start_at')->get();
        $this->assertCount(12, $sessions);
        $this->assertSame('2026-11-02 10:00:00', $sessions->first()->getRawOriginal('planned_start_at'));
        $this->assertSame('2027-01-25 10:00:00', $sessions->last()->getRawOriginal('planned_start_at'));
        $this->assertSame(12, $sessions->pluck('id')->unique()->count());
        $this->assertSame(12, $sessions->pluck('series_occurrence_on')->unique()->count());
        $this->assertFalse($sessions->contains(fn ($s) => $s->planned_start_at->toDateString() === '2026-12-28'));
        $this->assertSame(['2026-12-28'], $series->definition['excluded_dates']);
        $this->assertSame($this->admin->id, $series->created_by_user_id);
        $this->assertTrue($sessions->every(fn ($s) => $s->status === 'scheduled' && $s->class_group_id === $this->group->id && $s->actual_start_at === null));
        $this->assertDatabaseCount('session_students', 0);
        $this->actingAs($this->admin)->get(route('admin.lesson-series.show', $series))->assertOk()->assertSee('12 pertemuan')->assertSee('Admin Utama');
    }

    public function test_store_requires_preview_and_does_not_trust_other_posted_schedule_fields(): void
    {
        $this->actingAs($this->admin)->post(route('admin.lesson-series.store'), $this->data())
            ->assertStatus(422)->assertSee('Buka pratinjau jadwal sebelum menyimpan.');
        $this->assertDatabaseCount('lesson_sessions', 0);
        $preview = $this->preview();
        $this->post(route('admin.lesson-series.store'), ['preview_token' => $preview['previewToken'],
            'branch_id' => $this->otherBranch->id, 'teacher_id' => $this->otherTeacher->id, 'status' => 'completed', 'starts_at' => '02:00'])
            ->assertRedirect();
        $this->assertDatabaseCount('lesson_sessions', 3);
        $this->assertDatabaseMissing('lesson_sessions', ['teacher_id' => $this->otherTeacher->id]);
        $this->assertDatabaseHas('lesson_sessions', ['planned_start_at' => '2026-10-26 10:00:00', 'status' => 'scheduled']);
    }

    public function test_preview_refresh_preserves_request_nonce_and_double_submit_does_not_duplicate(): void
    {
        $first = $this->preview();
        $second = $this->service()->previewCreate($this->admin, ['preview_token' => $first['previewToken'], 'excluded_dates' => ['2026-11-02']]);
        $payload = ['preview_token' => $second['previewToken'], 'excluded_dates' => ['2026-11-02']];
        $series = $this->service()->create($this->admin, $payload);
        $again = $this->service()->create($this->admin, $payload);
        $this->assertSame($series->id, $again->id);
        $this->assertDatabaseCount('lesson_schedule_series', 1);
        $this->assertDatabaseCount('lesson_sessions', 2);
        $this->invalid(fn () => $this->service()->create($this->admin, ['preview_token' => $first['previewToken']]), 'preview_token');
    }

    public static function invalidDefinitions(): array
    {
        return [
            [['weekdays' => []], 'weekdays'], [['weekdays' => [0]], 'weekdays.0'], [['weekdays' => [1, 1]], 'weekdays.0'],
            [['starts_on' => '2026-02-30'], 'starts_on'], [['ends_on' => '2026-10-01'], 'ends_on'],
            [['ends_on' => '2027-01-26'], 'ends_on'], [['starts_on' => '2026-10-22'], 'starts_on'],
            [['starts_at' => '12:00', 'ends_at' => '11:30'], 'ends_at'], [['starts_at' => '10:00', 'ends_at' => '10:00'], 'ends_at'],
            [['starts_on' => '2026-10-27', 'ends_on' => '2026-10-27', 'weekdays' => [1]], 'weekdays'],
            [['class_group_id' => null], 'class_group_id'], [['teaching_location' => 'invalid'], 'teaching_location'],
            [['excluded_dates' => ['2026-10-27']], 'excluded_dates.0'],
            [['excluded_dates' => ['2026-10-26', '2026-10-26']], 'excluded_dates.0'],
        ];
    }

    #[DataProvider('invalidDefinitions')]
    public function test_invalid_definition_is_rejected_without_partial_records(array $changes, string $field): void
    {
        $this->invalid(fn () => $this->preview($changes), $field);
        $this->assertDatabaseCount('lesson_schedule_series', 0);
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public function test_no_dates_selected_or_past_clock_cannot_be_saved(): void
    {
        $preview = $this->preview();
        $this->invalid(fn () => $this->service()->create($this->admin, ['preview_token' => $preview['previewToken'],
            'excluded_dates' => ['2026-10-26', '2026-11-02', '2026-11-09']]), 'schedule_dates');
        $today = $this->preview(['starts_on' => '2026-10-23', 'ends_on' => '2026-10-23', 'weekdays' => [5], 'starts_at' => '07:00', 'ends_at' => '08:30']);
        $this->assertNotEmpty($today['rows'][0]['issues']);
        $this->invalid(fn () => $this->service()->create($this->admin, ['preview_token' => $today['previewToken']]), 'schedule_dates');
        $this->assertDatabaseCount('lesson_schedule_series', 0);
    }

    public function test_conflict_shown_per_date_and_excluding_it_allows_remaining_dates(): void
    {
        $this->existing('2026-11-02');
        $preview = $this->preview();
        $this->assertEmpty($preview['rows'][0]['issues']);
        $this->assertNotEmpty($preview['rows'][1]['issues']);
        $this->actingAs($this->admin)->post(route('admin.lesson-series.store'), ['preview_token' => $preview['previewToken']])
            ->assertStatus(422)->assertSee('Belum dapat disimpan')->assertSee('bertabrakan');
        $this->assertDatabaseCount('lesson_schedule_series', 0);
        $this->assertDatabaseCount('lesson_sessions', 1);
        $series = $this->service()->create($this->admin, ['preview_token' => $preview['previewToken'], 'excluded_dates' => ['2026-11-02']]);
        $this->assertSame(2, $series->sessions()->count());
    }

    public function test_conflict_inserted_after_preview_is_rechecked_and_aborts_whole_create(): void
    {
        $preview = $this->preview();
        $this->existing('2026-11-09');
        $this->invalid(fn () => $this->service()->create($this->admin, ['preview_token' => $preview['previewToken']]), 'schedule_dates');
        $this->assertDatabaseCount('lesson_sessions', 1);
        $this->assertDatabaseCount('lesson_schedule_series', 0);
    }

    public function test_database_failure_after_first_insert_rolls_back_series_and_all_sessions(): void
    {
        $preview = $this->preview();
        DB::unprepared("CREATE TRIGGER reject_second_series_session BEFORE INSERT ON lesson_sessions WHEN NEW.planned_start_at = '2026-11-02 10:00:00' BEGIN SELECT RAISE(ABORT, 'fixture late insert failure'); END");
        try { $this->service()->create($this->admin, ['preview_token' => $preview['previewToken']]); $this->fail('Expected database failure'); }
        catch (QueryException $error) { $this->assertStringContainsString('fixture late insert failure', $error->getMessage()); }
        $this->assertDatabaseCount('lesson_schedule_series', 0);
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public function test_migration_rollback_refuses_to_discard_existing_schedule_series(): void
    {
        $series = $this->createSeries();
        $migration = require database_path('migrations/2026_10_23_010000_create_lesson_schedule_series.php');
        try { $migration->down(); $this->fail('Expected rollback refusal'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('Rollback ditolak', $error->getMessage()); }
        $this->assertSame(3, $series->sessions()->count());
        $this->assertDatabaseCount('lesson_schedule_series', 1);
    }

    public function test_teacher_overlap_is_checked_across_branches_and_class_overlap_across_teachers(): void
    {
        $this->existing('2026-10-26', extra: ['branch_id' => $this->otherBranch->id, 'class_group_id' => $this->otherGroup->id]);
        $this->assertStringContainsString('Teacher', implode(' ', $this->preview()['rows'][0]['issues']));
        LessonSession::query()->delete();
        $this->existing('2026-10-26', extra: ['teacher_id' => $this->otherTeacher->id]);
        $this->assertStringContainsString('Kelompok kelas', implode(' ', $this->preview()['rows'][0]['issues']));
    }

    public function test_same_teacher_can_teach_another_class_at_adjacent_time_and_cancelled_does_not_block(): void
    {
        $this->createSeries();
        $groupB = $this->group('Kelas B', $this->branch, $this->teacher);
        $series = $this->createSeries(['class_group_id' => $groupB->id, 'starts_at' => '11:30', 'ends_at' => '13:00']);
        $this->assertSame(3, $series->sessions()->count());
        $this->assertDatabaseCount('lesson_sessions', 6);
        $this->existing('2026-10-27', extra: ['status' => 'cancelled']);
        $this->assertEmpty($this->preview(['starts_on' => '2026-10-27', 'ends_on' => '2026-10-27', 'weekdays' => [2]])['rows'][0]['issues']);
    }

    public function test_superseded_original_does_not_block_new_schedule(): void
    {
        $source = $this->existing('2026-10-26');
        $this->existing('2026-10-27', extra: ['rescheduled_from_session_id' => $source->id]);
        $this->assertEmpty($this->preview()['rows'][0]['issues']);
    }

    public function test_availability_is_optional_but_blocked_or_uncovered_slots_are_rejected(): void
    {
        $this->assertEmpty($this->preview()['rows'][0]['issues']);
        $slot = TeacherAvailability::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id,
            'branch_id' => $this->branch->id, 'available_on' => '2026-10-26', 'starts_at' => '09:00:00', 'ends_at' => '12:00:00',
            'class_type' => 'Regular', 'availability_status' => 'Blocked']);
        $this->assertNotEmpty($this->preview()['rows'][0]['issues']);
        $slot->update(['availability_status' => 'Available', 'ends_at' => '11:00:00']);
        $this->assertNotEmpty($this->preview()['rows'][0]['issues']);
        $slot->update(['ends_at' => '12:00:00']);
        $this->assertEmpty($this->preview()['rows'][0]['issues']);
    }

    public function test_inactive_reference_and_mismatched_class_program_are_rejected(): void
    {
        $this->group->update(['is_active' => false]);
        $this->invalid(fn () => $this->preview(), 'class_group_id');
        $this->group->update(['is_active' => true]);
        $this->teacher->user->update(['is_active' => false]);
        $this->invalid(fn () => $this->preview(), 'teacher_id');
        $this->teacher->user->update(['is_active' => true]);
        $program = Program::create(['id' => (string) Str::uuid(), 'code' => 'OTHER', 'name' => 'Other', 'class_type' => 'private', 'is_active' => true]);
        $this->invalid(fn () => $this->preview(['program_id' => $program->id]), 'class_group_id');
        $this->invalid(fn () => $this->preview(['class_group_id' => $this->otherGroup->id]), 'class_group_id');
    }

    public function test_teacher_responsibility_is_checked_for_each_generated_date(): void
    {
        $this->group->update(['default_teacher_id' => null]);
        TeacherBranchAssignment::where('teacher_id', $this->teacher->id)->update(['ends_on' => '2026-10-31']);
        $preview = $this->preview();
        $this->assertEmpty($preview['rows'][0]['issues']);
        $this->assertNotEmpty($preview['rows'][1]['issues']);
        $this->invalid(fn () => $this->service()->create($this->admin, ['preview_token' => $preview['previewToken']]), 'schedule_dates');
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public function test_branch_scope_and_teacher_role_are_rechecked_at_store(): void
    {
        $preview = $this->preview();
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-10-22']);
        $this->actingAs($this->admin)->post(route('admin.lesson-series.store'), ['preview_token' => $preview['previewToken']])->assertForbidden();
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public function test_teacher_deactivation_after_preview_prevents_creation(): void
    {
        $preview = $this->preview();
        $this->teacher->update(['is_active' => false]);
        $this->invalid(fn () => $this->service()->create($this->admin, ['preview_token' => $preview['previewToken']]), 'teacher_id');
        $this->assertDatabaseCount('lesson_schedule_series', 0);
    }

    public function test_preview_token_is_bound_to_actor_and_expires(): void
    {
        $preview = $this->preview();
        $this->invalid(fn () => $this->service()->create($this->superadmin, ['preview_token' => $preview['previewToken']]), 'preview_token');
        $this->invalid(fn () => $this->service()->create($this->admin, ['preview_token' => $preview['previewToken'].'broken']), 'preview_token');
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:01:00', 'Asia/Makassar'));
        $this->invalid(fn () => $this->service()->create($this->admin, ['preview_token' => $preview['previewToken']]), 'preview_token');
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public static function prohibitedRoles(): array { return [['teacher'], ['student'], ['inactive-admin']]; }

    #[DataProvider('prohibitedRoles')]
    public function test_other_roles_cannot_open_preview_or_store_series(string $role): void
    {
        $actor = $this->user($role === 'inactive-admin' ? 'admin' : $role);
        if ($role === 'inactive-admin') { $actor->update(['is_active' => false]); }
        $this->actingAs($actor)->get(route('admin.lesson-series.create'))->assertForbidden();
        $this->post(route('admin.lesson-series.preview'), $this->data())->assertForbidden();
        $this->post(route('admin.lesson-series.store'), $this->data())->assertForbidden();
        $this->get(route('superadmin.lesson-series.create'))->assertForbidden();
        $this->assertDatabaseCount('lesson_sessions', 0);
    }

    public function test_guest_requires_login_and_admin_cannot_access_foreign_series_or_superadmin_routes(): void
    {
        $this->get(route('admin.lesson-series.create'))->assertRedirect(route('login'));
        $this->post(route('admin.lesson-series.preview'), $this->data())->assertRedirect(route('login'));
        $this->assign($this->teacher, $this->otherBranch, ['ends_on' => '2027-12-31']);
        $foreign = $this->createSeries(['branch_id' => $this->otherBranch->id, 'class_group_id' => $this->otherGroup->id], $this->superadmin);
        $this->actingAs($this->admin)->get(route('admin.lesson-series.show', $foreign))->assertForbidden();
        $anchor = $foreign->sessions()->first();
        $this->get(route('admin.lesson-series.edit', [$foreign, $anchor]))->assertForbidden();
        $this->post(route('admin.lesson-series.preview-future', [$foreign, $anchor]), $this->futureData($anchor))->assertForbidden();
        $this->post(route('admin.lesson-series.preview'), $this->data(['branch_id' => $this->otherBranch->id]))->assertForbidden();
        $this->get(route('superadmin.lesson-series.create'))->assertForbidden();
        $this->actingAs($this->superadmin)->get(route('superadmin.lesson-series.show', $foreign))->assertOk();
    }

    public function test_one_meeting_edit_changes_only_that_session_and_keeps_series_class_fixed(): void
    {
        $series = $this->createSeries();
        $sessions = $series->sessions()->orderBy('planned_start_at')->get();
        $session = $sessions[1];
        $data = $session->only(['branch_id', 'teacher_id', 'program_id', 'class_group_id', 'teaching_location', 'status', 'topic', 'material', 'activity'])
            + ['planned_start_at' => '2026-11-02T14:00', 'planned_end_at' => '2026-11-02T15:30',
                'record_version' => app(BranchOperationsService::class)->version($session)];
        $this->actingAs($this->admin)->get(route('admin.lesson-sessions.edit', $session))->assertOk()->assertSee('pertemuan ini saja')->assertSee('disabled', false);
        $this->put(route('admin.lesson-sessions.update', $session), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('2026-11-02 14:00:00', $session->fresh()->getRawOriginal('planned_start_at'));
        $this->assertSame('2026-10-26 10:00:00', $sessions[0]->fresh()->getRawOriginal('planned_start_at'));
        $this->assertSame('2026-11-09 10:00:00', $sessions[2]->fresh()->getRawOriginal('planned_start_at'));
        $groupB = $this->group('Kelas B', $this->branch, $this->teacher);
        $this->invalid(fn () => app(LessonSessionService::class)->update($session, array_replace($data, ['class_group_id' => $groupB->id])), 'class_group_id');
        $this->assertSame('2026-11-02', substr((string) $session->fresh()->series_occurrence_on, 0, 10));
    }

    public function test_future_change_moves_only_anchor_and_later_dates_and_keeps_original_ids_and_occurrences(): void
    {
        $series = $this->createSeries();
        $sessions = $series->sessions()->orderBy('planned_start_at')->get();
        $anchor = $sessions[1];
        $input = $this->futureData($anchor, ['teacher_id' => $this->otherTeacher->id,
            'planned_start_at' => '2026-11-03T13:00', 'planned_end_at' => '2026-11-03T14:30']);
        $preview = $this->actingAs($this->admin)->post(route('admin.lesson-series.preview-future', [$series, $anchor]), $input)->assertOk();
        $this->assertSame(['2026-11-03', '2026-11-10'], array_column($preview->viewData('rows'), 'date'));
        $this->post(route('admin.lesson-series.update-future', [$series, $anchor]), ['preview_token' => $preview->viewData('previewToken')])
            ->assertRedirect(route('admin.lesson-series.show', $series));
        $this->assertSame('2026-10-26 10:00:00', $sessions[0]->fresh()->getRawOriginal('planned_start_at'));
        $this->assertSame('2026-11-03 13:00:00', $anchor->fresh()->getRawOriginal('planned_start_at'));
        $this->assertSame('2026-11-10 13:00:00', $sessions[2]->fresh()->getRawOriginal('planned_start_at'));
        $this->assertSame($this->otherTeacher->id, $anchor->fresh()->teacher_id);
        $this->assertEqualsCanonicalizing($sessions->pluck('id')->all(), $series->sessions()->pluck('id')->all());
        $this->assertSame('2026-11-02', substr((string) $anchor->fresh()->series_occurrence_on, 0, 10));
        $change = LessonScheduleSeriesChange::firstOrFail();
        $this->assertSame($this->admin->id, $change->actor_user_id);
        $this->assertCount(2, $change->before_snapshot);
        $this->assertCount(2, $change->after_snapshot);
    }

    public function test_future_change_preserves_absence_notes_teacher_attendance_cancelled_and_replacements(): void
    {
        $series = $this->createSeries(['ends_on' => '2026-12-07']);
        $sessions = $series->sessions()->orderBy('planned_start_at')->get();
        SessionStudent::create(['session_id' => $sessions[1]->id, 'student_id' => $this->student->id, 'attendance_status' => 'present', 'recorded_at' => now()]);
        SessionStudent::create(['session_id' => $sessions[2]->id, 'student_id' => $this->student->id, 'individual_learning_note' => 'Catatan tetap']);
        TeacherAttendance::create(['id' => (string) Str::uuid(), 'session_id' => $sessions[3]->id,
            'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id, 'status' => 'approved']);
        $sessions[4]->update(['status' => 'cancelled']);
        $this->existing('2026-11-25', extra: ['rescheduled_from_session_id' => $sessions[5]->id]);
        $before = $sessions->mapWithKeys(fn ($s) => [$s->id => $s->fresh()->getRawOriginal()]);
        $preview = $this->service()->previewFuture($this->admin, $series, $sessions[0], $this->futureData($sessions[0]));
        $this->assertSame(5, collect($preview['rows'])->filter(fn ($r) => $r['preserved'])->count());
        foreach ($preview['rows'] as $row) {
            if ($row['preserved']) { $this->assertSame($before[$row['session']->id]['planned_start_at'], (string) $row['data']['planned_start_at']); }
        }
        $this->service()->updateFuture($this->admin, $series, $sessions[0], ['preview_token' => $preview['previewToken']]);
        foreach ([1, 2, 3, 4, 5] as $index) { $this->assertSame($before[$sessions[$index]->id], $sessions[$index]->fresh()->getRawOriginal()); }
        $this->assertSame('13:00', $sessions[0]->fresh()->planned_start_at->format('H:i'));
        $this->assertSame('13:00', $sessions[6]->fresh()->planned_start_at->format('H:i'));
        $this->assertDatabaseHas('session_students', ['session_id' => $sessions[2]->id, 'individual_learning_note' => 'Catatan tetap']);
    }

    public function test_learning_audit_and_past_or_ongoing_anchor_prevent_bulk_update(): void
    {
        $series = $this->createSeries();
        $anchor = $series->sessions()->orderBy('planned_start_at')->first();
        LessonLearningAudit::create(['session_id' => $anchor->id, 'actor_user_id' => $this->teacher->user_id, 'event_type' => 'updated',
            'before_snapshot' => [], 'after_snapshot' => [], 'happened_at' => now()]);
        $this->invalid(fn () => $this->service()->previewFuture($this->admin, $series, $anchor, $this->futureData($anchor)), 'schedule_dates');
        LessonLearningAudit::query()->delete();
        $anchor->update(['status' => 'ongoing']);
        $this->invalid(fn () => $this->service()->previewFuture($this->admin, $series, $anchor, $this->futureData($anchor)), 'schedule_dates');
        $anchor->update(['status' => 'scheduled']);
        Carbon::setTestNow(Carbon::parse('2026-10-26 10:00:00', 'Asia/Makassar'));
        $this->invalid(fn () => $this->service()->previewFuture($this->admin, $series, $anchor, $this->futureData($anchor)), 'schedule_dates');
    }

    public function test_future_cancellation_keeps_times_teacher_and_notes_and_is_idempotent(): void
    {
        $series = $this->createSeries();
        $sessions = $series->sessions()->orderBy('planned_start_at')->get();
        $anchor = $sessions[1];
        $preview = $this->service()->previewFuture($this->admin, $series, $anchor, $this->futureData($anchor,
            ['status' => 'cancelled', 'teacher_id' => $this->otherTeacher->id, 'topic' => 'Must not replace']));
        $payload = ['preview_token' => $preview['previewToken']];
        $this->service()->updateFuture($this->admin, $series, $anchor, $payload);
        $this->service()->updateFuture($this->admin, $series, $anchor, $payload);
        $this->assertSame('scheduled', $sessions[0]->fresh()->status);
        $this->assertSame('cancelled', $anchor->fresh()->status);
        $this->assertSame('2026-11-02 10:00:00', $anchor->fresh()->getRawOriginal('planned_start_at'));
        $this->assertSame($this->teacher->id, $anchor->fresh()->teacher_id);
        $this->assertSame('Percakapan awal', $anchor->fresh()->topic);
        $this->assertDatabaseCount('lesson_schedule_series_changes', 1);
    }

    public function test_bulk_conflict_aborts_all_changes_and_does_not_create_audit(): void
    {
        $series = $this->createSeries();
        $anchor = $series->sessions()->orderBy('planned_start_at')->first();
        $preview = $this->service()->previewFuture($this->admin, $series, $anchor, $this->futureData($anchor));
        $this->existing('2026-11-09', '13:30', '15:00', ['class_group_id' => null]);
        $before = $series->sessions()->orderBy('id')->get()->map->getRawOriginal()->all();
        $this->invalid(fn () => $this->service()->updateFuture($this->admin, $series, $anchor, ['preview_token' => $preview['previewToken']]), 'schedule_dates');
        $this->assertSame($before, $series->sessions()->orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertDatabaseCount('lesson_schedule_series_changes', 0);
    }

    public function test_database_failure_after_first_update_rolls_back_every_changed_session(): void
    {
        $series = $this->createSeries();
        $anchor = $series->sessions()->orderBy('planned_start_at')->first();
        $before = $series->sessions()->orderBy('id')->get()->map->getRawOriginal()->all();
        $preview = $this->service()->previewFuture($this->admin, $series, $anchor, $this->futureData($anchor));
        DB::unprepared("CREATE TRIGGER reject_second_series_update BEFORE UPDATE ON lesson_sessions WHEN NEW.planned_start_at = '2026-11-02 13:00:00' BEGIN SELECT RAISE(ABORT, 'fixture late update failure'); END");
        try { $this->service()->updateFuture($this->admin, $series, $anchor, ['preview_token' => $preview['previewToken']]); $this->fail('Expected database failure'); }
        catch (QueryException $error) { $this->assertStringContainsString('fixture late update failure', $error->getMessage()); }
        $this->assertSame($before, $series->sessions()->orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertDatabaseCount('lesson_schedule_series_changes', 0);
    }

    public function test_stale_bulk_preview_after_single_edit_is_rejected_then_returns_a_reviewable_preview(): void
    {
        $series = $this->createSeries();
        $sessions = $series->sessions()->orderBy('planned_start_at')->get();
        $anchor = $sessions[0];
        $preview = $this->service()->previewFuture($this->admin, $series, $anchor, $this->futureData($anchor));
        $sessions[2]->update(['topic' => 'Diubah petugas lain']);
        $this->actingAs($this->admin)->post(route('admin.lesson-series.update-future', [$series, $anchor]), ['preview_token' => $preview['previewToken']])
            ->assertStatus(422)->assertSee('Data seri berubah sejak pratinjau');
        $this->assertSame('Diubah petugas lain', $sessions[2]->fresh()->topic);
        $this->assertDatabaseCount('lesson_schedule_series_changes', 0);
    }

    public function test_new_attendance_after_preview_prevents_overwrite(): void
    {
        $series = $this->createSeries();
        $sessions = $series->sessions()->orderBy('planned_start_at')->get();
        $preview = $this->service()->previewFuture($this->admin, $series, $sessions[0], $this->futureData($sessions[0]));
        SessionStudent::create(['session_id' => $sessions[2]->id, 'student_id' => $this->student->id, 'attendance_status' => 'absent']);
        $this->invalid(fn () => $this->service()->updateFuture($this->admin, $series, $sessions[0], ['preview_token' => $preview['previewToken']]), 'preview_token');
        $this->assertSame('10:00', $sessions[0]->fresh()->planned_start_at->format('H:i'));
        $this->assertSame('10:00', $sessions[2]->fresh()->planned_start_at->format('H:i'));
    }

    public function test_bulk_update_checks_collisions_between_proposed_dates_even_when_old_targets_are_ignored(): void
    {
        $series = $this->createSeries();
        $sessions = $series->sessions()->orderBy('planned_start_at')->get();
        // A previous single-meeting adjustment puts two occurrences on one date at distinct times.
        $sessions[1]->update(['planned_start_at' => '2026-10-26 12:00:00', 'planned_end_at' => '2026-10-26 13:30:00']);
        $preview = $this->service()->previewFuture($this->admin, $series, $sessions[0], $this->futureData($sessions[0]));
        $this->assertNotEmpty($preview['rows'][0]['issues']);
        $this->invalid(fn () => $this->service()->updateFuture($this->admin, $series, $sessions[0], ['preview_token' => $preview['previewToken']]), 'schedule_dates');
        $this->assertDatabaseCount('lesson_schedule_series_changes', 0);
    }

    public function test_anchor_must_belong_to_route_series_and_new_dates_must_stay_same_day_and_future(): void
    {
        $series = $this->createSeries();
        $anchor = $series->sessions()->orderBy('planned_start_at')->first();
        $unrelated = $this->existing('2026-10-27');
        $this->actingAs($this->admin)->get(route('admin.lesson-series.edit', [$series, $unrelated]))->assertNotFound();
        $this->post(route('admin.lesson-series.preview-future', [$series, $unrelated]), $this->futureData($unrelated))->assertNotFound();
        $this->invalid(fn () => $this->service()->previewFuture($this->admin, $series, $anchor,
            $this->futureData($anchor, ['planned_end_at' => '2026-10-27T14:30'])), 'planned_end_at');
        $past = $this->service()->previewFuture($this->admin, $series, $anchor,
            $this->futureData($anchor, ['planned_start_at' => '2026-10-22T13:00', 'planned_end_at' => '2026-10-22T14:30']));
        $this->assertNotEmpty($past['rows'][0]['issues']);
    }

    public function test_html_escapes_names_and_notes_in_preview_and_series_detail(): void
    {
        $this->group->update(['name' => '<script>alert(1)</script>']);
        $response = $this->actingAs($this->admin)->post(route('admin.lesson-series.preview'), $this->data())->assertOk();
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $series = $this->service()->create($this->admin, ['preview_token' => $response->viewData('previewToken')]);
        $this->get(route('admin.lesson-series.show', $series))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_teacher_defaults_to_current_week_with_chronological_date_groups_and_month_shortcut(): void
    {
        $this->existing('2026-10-23', '12:00', '13:00', ['topic' => 'Sesi siang']);
        $this->existing('2026-10-23', '10:00', '11:00', ['topic' => 'Sesi pagi']);
        $this->existing('2026-10-24', '09:00', '10:00', ['topic' => 'Sesi sabtu']);
        $this->existing('2026-10-26', '09:00', '10:00', ['topic' => 'Minggu depan']);
        $this->actingAs($this->teacher->user)->get(route('teacher.schedule.index'))->assertOk()
            ->assertViewHas('filters', fn ($f) => $f['from'] === '2026-10-19' && $f['to'] === '2026-10-25')
            ->assertViewHas('sessions', fn ($s) => $s->total() === 3)
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('session.topic')->all() === ['Sesi pagi', 'Sesi siang', 'Sesi sabtu'])
            ->assertSee('Jumat, 23 Oktober 2026')->assertSee('Sabtu, 24 Oktober 2026')->assertSee('Hari ini')->assertSee('Minggu ini')->assertSee('Bulan ini');
        $this->get(route('teacher.schedule.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()
            ->assertViewHas('sessions', fn ($s) => $s->total() === 4);
    }

    public function test_teacher_can_browse_all_three_months_with_pagination_without_seeing_other_teachers(): void
    {
        $series = $this->createSeries(['starts_on' => '2026-11-01', 'ends_on' => '2027-01-31']);
        $this->existing('2026-11-03', extra: ['teacher_id' => $this->otherTeacher->id, 'topic' => 'Rahasia Teacher lain']);
        $this->actingAs($this->teacher->user)->get(route('teacher.schedule.index', ['from' => '2026-11-01', 'to' => '2027-01-31']))
            ->assertOk()->assertViewHas('sessions', fn ($s) => $s->total() === 13 && $s->count() === 12)
            ->assertSee('from=2026-11-01', false)->assertSee('to=2027-01-31', false)->assertDontSee('Rahasia Teacher lain');
        $this->get(route('teacher.schedule.index', ['from' => '2026-11-01', 'to' => '2027-01-31', 'page' => 2]))
            ->assertOk()->assertViewHas('sessions', fn ($s) => $s->count() === 1)->assertSee('Senin, 25 Januari 2027');
        $this->assertSame(13, $series->sessions()->count());
    }
}
