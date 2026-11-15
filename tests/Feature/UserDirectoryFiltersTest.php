<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserDirectoryFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $admin;
    private Branch $branch;
    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 10:00:00');
        $this->superadmin = $this->account('superadmin', 'Super Admin');
        $this->admin = $this->account('admin', 'Admin Cabang');
        $this->branch = $this->branch('Cabang Saya');
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => 'EN', 'name' => 'English', 'class_type' => 'private', 'is_active' => true]);
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id,
            'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $role, string $name, bool $active = true): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => 'akun_'.Str::random(12),
            'email' => Str::random(12).'@example.test', 'password_hash' => 'fixture-password',
            'full_name' => $name, 'role_code' => $role, 'is_active' => $active]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => $name,
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true]);
    }

    private function student(string $name, bool $active = true, ?Branch $branch = null): User
    {
        $account = $this->account('student', $name, $active);
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $account->id,
            'full_name' => $name, 'began_on' => '2026-01-01', 'status' => 'active']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id,
            'branch_id' => ($branch ?? $this->branch)->id, 'program_id' => $this->program->id, 'starts_on' => '2026-01-01']);

        return $account;
    }

    public static function searchCases(): array
    {
        $cases = [];
        foreach (['admin', 'superadmin'] as $role) {
            foreach (['full_name', 'username', 'email'] as $field) { $cases[] = [$role, $field]; }
        }

        return $cases;
    }

    #[DataProvider('searchCases')]
    public function test_search_and_status_filters_work_together(string $role, string $field): void
    {
        $found = $this->student('Siswa Dicari', false);
        $found->update([$field => $field === 'email' ? 'needle@example.test' : 'needle akun']);
        $this->student('needle aktif');
        $this->student('Siswa Lain', false);
        $filters = ['q' => 'needle', 'status' => 'inactive'];
        if ($role === 'superadmin') { $filters['role_code'] = 'student'; }
        $response = $this->actingAs($this->$role)->get(route($role.'.users.index', $filters))->assertOk();
        $this->assertSame([$found->id], $response->viewData('users')->pluck('id')->all());
        $response->assertSee('name="q"', false)->assertSee('name="status"', false);
        $role === 'superadmin' ? $response->assertSee('name="role_code"', false) : $response->assertDontSee('name="role_code"', false);
    }

    public function test_superadmin_can_filter_teacher_and_admin_roles(): void
    {
        $teacher = $this->account('teacher', 'Pengguna Uji');
        $admin = $this->account('admin', 'Pengguna Uji');
        $this->student('Pengguna Uji');
        foreach (['teacher' => $teacher, 'admin' => $admin] as $role => $expected) {
            $response = $this->actingAs($this->superadmin)->get(route('superadmin.users.index', ['q' => 'Pengguna Uji', 'role_code' => $role, 'status' => 'active']))->assertOk();
            $this->assertSame([$expected->id], $response->viewData('users')->pluck('id')->all());
        }
    }

    public function test_admin_search_cannot_escape_branch_scope_through_username_or_email(): void
    {
        $inside = $this->student('needle milik cabang');
        $outside = $this->student('Siswa luar rahasia', branch: $this->branch('Cabang Luar'));
        $outside->update(['username' => 'needle_luar', 'email' => 'needle@example.test']);
        $this->account('teacher', 'needle guru');
        $response = $this->actingAs($this->admin)->get(route('admin.users.index', ['q' => 'needle']))->assertOk();
        $this->assertSame([$inside->id], $response->viewData('users')->pluck('id')->all());
        $response->assertDontSee('Siswa luar rahasia')->assertDontSee('needle_luar');
    }

    public static function roles(): array { return [['admin'], ['superadmin']]; }

    #[DataProvider('roles')]
    public function test_pagination_keeps_filters_and_empty_results_have_reset_guidance(string $role): void
    {
        for ($i = 0; $i < 16; $i++) { $this->student('needle '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), false); }
        $filters = ['q' => 'needle', 'status' => 'inactive'];
        if ($role === 'superadmin') { $filters['role_code'] = 'student'; }
        $response = $this->actingAs($this->$role)->get(route($role.'.users.index', $filters))->assertOk();
        $paginator = $response->viewData('users');
        $this->assertSame(16, $paginator->total());
        parse_str(parse_url($paginator->nextPageUrl(), PHP_URL_QUERY), $next);
        foreach ($filters as $key => $value) { $this->assertSame($value, $next[$key]); }
        $this->get($paginator->nextPageUrl())->assertOk()->assertViewHas('users', fn ($users) => $users->count() === 1);
        $this->get(route($role.'.users.index', ['q' => 'tidak-ada']))->assertOk()
            ->assertSee('Pengguna tidak ditemukan')->assertSee('Reset');
        $this->get(route($role.'.users.index'))->assertOk()->assertViewHas('hasUserFilters', false);
    }

    #[DataProvider('roles')]
    public function test_invalid_filter_values_are_rejected(string $role): void
    {
        $this->actingAs($this->$role);
        foreach ([['q' => ['array']], ['q' => str_repeat('x', 101)], ['status' => 'deleted'], ['role_code' => 'invalid'], ['page' => 0]] as $data) {
            $field = array_key_first($data);
            $this->getJson(route($role.'.users.index', $data))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
}
