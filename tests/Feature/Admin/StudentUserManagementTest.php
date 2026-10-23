<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\StudentAccountProvisioningService;
use App\Services\StudentEnrollmentService;
use App\Services\StudentService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StudentUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch;
    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Makassar'));
        $this->admin = $this->account('admin');
        $this->branch = $this->branch('Cabang Admin');
        $this->assign($this->admin, $this->branch);
        $this->program = Program::create([
            'id' => (string) Str::uuid(), 'code' => 'ENGLISH', 'name' => 'English Private',
            'class_type' => 'private', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $role = 'student', bool $active = true): User
    {
        return User::create([
            'id' => (string) Str::uuid(), 'username' => $role . '_' . Str::random(10),
            'full_name' => 'Akun ' . $role, 'email' => null, 'role_code' => $role,
            'is_active' => $active, 'password_hash' => Hash::make('Testing123!'),
        ]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create([
            'id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => $name,
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 25, 'is_active' => true,
        ]);
    }

    private function assign(User $admin, Branch $branch, ?string $endsOn = null): BranchAdminAssignment
    {
        return BranchAdminAssignment::create([
            'id' => (string) Str::uuid(), 'admin_user_id' => $admin->id,
            'branch_id' => $branch->id, 'starts_on' => '2026-08-01', 'ends_on' => $endsOn,
        ]);
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'Anak Baru', 'username' => 'anak_baru', 'email' => '',
            'password' => 'Student123!', 'password_confirmation' => 'Student123!',
            'program_id' => $this->program->id, 'began_on' => '2026-10-01',
            'school_name' => 'SD Contoh', 'grade_name' => 'Kelas 4',
        ], $overrides);
    }

    private function createStudent(Branch $branch, array $overrides = []): User
    {
        $account = $this->account();
        $student = Student::create([
            'id' => (string) Str::uuid(), 'portal_user_id' => $account->id,
            'full_name' => $account->full_name, 'began_on' => '2026-08-01', 'status' => 'active',
        ]);
        StudentEnrollment::create(array_replace([
            'id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $branch->id,
            'program_id' => $this->program->id, 'starts_on' => '2026-08-01', 'ends_on' => null,
        ], $overrides));

        return $account;
    }

    private function html(string $content): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    public function test_admin_creates_account_profile_and_enrollment_without_a_separate_branch_step(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->data())
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $user = User::where('username', 'anak_baru')->firstOrFail();
        $student = $user->studentProfile;
        $this->assertSame('student', $user->role_code);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email);
        $this->assertTrue(Hash::check('Student123!', $user->password_hash));
        $this->assertSame('Anak Baru', $student->full_name);
        $this->assertSame('SD Contoh', $student->school_name);
        $this->assertSame('Kelas 4', $student->grade_name);
        $this->assertSame('active', $student->status);
        $this->assertSame('2026-10-01', $student->began_on->toDateString());
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id, 'branch_id' => $this->branch->id,
            'program_id' => $this->program->id, 'ends_on' => null,
        ]);
        $this->assertSame('2026-10-01', $student->enrollments()->firstOrFail()->starts_on->toDateString());
        $this->get(route('admin.users.show', $user))->assertOk()->assertSee('Cabang Admin')->assertSee('English Private')
            ->assertSee(route('admin.students.show', $student), false)->assertDontSee('Student123!')->assertDontSee($user->password_hash);
        $this->get(route('admin.students.index'))->assertOk()->assertSee('Anak Baru');
        $this->get(route('admin.students.show', $student))->assertOk()->assertSee('Cabang Admin');
    }

    public function test_student_can_log_in_and_has_a_branch_profile_immediately(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->data())->assertSessionHasNoErrors();
        $this->post(route('logout'));
        $this->post('/login', ['username' => 'anak_baru', 'password' => 'Student123!'])
            ->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs(User::where('username', 'anak_baru')->firstOrFail());
        $this->get(route('student.dashboard'))->assertOk()->assertSee('Anak Baru');
        $this->get(route('student.profile'))->assertOk()->assertSee('Cabang Admin')->assertSee('English Private');
    }

    public function test_form_has_automatic_branch_fixed_role_optional_email_and_password_eyes(): void
    {
        $this->actingAs($this->admin);
        $this->get(route('admin.users.index'))->assertOk()->assertSee(route('admin.users.create'), false);
        $response = $this->get(route('admin.users.create'))->assertOk();
        $html = $this->html($response->getContent());
        $this->assertSame(1, $html->query('//a[@href="' . route('admin.users.index') . '" and contains(@class,"is-active")]')->length);
        $this->assertSame(0, $html->query('//select[@name="role_code"]|//select[@name="branch_id"]')->length);
        $this->assertSame($this->branch->id, $html->query('//input[@name="branch_id"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $html->query('//input[@name="email"]/@required')->length);
        $this->assertSame(2, $html->query('//button[@data-account-toggle="password" or @data-account-toggle="password_confirmation"]/*[local-name()="svg"]')->length);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_cannot_access_admin_user_pages_or_create_accounts(string $role): void
    {
        $actor = $this->account($role);
        $student = $this->createStudent($this->branch);
        $this->actingAs($actor);
        foreach (['admin.users.index', 'admin.users.create'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('admin.users.show', $student))->assertForbidden();
        $this->post(route('admin.users.store'), $this->data())->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'anak_baru']);
    }

    public static function forbiddenRoles(): array
    {
        return [['teacher'], ['student'], ['superadmin']];
    }

    public function test_guest_and_inactive_admin_are_denied(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->post(route('admin.users.store'), $this->data())->assertRedirect(route('login'));
        $this->admin->update(['is_active' => false]);
        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertForbidden();
        $this->post(route('admin.users.store'), $this->data())->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'anak_baru']);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_or_forged_data_does_not_leave_a_partial_student(array $change, string $field): void
    {
        $this->actingAs($this->admin)->from(route('admin.users.create'))
            ->post(route('admin.users.store'), $this->data($change))
            ->assertRedirect(route('admin.users.create'))->assertSessionHasErrors($field);
        $this->assertDatabaseMissing('users', ['username' => 'anak_baru']);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('student_enrollments', 0);
    }

    public static function invalidInputs(): array
    {
        return [
            'teacher role' => [['role_code' => 'teacher'], 'role_code'],
            'admin role' => [['role_code' => 'admin'], 'role_code'],
            'superadmin role' => [['role_code' => 'superadmin'], 'role_code'],
            'existing account' => [['portal_user_id' => 'forged'], 'portal_user_id'],
            'inactive account' => [['is_active' => '0'], 'is_active'],
            'short password' => [['password' => 'abc', 'password_confirmation' => 'abc'], 'password'],
            'password mismatch' => [['password_confirmation' => 'Different123!'], 'password'],
            'empty program' => [['program_id' => ''], 'program_id'],
            'missing program' => [['program_id' => (string) 'missing-program'], 'program_id'],
            'invalid date' => [['began_on' => '2026-02-31'], 'began_on'],
            'array name' => [['full_name' => ['invalid']], 'full_name'],
            'array email' => [['email' => ['invalid']], 'email'],
            'forged branch' => [['branch_id' => 'not-assigned'], 'branch_id'],
        ];
    }

    public function test_foreign_branch_is_rejected_even_when_it_exists(): void
    {
        $foreign = $this->branch('Cabang Rahasia');
        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk()->assertDontSee('Cabang Rahasia');
        $this->post(route('admin.users.store'), $this->data(['branch_id' => $foreign->id]))->assertSessionHasErrors('branch_id');
        $this->assertDatabaseMissing('users', ['username' => 'anak_baru']);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_branch_and_program_must_still_be_active_when_saving(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk();
        $this->branch->update(['is_active' => false]);
        $this->post(route('admin.users.store'), $this->data())->assertSessionHasErrors('branch_id');
        $this->branch->update(['is_active' => true]);
        $this->program->update(['is_active' => false]);
        $this->post(route('admin.users.store'), $this->data())->assertSessionHasErrors('program_id');
        $form = $this->get(route('admin.users.create'))->assertOk()->assertSee('Belum ada program belajar aktif');
        $this->assertSame(1, $this->html($form->getContent())->query('//button[@type="submit" and @disabled]')->length);
        $this->assertDatabaseMissing('users', ['username' => 'anak_baru']);
    }

    public function test_no_assignment_expired_assignment_and_future_assignment_cannot_create(): void
    {
        $this->actingAs($this->admin);
        $assignment = BranchAdminAssignment::firstOrFail();
        $assignment->delete();
        $form = $this->get(route('admin.users.create'))->assertOk()->assertSee('Belum ada penugasan cabang aktif');
        $this->assertSame(1, $this->html($form->getContent())->query('//button[@type="submit" and @disabled]')->length);
        $this->post(route('admin.users.store'), $this->data())->assertSessionHasErrors('branch_id');
        $assignment = $this->assign($this->admin, $this->branch, '2026-10-21');
        $this->post(route('admin.users.store'), $this->data())->assertSessionHasErrors('branch_id');
        $assignment->update(['starts_on' => '2026-10-23', 'ends_on' => null]);
        $this->post(route('admin.users.store'), $this->data())->assertSessionHasErrors('branch_id');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_stale_form_cannot_register_in_a_previous_admin_branch(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk();
        BranchAdminAssignment::firstOrFail()->update(['ends_on' => '2026-10-21']);
        $newBranch = $this->branch('Cabang Baru');
        $this->assign($this->admin, $newBranch);
        $this->post(route('admin.users.store'), $this->data(['branch_id' => $this->branch->id]))
            ->assertSessionHasErrors('branch_id');
        $this->assertDatabaseMissing('users', ['username' => 'anak_baru']);
    }

    public function test_service_rechecks_actor_and_program_instead_of_trusting_prevalidated_data(): void
    {
        $service = app(StudentAccountProvisioningService::class);
        DB::table('users')->where('id', $this->admin->id)->update(['role_code' => 'teacher']);
        try {
            $service->create($this->admin, $this->data());
            $this->fail('A stale Admin role must not authorize creation.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        DB::table('users')->where('id', $this->admin->id)->update(['role_code' => 'admin', 'is_active' => false]);
        try {
            $service->create($this->admin, $this->data());
            $this->fail('An inactive actor must not authorize creation.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        DB::table('users')->where('id', $this->admin->id)->update(['is_active' => true]);
        $this->program->update(['is_active' => false]);
        try {
            $service->create($this->admin, $this->data());
            $this->fail('A program deactivated after validation must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('program_id', $error->errors());
        }
        $this->assertDatabaseMissing('users', ['username' => 'anak_baru']);
    }

    public function test_multiple_assignments_require_an_explicit_choice_within_the_admin_scope(): void
    {
        // Legacy finite assignments can overlap despite the unique open-assignment key.
        $other = $this->branch('Cabang Kedua');
        $this->assign($this->admin, $other, '2026-12-31');
        $this->actingAs($this->admin);
        $form = $this->get(route('admin.users.create'))->assertOk();
        $this->assertSame(3, $this->html($form->getContent())->query('//select[@name="branch_id"]/option')->length);
        $this->post(route('admin.users.store'), $this->data())->assertSessionHasErrors('branch_id');
        $this->post(route('admin.users.store'), $this->data(['branch_id' => $other->id]))->assertSessionHasNoErrors();
        $student = User::where('username', 'anak_baru')->firstOrFail()->studentProfile;
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $student->id, 'branch_id' => $other->id]);
    }

    public function test_account_directory_is_scoped_and_does_not_expose_other_branch_history(): void
    {
        $own = $this->createStudent($this->branch);
        $own->update(['full_name' => 'Siswa Cabang Sendiri']);
        $foreignBranch = $this->branch('Cabang Rahasia');
        $foreign = $this->createStudent($foreignBranch);
        $foreign->update(['full_name' => 'Siswa Rahasia']);
        StudentEnrollment::create([
            'id' => (string) Str::uuid(), 'student_id' => $own->studentProfile->id,
            'branch_id' => $foreignBranch->id, 'program_id' => $this->program->id,
            'starts_on' => '2026-06-01', 'ends_on' => '2026-07-31',
        ]);
        $unlinked = $this->account();
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('Siswa Cabang Sendiri')->assertDontSee('Siswa Rahasia')->assertDontSee($unlinked->username)->assertDontSee('Cabang Rahasia');
        $this->get(route('admin.users.show', $own))->assertOk()->assertDontSee('Cabang Rahasia');
        foreach ([$foreign, $unlinked, $this->admin, $this->account('teacher')] as $outside) {
            $this->get(route('admin.users.show', $outside))->assertNotFound();
        }
        $this->get(route('superadmin.users.index'))->assertForbidden();
        $this->put('/admin/users/' . $own->id, $this->data())->assertStatus(405);
    }

    public function test_closed_enrollment_and_lost_assignment_remove_account_access(): void
    {
        $own = $this->createStudent($this->branch);
        $this->actingAs($this->admin)->get(route('admin.users.show', $own))->assertOk();
        $enrollment = $own->studentProfile->enrollments()->firstOrFail();
        $enrollment->update(['ends_on' => '2026-10-21']);
        $this->get(route('admin.users.show', $own))->assertNotFound();
        $this->get(route('admin.users.index'))->assertOk()->assertDontSee($own->username);
        $enrollment->update(['ends_on' => null]);
        BranchAdminAssignment::firstOrFail()->update(['ends_on' => '2026-10-21']);
        $this->get(route('admin.users.show', $own))->assertNotFound();
    }

    public function test_future_enrollment_remains_visible_without_an_inaccessible_profile_link(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->data(['began_on' => '2026-11-01']))
            ->assertSessionHasNoErrors();
        $user = User::where('username', 'anak_baru')->firstOrFail();
        $this->get(route('admin.users.index'))->assertOk()->assertSee('Anak Baru');
        $this->get(route('admin.users.show', $user))->assertOk()->assertSee('Akan dimulai')
            ->assertDontSee(route('admin.students.show', $user->studentProfile), false);
        $this->get(route('admin.students.index'))->assertOk()->assertDontSee('Anak Baru');
    }

    public function test_optional_values_are_trimmed_and_duplicate_submissions_create_only_one_complete_student(): void
    {
        $data = $this->data(['full_name' => '  Anak Baru  ', 'username' => '  anak_baru  ', 'email' => '  SISWA@EXAMPLE.TEST  ']);
        $this->actingAs($this->admin)->post(route('admin.users.store'), $data)->assertSessionHasNoErrors();
        $user = User::where('username', 'anak_baru')->firstOrFail();
        $this->assertSame('Anak Baru', $user->full_name);
        $this->assertSame('siswa@example.test', $user->email);
        $this->post(route('admin.users.store'), $data)->assertSessionHasErrors(['username', 'email']);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('student_enrollments', 1);
        $this->post(route('admin.users.store'), $this->data(['username' => 'another_student', 'email' => $user->email]))
            ->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['username' => 'another_student']);
    }

    public function test_validation_preserves_non_secret_input_but_never_redisplays_passwords(): void
    {
        $this->actingAs($this->admin)->from(route('admin.users.create'))
            ->post(route('admin.users.store'), $this->data(['password_confirmation' => 'Different123!']))
            ->assertSessionHasErrors('password');
        $response = $this->get(route('admin.users.create'))->assertOk()->assertDontSee('Student123!')->assertDontSee('Different123!');
        $html = $this->html($response->getContent());
        $this->assertSame('anak_baru', $html->query('//input[@name="username"]')->item(0)->getAttribute('value'));
        foreach (['password', 'password_confirmation'] as $field) {
            $this->assertSame('', $html->query('//input[@name="' . $field . '"]')->item(0)->getAttribute('value'));
        }
    }

    #[DataProvider('failingServices')]
    public function test_downstream_failure_rolls_back_account_profile_and_enrollment(string $service): void
    {
        $this->mock($service, function ($mock) {
            $mock->shouldReceive('create')->once()->andThrow(new RuntimeException('Simulated provisioning failure'));
        });
        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->data())->assertStatus(500);
        $this->assertDatabaseMissing('users', ['username' => 'anak_baru']);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('student_enrollments', 0);
    }

    public static function failingServices(): array
    {
        return [[StudentService::class], [StudentEnrollmentService::class]];
    }

    public function test_get_pages_are_read_only_and_escape_account_names(): void
    {
        $own = $this->createStudent($this->branch);
        $own->update(['full_name' => '<img src=x onerror=alert(1)>']);
        $before = [User::count(), Student::count(), StudentEnrollment::count(), DB::table('branches')->get()->toJson()];
        $this->actingAs($this->admin);
        $this->get(route('admin.users.index'))->assertOk()->assertSee('&lt;img src=x', false)->assertDontSee('<img src=x', false);
        $this->get(route('admin.users.create'))->assertOk();
        $this->get(route('admin.users.show', $own))->assertOk()->assertDontSee('<img src=x', false);
        $this->assertSame($before, [User::count(), Student::count(), StudentEnrollment::count(), DB::table('branches')->get()->toJson()]);
    }
}
