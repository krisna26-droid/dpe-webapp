<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\ClassLearningSession;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\ClassLearningSessionService;
use App\Services\StudentAccountProvisioningService;
use App\Services\StudentAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassLearningSessionWeekdayTest extends TestCase
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

    private function slot(string $name, ?ClassGroup $group = null): ClassLearningSession
    {
        return ClassLearningSession::create(['id' => (string) Str::uuid(), 'class_group_id' => ($group ?? $this->group)->id, 'name' => $name]);
    }

    private function lesson(ClassLearningSession $slot, string $day = '2026-11-02', int $hour = 14): LessonSession
    {
        return LessonSession::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id,
            'teacher_id' => $this->teacher->id, 'class_group_id' => $slot->class_group_id, 'class_learning_session_id' => $slot->id,
            'planned_start_at' => sprintf('%s %02d:00:00', $day, $hour), 'planned_end_at' => sprintf('%s %02d:00:00', $day, $hour + 1),
            'status' => 'scheduled', 'teaching_location' => 'branch']);
    }

    private function member(): ClassGroupMembership
    {
        return ClassGroupMembership::where('student_id', $this->student->id)->firstOrFail();
    }

    private function input(array $slots): array
    {
        return ['username' => 'murid_baru', 'full_name' => 'Murid Baru', 'password' => 'DpePassword123',
            'grade_name' => '5 SD', 'school_name' => 'SD Uji', 'guardian_phone' => '+62 812 3456 7890', 'branch_id' => $this->branch->id,
            'teacher_id' => $this->teacher->id, 'learning_session_ids' => array_map(fn ($s) => $s->id, $slots)];
    }

    private function week(): array
    {
        $one = $this->slot('Sesi 1');
        $two = $this->slot('Sesi 2');
        foreach (['2026-11-02', '2026-11-03', '2026-11-09', '2026-11-10'] as $date) {
            $this->lesson($one, $date, 14);
            $this->lesson($two, $date, 15);
        }

        return [$one, $two];
    }

    private function checkboxValues(string $html, bool $checkedOnly = false): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $nodes = (new \DOMXPath($document))->query('//input[@name="learning_session_ids[]"]'.($checkedOnly ? '[@checked]' : ''));

        return array_map(fn ($node) => $node->getAttribute('value'), iterator_to_array($nodes));
    }

    public function test_the_two_days_produce_four_choices_and_only_selected_days_enter_rosters_and_portal(): void
    {
        [$one, $two] = $this->week();
        $service = app(ClassLearningSessionService::class);
        $choices = $service->choices($service->options($this->admin));
        $this->assertSame([$one->id.'@1', $two->id.'@1', $one->id.'@2', $two->id.'@2'], $choices->pluck('key')->all());
        $this->assertSame(['Senin', 'Senin', 'Selasa', 'Selasa'], $choices->pluck('day_name')->all());
        $service->select($this->member(), [$one->id.'@1', $two->id.'@2'], '2026-10-23');
        $expected = [];
        foreach (LessonSession::get() as $session) {
            $wanted = $session->class_learning_session_id === $one->id ? $session->planned_start_at->isoWeekday() === 1 : $session->planned_start_at->isoWeekday() === 2;
            $this->assertSame($wanted, app(StudentAttendanceService::class)->roster($session)->contains('id', $this->student->id));
            if ($wanted) {
                $expected[] = $session->id;
            }
        }
        $page = $this->actingAs($this->student->portalUser)->get(route('student.schedule', ['month' => '2026-11']))->assertOk();
        $this->assertEqualsCanonicalizing($expected, $page->viewData('sessions')->pluck('id')->all());
    }

    public function test_both_monday_sessions_can_be_selected_without_selecting_tuesday(): void
    {
        [$one, $two] = $this->week();
        app(ClassLearningSessionService::class)->select($this->member(), [$one->id.'@1', $two->id.'@1'], '2026-10-23');
        foreach (LessonSession::get() as $session) {
            $this->assertSame($session->planned_start_at->isoWeekday() === 1, app(StudentAttendanceService::class)->roster($session)->contains('id', $this->student->id));
        }
    }

    public static function staffRoutes(): array
    {
        return [['admin', 'admin'], ['superadmin', 'admin'], ['superadmin', 'superadmin']];
    }

    #[DataProvider('staffRoutes')]
    public function test_membership_form_store_and_edit_keep_independent_day_selections(string $role, string $prefix): void
    {
        [$one, $two] = $this->week();
        $actor = $role === 'admin' ? $this->admin : $this->superadmin;
        $this->member()->delete();
        $page = $this->actingAs($actor)->get(route($prefix.'.class-group-memberships.create', ['class_group_id' => $this->group->id]))->assertOk()->assertSee('Senin')->assertSee('Selasa');
        $this->assertEqualsCanonicalizing([$one->id.'@1', $two->id.'@1', $one->id.'@2', $two->id.'@2'], $this->checkboxValues($page->getContent()));
        $this->post(route($prefix.'.class-group-memberships.store'), ['class_group_id' => $this->group->id, 'student_id' => $this->student->id,
            'starts_on' => '2026-10-23', 'select_learning_sessions' => 1, 'learning_session_ids' => [$one->id.'@1', $two->id.'@2']])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('class_learning_session_enrollments', ['class_learning_session_id' => $one->id, 'weekday' => 1]);
        $this->assertDatabaseHas('class_learning_session_enrollments', ['class_learning_session_id' => $two->id, 'weekday' => 2]);
        $edit = $this->get(route($prefix.'.class-group-memberships.edit', $this->member()))->assertOk();
        $this->assertEqualsCanonicalizing([$one->id.'@1', $two->id.'@2'], $this->checkboxValues($edit->getContent(), true));
    }

    public function test_legacy_all_day_selection_keeps_both_days_and_get_edit_is_read_only(): void
    {
        [$one] = $this->week();
        app(ClassLearningSessionService::class)->select($this->member(), [$one->id], '2026-10-23');
        $before = DB::table('class_learning_session_enrollments')->get()->toJson();
        $this->assertDatabaseHas('class_learning_session_enrollments', ['class_learning_session_id' => $one->id, 'weekday' => null]);
        $page = $this->actingAs($this->admin)->get(route('admin.class-group-memberships.edit', $this->member()))->assertOk();
        $this->assertEqualsCanonicalizing([$one->id.'@1', $one->id.'@2'], $this->checkboxValues($page->getContent(), true));
        $this->assertSame($before, DB::table('class_learning_session_enrollments')->get()->toJson());
        foreach (LessonSession::get() as $session) {
            $this->assertSame($session->class_learning_session_id === $one->id, app(StudentAttendanceService::class)->roster($session)->contains('id', $this->student->id));
        }
    }

    public function test_new_day_choices_apply_from_the_effective_date_without_rewriting_previous_participation(): void
    {
        [$one, $two] = $this->week();
        app(ClassLearningSessionService::class)->select($this->member(), [$one->id], '2026-10-23');
        app(ClassLearningSessionService::class)->select($this->member(), [$two->id.'@2'], '2026-11-05');
        foreach (LessonSession::get() as $session) {
            $wanted = $session->planned_start_at->toDateString() < '2026-11-05' ? $session->class_learning_session_id === $one->id
                : $session->class_learning_session_id === $two->id && $session->planned_start_at->isoWeekday() === 2;
            $this->assertSame($wanted, app(StudentAttendanceService::class)->roster($session)->contains('id', $this->student->id));
        }
        $this->assertSame('2026-11-04', $this->member()->learningSessionEnrollments()->where('class_learning_session_id', $one->id)->whereNull('weekday')->firstOrFail()->ends_on->toDateString());
    }

    public function test_an_unavailable_weekday_does_not_replace_the_existing_selection(): void
    {
        [$one] = $this->week();
        app(ClassLearningSessionService::class)->select($this->member(), [$one->id.'@1'], '2026-10-23');
        $before = DB::table('class_learning_session_enrollments')->get()->toJson();
        try {
            app(ClassLearningSessionService::class)->select($this->member(), [$one->id.'@3'], '2026-10-23');
            $this->fail('Wednesday has no published schedule');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('learning_session_ids', $error->errors());
        }
        $this->assertSame($before, DB::table('class_learning_session_enrollments')->get()->toJson());
    }

    public static function invalidChoices(): array
    {
        return [['@0'], ['@8'], ['@Mon'], ['@1@2'], ['@-1']];
    }

    #[DataProvider('invalidChoices')]
    public function test_invalid_weekday_tokens_are_rejected(string $suffix): void
    {
        [$one] = $this->week();
        $this->expectException(ValidationException::class);
        app(ClassLearningSessionService::class)->select($this->member(), [$one->id.$suffix], '2026-10-23');
    }

    public function test_a_wildcard_cannot_be_mixed_with_a_day_for_the_same_session(): void
    {
        [$one] = $this->week();
        $this->expectException(ValidationException::class);
        app(ClassLearningSessionService::class)->select($this->member(), [$one->id, $one->id.'@1'], '2026-10-23');
    }

    public function test_another_class_day_cannot_be_selected(): void
    {
        $slot = $this->slot('Sesi luar', $this->otherGroup);
        $this->lesson($slot);
        $this->expectException(ValidationException::class);
        app(ClassLearningSessionService::class)->select($this->member(), [$slot->id.'@1'], '2026-10-23');
    }

    public static function accountRoles(): array
    {
        return [['admin'], ['superadmin']];
    }

    #[DataProvider('accountRoles')]
    public function test_student_account_creation_preserves_weekdays(string $role): void
    {
        [$one, $two] = $this->week();
        $data = $this->input([$one, $two]);
        $data['learning_session_ids'] = [$one->id.'@1', $two->id.'@2'];
        if ($role === 'superadmin') {
            $data['role_code'] = 'student';
        }
        $this->actingAs($role === 'admin' ? $this->admin : $this->superadmin)->post(route($role.'.users.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $student = User::where('username', 'murid_baru')->firstOrFail()->studentProfile;
        $links = $student->classGroupMemberships->firstOrFail()->learningSessionEnrollments;
        $this->assertCount(2, $links);
        $this->assertSame([1, 2], $links->sortBy('weekday')->pluck('weekday')->values()->all());
        foreach (LessonSession::get() as $session) {
            $wanted = $session->class_learning_session_id === $one->id ? $session->planned_start_at->isoWeekday() === 1 : $session->planned_start_at->isoWeekday() === 2;
            $this->assertSame($wanted, app(StudentAttendanceService::class)->roster($session)->contains('id', $student->id));
        }
    }

    public function test_account_options_and_validation_do_not_offer_another_teachers_day(): void
    {
        [$one] = $this->week();
        LessonSession::where('class_learning_session_id', $one->id)->whereIn('planned_start_at', ['2026-11-03 14:00:00', '2026-11-10 14:00:00'])->update(['teacher_id' => $this->otherTeacher->id]);
        $options = app(StudentAccountProvisioningService::class)->options($this->admin);
        $this->assertSame([$this->otherTeacher->id], $options['accountSessionChoices']->firstWhere('key', $one->id.'@2')['teacher_ids']);
        $data = $this->input([$one]);
        $data['learning_session_ids'] = [$one->id.'@2'];
        $this->actingAs($this->admin)->post(route('admin.users.store'), $data)->assertSessionHasErrors('learning_session_ids');
        $this->assertDatabaseMissing('users', ['username' => 'murid_baru']);
    }

    public function test_monday_participants_follow_a_tuesday_replacement_without_joining_normal_tuesday(): void
    {
        [$one] = $this->week();
        app(ClassLearningSessionService::class)->select($this->member(), [$one->id.'@1'], '2026-10-23');
        $source = LessonSession::where('class_learning_session_id', $one->id)->where('planned_start_at', '2026-11-02 14:00:00')->firstOrFail();
        $replacement = $this->lesson($one, '2026-11-03', 17);
        $replacement->update(['rescheduled_from_session_id' => $source->id]);
        $this->assertTrue(app(StudentAttendanceService::class)->roster($replacement->fresh())->contains('id', $this->student->id));
        $normal = LessonSession::where('class_learning_session_id', $one->id)->where('planned_start_at', '2026-11-03 14:00:00')->firstOrFail();
        $this->assertFalse(app(StudentAttendanceService::class)->roster($normal)->contains('id', $this->student->id));
    }

    public function test_shifted_series_occurrences_keep_the_original_day_selection(): void
    {
        [$one] = $this->week();
        app(ClassLearningSessionService::class)->select($this->member(), [$one->id.'@1'], '2026-10-23');
        $shifted = $this->lesson($one, '2026-11-04', 17);
        $shifted->update(['series_occurrence_on' => '2026-11-02']);
        $this->assertSame(1, app(ClassLearningSessionService::class)->weekday($shifted));
        $this->assertTrue(app(StudentAttendanceService::class)->roster($shifted)->contains('id', $this->student->id));
        $choices = app(ClassLearningSessionService::class)->choices(collect([$one->fresh()]));
        $this->assertStringContainsString('Rabu 17:00–18:00', $choices->firstWhere('weekday', 1)['details']);
    }

    public function test_rollback_cannot_remove_saved_weekday_restrictions(): void
    {
        [$one] = $this->week();
        app(ClassLearningSessionService::class)->select($this->member(), [$one->id.'@1'], '2026-10-23');
        $migration = require database_path('migrations/2026_10_29_030000_add_weekday_to_learning_session_enrollments.php');
        try {
            $migration->down();
            $this->fail('Removing weekday restrictions must be blocked');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Pilihan sesi per hari sudah digunakan', $error->getMessage());
        }
        $this->assertDatabaseHas('class_learning_session_enrollments', ['class_learning_session_id' => $one->id, 'weekday' => 1]);
    }
}
