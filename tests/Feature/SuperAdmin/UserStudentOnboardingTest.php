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
    use \Tests\Concerns\StudentAccountSessionFixture;

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

    private function studentAccountData(array $overrides=[]): array {
        $branch=\App\Models\Branch::create(['id'=>(string)Str::uuid(),'code'=>Str::random(10),'name'=>'Cabang Uji','timezone_name'=>'Asia/Makassar','payment_recap_day'=>20,'is_active'=>true]);
        $program=\App\Models\Program::create(['id'=>(string)Str::uuid(),'code'=>Str::random(10),'name'=>'English Uji','class_type'=>'regular','is_active'=>true]);
        return array_replace($this->accountSessionFields($branch,$program),['username'=>'siswa_baru','full_name'=>'Akun Siswa Baru','password'=>'Password123!','role_code'=>'student','grade_name'=>'4 SD','school_name'=>'SD Contoh'],$overrides);
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

    public function test_created_student_has_profile_and_is_ready_for_billing(): void
    {
        $this->actingAs($this->createUser());
        $response=$this->post(route('superadmin.users.store'),$this->studentAccountData())->assertSessionHasNoErrors();
        $user=User::where('username','siswa_baru')->firstOrFail();$student=$user->studentProfile;$this->assertNotNull($student);
        $response->assertRedirect(route('superadmin.users.show',$user));
        $this->get(route('superadmin.monthly-charges.create'))->assertOk()->assertViewHas('students',fn($students)=>$students->contains('id',$student->id));
        $this->get(route('superadmin.students.create'))->assertOk()->assertViewHas('studentUsers',fn($users)=>!$users->contains('id',$user->id));
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
        $this->assertSame(0, $html->query('//input[@name="is_active"]')->length);
    }

    public function test_invalid_account_form_keeps_identity_but_does_not_redisplay_passwords(): void
    {
        $this->actingAs($this->createUser());
        $createUrl = route('superadmin.users.create');

        $this->from($createUrl)->post(
            route('superadmin.users.store'),
            $this->studentAccountData(['password' => 'abc'])
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

    public function test_student_creation_does_not_offer_deactivation(): void
    {
        $this->actingAs($this->createUser());
        $this->post(route('superadmin.users.store'),$this->studentAccountData(['is_active'=>'0']))->assertSessionHasNoErrors();
        $user=User::where('username','siswa_baru')->firstOrFail();$this->assertTrue($user->is_active);$this->assertNotNull($user->studentProfile);
    }
}
