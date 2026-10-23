<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Student;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserStudentOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role = 'superadmin'): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $role . '_' . Str::random(8),
            'email' => null,
            'password_hash' => Hash::make('Password123!'),
            'full_name' => 'Test User',
            'role_code' => $role,
            'is_active' => true,
        ]);
    }

    private function studentAccountData(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'Akun Siswa Baru',
            'username' => 'siswa_baru',
            'email' => 'siswa_baru@example.test',
            'role_code' => 'student',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => '1',
        ], $overrides);
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

    public function test_created_student_account_can_be_completed_and_selected_for_billing(): void
    {
        $this->actingAs($this->createUser());

        $this->get(route('superadmin.users.index'))
            ->assertOk()
            ->assertSee(route('superadmin.users.create'), false)
            ->assertSee('Tambah pengguna');

        $this->get(route('superadmin.users.create'))
            ->assertOk()
            ->assertViewIs('superadmin.users.create');

        $response = $this->post(
            route('superadmin.users.store'),
            $this->studentAccountData()
        );
        $user = User::query()->where('username', 'siswa_baru')->firstOrFail();

        $response->assertRedirect(route('superadmin.users.index'))
            ->assertSessionHas('created_student_user_id', $user->id);

        $profileUrl = route('superadmin.students.create', [
            'portal_user_id' => $user->id,
        ]);
        $this->get(route('superadmin.users.index'))
            ->assertOk()
            ->assertSee('Tambah profil siswa')
            ->assertSee($profileUrl, false);

        $profileForm = $this->get($profileUrl)->assertOk();
        $profileHtml = $this->html($profileForm->getContent());
        $this->assertSame(1, $profileHtml->query(
            '//select[@name="portal_user_id"]/option[@value="' . $user->id . '" and @selected]'
        )->length);

        $profileResponse = $this->post(route('superadmin.students.store'), [
            'portal_user_id' => $user->id,
            'full_name' => 'Siswa Baru',
            'school_name' => 'SD Contoh',
            'grade_name' => 'Kelas 4',
            'began_on' => '2026-10-09',
        ]);
        $student = Student::query()->where('portal_user_id', $user->id)->firstOrFail();
        $profileResponse->assertRedirect(route('superadmin.students.show', $student));

        $this->get(route('superadmin.students.show', $student))
            ->assertOk()->assertSee('Siswa Baru');
        $this->get(route('superadmin.students.index'))
            ->assertOk()->assertSee('Siswa Baru');

        $billingForm = $this->get(route('superadmin.monthly-charges.create'))
            ->assertOk()
            ->assertViewHas('students', fn ($students) => $students->contains('id', $student->id));
        $this->assertSame(1, $this->html($billingForm->getContent())->query(
            '//select[@name="student_id"]/option[@value="' . $student->id . '"]'
        )->length);

        $this->get(route('superadmin.students.create'))
            ->assertOk()
            ->assertViewHas('studentUsers', fn ($users) => ! $users->contains('id', $user->id));
    }

    public function test_empty_student_form_links_to_account_form_with_student_role_selected(): void
    {
        $this->actingAs($this->createUser());
        $accountUrl = route('superadmin.users.create', ['role_code' => 'student']);

        $this->get(route('superadmin.students.create'))
            ->assertOk()
            ->assertSee('Buat akun siswa')
            ->assertSee($accountUrl, false);

        $response = $this->get($accountUrl)->assertOk();
        $html = $this->html($response->getContent());
        $this->assertSame(1, $html->query('//select[@name="role_code"]/option[@value="student" and @selected]')->length);
        $this->assertSame(1, $html->query('//input[@name="is_active" and @type="checkbox" and @checked]')->length);
    }

    public function test_invalid_account_form_keeps_identity_but_does_not_redisplay_passwords(): void
    {
        $this->actingAs($this->createUser());
        $createUrl = route('superadmin.users.create');

        $this->from($createUrl)->post(
            route('superadmin.users.store'),
            $this->studentAccountData(['password_confirmation' => 'Different123!'])
        )->assertRedirect($createUrl)->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['username' => 'siswa_baru']);
        $response = $this->get($createUrl)->assertOk();
        $html = $this->html($response->getContent());
        $this->assertSame('siswa_baru', $html->query('//input[@name="username"]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $html->query('//select[@name="role_code"]/option[@value="student" and @selected]')->length);
        $this->assertSame('', $html->query('//input[@name="password"]')->item(0)->getAttribute('value'));
        $this->assertSame('', $html->query('//input[@name="password_confirmation"]')->item(0)->getAttribute('value'));
        $response->assertDontSee('Password123!')->assertDontSee('Different123!');
    }

    public function test_inactive_student_account_is_not_offered_for_a_new_profile(): void
    {
        $this->actingAs($this->createUser());

        $this->post(route('superadmin.users.store'), $this->studentAccountData(['is_active' => '0']))
            ->assertRedirect(route('superadmin.users.index'))
            ->assertSessionMissing('created_student_user_id');

        $user = User::query()->where('username', 'siswa_baru')->firstOrFail();
        $this->assertFalse($user->is_active);
        $this->get(route('superadmin.students.create', ['portal_user_id' => $user->id]))
            ->assertOk()
            ->assertViewHas('studentUsers', fn ($users) => ! $users->contains('id', $user->id));
    }
}
