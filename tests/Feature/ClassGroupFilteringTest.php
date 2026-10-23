<?php

namespace Tests\Feature;

use App\Models\{Branch, BranchAdminAssignment, ClassGroup, Program, Teacher, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassGroupFilteringTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $admin;
    private Branch $branch;
    private Branch $foreignBranch;
    private Program $program;
    private Program $foreignProgram;
    private Teacher $teacher;
    private Teacher $foreignTeacher;
    private array $groups;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Makassar'));
        $this->superadmin = $this->user('superadmin', 'Super Admin Filter');
        $this->admin = $this->user('admin', 'Admin Filter');
        $this->branch = $this->branch('Cabang Utama');
        $this->foreignBranch = $this->branch('Cabang Luar Rahasia');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id,
            'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->program = $this->program('English Regular');
        $localProgram = $this->program('English Conversation');
        $this->foreignProgram = $this->program('Program Luar Rahasia');
        $this->teacher = $this->teacher('Teacher Utama');
        $localTeacher = $this->teacher('Teacher Percakapan');
        $this->foreignTeacher = $this->teacher('Teacher Luar Rahasia');
        $this->groups = [
            'one' => $this->group('A-01', 'Kelompok Alpha', $this->branch, $this->program, $this->teacher),
            'two' => $this->group('A-02', 'Kelompok Beta', $this->branch, $localProgram, $localTeacher),
            'inactive' => $this->group('A-03', 'Kelompok Arsip', $this->branch, $this->program, $this->teacher, false),
            'foreign' => $this->group('B-01', 'Kelompok Luar Rahasia', $this->foreignBranch, $this->foreignProgram, $this->foreignTeacher),
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'full_name' => $name,
            'role_code' => $role, 'is_active' => true, 'password_hash' => 'fixture-filter-password']);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => $name,
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function program(string $name): Program
    {
        return Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => $name,
            'class_type' => 'regular', 'is_active' => true]);
    }

    private function teacher(string $name): Teacher
    {
        return Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', $name)->id,
            'whatsapp_number' => '081234567890', 'is_active' => true]);
    }

    private function group(string $code, string $name, Branch $branch, Program $program, Teacher $teacher, bool $active = true): ClassGroup
    {
        return ClassGroup::create(['id' => (string) Str::uuid(), 'code' => $code, 'name' => $name,
            'branch_id' => $branch->id, 'program_id' => $program->id,
            'default_teacher_id' => $teacher->id, 'is_active' => $active]);
    }

    public function test_superadmin_has_all_four_filters_and_all_branch_options(): void
    {
        $response = $this->actingAs($this->superadmin)->get(route('superadmin.class-groups.index'))->assertOk();
        foreach (['branch_id', 'class_group_id', 'program_id', 'teacher_id'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }
        $this->assertSame(4, $response->viewData('classGroups')->total());
        $options = $response->viewData('classGroupFilterOptions');
        $this->assertTrue($options['showBranch']);
        $this->assertCount(2, $options['branches']);
        $this->assertTrue($options['groups']->contains('id', $this->groups['foreign']->id));
    }

    public function test_admin_has_no_branch_control_and_options_only_from_their_groups(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.class-groups.index'))->assertOk()
            ->assertDontSee('name="branch_id"', false)->assertSee('name="class_group_id"', false)
            ->assertSee('name="program_id"', false)->assertSee('name="teacher_id"', false);
        foreach (['Cabang Luar Rahasia', 'Kelompok Luar Rahasia', 'Program Luar Rahasia', 'Teacher Luar Rahasia'] as $name) {
            $response->assertDontSee($name);
        }
        $this->assertSame(3, $response->viewData('classGroups')->total());
        $options = $response->viewData('classGroupFilterOptions');
        $this->assertFalse($options['showBranch']);
        $this->assertSame([$this->branch->id], $options['branches']->modelKeys());
        $this->assertCount(3, $options['groups']);
        $this->assertCount(2, $options['programs']);
        $this->assertCount(2, $options['teachers']);
    }

    public static function individualFilters(): array
    {
        return [
            'branch' => ['branch_id', 'branch', ['one', 'two', 'inactive']],
            'group' => ['class_group_id', 'one', ['one']],
            'program' => ['program_id', 'program', ['one', 'inactive']],
            'teacher' => ['teacher_id', 'teacher', ['one', 'inactive']],
        ];
    }

    #[DataProvider('individualFilters')]
    public function test_superadmin_can_use_each_filter_independently(string $field, string $record, array $expected): void
    {
        $id = isset($this->groups[$record]) ? $this->groups[$record]->id : $this->{$record}->id;
        $response = $this->actingAs($this->superadmin)->get(route('superadmin.class-groups.index', [$field => $id]))->assertOk();
        $this->assertEqualsCanonicalizing(array_map(fn ($key) => $this->groups[$key]->id, $expected),
            $response->viewData('classGroups')->getCollection()->modelKeys());
        $this->assertSame($id, $response->viewData('filters')[$field]);
    }

    public function test_combined_filters_use_the_intersection_and_keep_selected_values(): void
    {
        $filters = ['branch_id' => $this->branch->id, 'class_group_id' => $this->groups['one']->id,
            'program_id' => $this->program->id, 'teacher_id' => $this->teacher->id, 'status' => 'active', 'q' => 'Alpha'];
        $response = $this->actingAs($this->superadmin)->get(route('superadmin.class-groups.index', $filters))->assertOk();
        $this->assertSame([$this->groups['one']->id], $response->viewData('classGroups')->getCollection()->modelKeys());
        $this->assertEquals($filters, $response->viewData('filters'));
        $response->assertSee('1 kelompok sesuai filter')->assertSee('value="'.$this->groups['one']->id.'" selected', false);
        unset($filters['branch_id']);
        $this->actingAs($this->admin)->get(route('admin.class-groups.index', $filters))->assertOk()
            ->assertViewHas('classGroups', fn ($groups) => $groups->total() === 1);
    }

    public function test_incompatible_filters_show_an_empty_result_with_reset_and_keep_options(): void
    {
        $response = $this->actingAs($this->superadmin)->get(route('superadmin.class-groups.index', [
            'class_group_id' => $this->groups['one']->id, 'program_id' => $this->foreignProgram->id]))->assertOk()
            ->assertSee('Tidak ada kelompok yang sesuai')->assertSee('Reset filter');
        $this->assertSame(0, $response->viewData('classGroups')->total());
        $this->assertCount(4, $response->viewData('classGroupFilterOptions')['groups']);
        $this->actingAs($this->superadmin)->get(route('superadmin.class-groups.index'))->assertOk()
            ->assertViewHas('hasClassGroupFilters', false)->assertViewHas('classGroups', fn ($groups) => $groups->total() === 4);
    }

    public function test_foreign_filter_ids_cannot_bypass_admin_scope(): void
    {
        foreach (['branch_id' => $this->foreignBranch->id, 'class_group_id' => $this->groups['foreign']->id,
            'program_id' => $this->foreignProgram->id, 'teacher_id' => $this->foreignTeacher->id] as $field => $id) {
            $this->actingAs($this->admin)->get(route('admin.class-groups.index', [$field => $id]))->assertForbidden();
        }
        $this->get(route('admin.class-groups.index', ['branch_id' => $this->branch->id]))->assertOk()
            ->assertViewHas('classGroups', fn ($groups) => $groups->total() === 3);
        $this->get(route('superadmin.class-groups.index'))->assertForbidden();
    }

    public static function invalidFilters(): array
    {
        return [
            ['branch_id', 'missing-id'], ['class_group_id', 'missing-id'],
            ['program_id', 'missing-id'], ['teacher_id', 'missing-id'],
            ['class_group_id', ['array-value']], ['teacher_id', ['array-value']],
            ['status', 'unknown'], ['q', ['array-value']], ['q', str_repeat('a', 121)],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected_without_a_server_error(string $field, mixed $value): void
    {
        $this->actingAs($this->superadmin)->getJson(route('superadmin.class-groups.index', [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function listingRoles(): array
    {
        return [['superadmin'], ['admin']];
    }

    #[DataProvider('listingRoles')]
    public function test_pagination_keeps_filters_and_never_adds_foreign_groups(string $role): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->group('P-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'Paged '.$i, $this->branch, $this->program, $this->teacher);
        }
        $filters = ['program_id' => $this->program->id, 'teacher_id' => $this->teacher->id, 'q' => 'Paged', 'status' => 'active'];
        if ($role === 'superadmin') $filters['branch_id'] = $this->branch->id;
        $response = $this->actingAs($this->{$role})->get(route($role.'.class-groups.index', $filters))->assertOk();
        $paginator = $response->viewData('classGroups');
        $this->assertSame(17, $paginator->total());
        parse_str(parse_url($paginator->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertEquals($filters + ['page' => '2'], $query);
        $pageTwo = $this->get($paginator->nextPageUrl())->assertOk()->viewData('classGroups');
        $this->assertCount(2, $pageTwo);
        $this->assertTrue($pageTwo->getCollection()->every(fn ($group) => $group->branch_id === $this->branch->id));
    }

    public function test_historical_inactive_groups_teachers_and_programs_are_still_filterable(): void
    {
        $this->program->update(['is_active' => false]);
        $this->teacher->update(['is_active' => false]);
        $this->teacher->user->update(['is_active' => false]);
        $this->actingAs($this->admin)->get(route('admin.class-groups.index', [
            'class_group_id' => $this->groups['inactive']->id, 'program_id' => $this->program->id,
            'teacher_id' => $this->teacher->id, 'status' => 'inactive']))->assertOk()
            ->assertViewHas('classGroups', fn ($groups) => $groups->total() === 1);
    }

    public function test_no_current_branch_assignment_produces_empty_scoped_options(): void
    {
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-10-21']);
        $response = $this->actingAs($this->admin)->get(route('admin.class-groups.index'))->assertOk();
        $this->assertSame(0, $response->viewData('classGroups')->total());
        foreach (['branches', 'groups', 'programs', 'teachers'] as $key) {
            $this->assertCount(0, $response->viewData('classGroupFilterOptions')[$key]);
        }
    }

    public function test_search_zero_and_search_or_conditions_do_not_widen_the_scope(): void
    {
        $this->actingAs($this->admin)->get(route('admin.class-groups.index', ['q' => '0-no-match']))->assertOk()
            ->assertViewHas('classGroups', fn ($groups) => $groups->total() === 0);
        $this->get(route('admin.class-groups.index', ['q' => 'Luar Rahasia']))->assertOk()
            ->assertViewHas('classGroups', fn ($groups) => $groups->total() === 0);
        $this->groups['one']->update(['code' => 'ABC', 'name' => 'Kelompok Tanpa Angka']);
        $this->get(route('admin.class-groups.index', ['q' => '0', 'class_group_id' => $this->groups['one']->id]))->assertOk()
            ->assertViewHas('classGroups', fn ($groups) => $groups->total() === 0);
    }

    public function test_filtering_is_read_only_and_other_operations_keep_their_filter_forms(): void
    {
        $tables = ['class_groups', 'class_group_memberships', 'lesson_sessions', 'session_students',
            'branches', 'teachers', 'programs', 'branch_admin_assignments'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
        $this->actingAs($this->admin)->get(route('admin.class-groups.index', ['teacher_id' => $this->teacher->id]))->assertOk();
        $this->actingAs($this->superadmin)->get(route('superadmin.class-groups.index', ['branch_id' => $this->foreignBranch->id]))->assertOk();
        foreach ($before as $table => $rows) $this->assertSame($rows, DB::table($table)->get()->toJson());
        foreach (['class-group-memberships.index', 'lesson-sessions.index'] as $name) {
            $this->actingAs($this->admin)->get(route('admin.'.$name))->assertOk()->assertSee('name="branch_id"', false);
        }
    }
}
