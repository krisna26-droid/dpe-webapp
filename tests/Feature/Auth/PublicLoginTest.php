<?php

namespace Tests\Feature\Auth;

use App\Models\PublicContentSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicLoginTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'superadmin', bool $active = true): User
    {
        return User::create([
            'id' => (string) Str::uuid(), 'username' => $role.'_'.Str::random(6),
            'full_name' => 'Test account', 'password_hash' => Hash::make('CorrectPassword!'),
            'role_code' => $role, 'is_active' => $active,
        ]);
    }

    public function test_login_uses_the_published_brand_identity(): void
    {
        PublicContentSection::create([
            'id' => (string) Str::uuid(), 'section_key' => 'identity',
            'title' => 'Identitas lembaga yang ditayangkan', 'body' => 'Tagline resmi',
            'display_order' => 0, 'is_published' => true,
        ]);
        $this->get('/login')->assertOk()->assertSee('Identitas lembaga yang ditayangkan')
            ->assertSee('Kembali ke beranda');
    }

    public function test_each_role_is_redirected_to_its_dashboard(): void
    {
        foreach (['superadmin', 'admin', 'teacher', 'student'] as $role) {
            $user = $this->account($role);
            $this->post('/login', ['username' => $user->username, 'password' => 'CorrectPassword!'])
                ->assertRedirect(route($role.'.dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->post('/logout')->assertRedirect('/login');
            $this->assertGuest();
        }
    }

    public function test_wrong_password_keeps_only_username_and_shows_error(): void
    {
        $user = $this->account();
        $this->from('/login')->post('/login', ['username' => $user->username, 'password' => 'WrongPassword!'])
            ->assertRedirect('/login')->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->get('/login')->assertOk()->assertSee($user->username)
            ->assertSee('Username atau password tidak sesuai.')->assertDontSee('WrongPassword!');
    }

    public function test_inactive_account_cannot_login(): void
    {
        $user = $this->account('teacher', false);
        $this->from('/login')->post('/login', ['username' => $user->username, 'password' => 'CorrectPassword!'])
            ->assertRedirect('/login')->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
