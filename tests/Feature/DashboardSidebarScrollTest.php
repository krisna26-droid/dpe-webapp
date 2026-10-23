<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardSidebarScrollTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], ['superadmin', 'admin', 'teacher', 'student']);
    }

    private function account(string $role): User
    {
        $user = User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16),
            'full_name' => 'Akun Sidebar Uji', 'password_hash' => 'fixture-hash',
            'role_code' => $role, 'is_active' => true]);
        if ($role === 'teacher') {
            Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $user->id,
                'whatsapp_number' => '081234567890', 'is_active' => true]);
        }
        if ($role === 'student') {
            Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $user->id,
                'full_name' => 'Siswa Sidebar Uji', 'began_on' => '2026-10-01', 'status' => 'active']);
        }

        return $user;
    }

    private function sidebarKey(string $html): string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $nodes = (new \DOMXPath($dom))->query('//aside[@data-sidebar and @data-sidebar-scroll-key]');
        $this->assertSame(1, $nodes->length);
        $key = $nodes->item(0)->getAttribute('data-sidebar-scroll-key');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key);

        return $key;
    }

    #[DataProvider('roles')]
    public function test_scroll_identity_is_stable_across_pages_and_separate_for_accounts(string $role): void
    {
        $first = $this->account($role);
        $dashboard = $this->actingAs($first)->get(route($role.'.dashboard'))->assertOk()->getContent();
        $key = $this->sidebarKey($dashboard);
        $inbox = $this->get(route('inbox.index'))->assertOk()->getContent();
        $this->assertSame($key, $this->sidebarKey($inbox));
        $this->assertStringNotContainsString($first->id, $key);
        foreach ([$dashboard, $inbox] as $html) {
            $this->assertSame(1, substr_count($html, '/assets/dpe/sidebar-scroll.js?v='));
            $this->assertLessThan(strpos($html, '<main'), strpos($html, '/assets/dpe/sidebar-scroll.js?v='));
        }

        $second = $this->account($role);
        $other = $this->actingAs($second)->get(route($role.'.dashboard'))->assertOk()->getContent();
        $this->assertNotSame($key, $this->sidebarKey($other));
    }

    public function test_same_account_gets_another_identity_after_role_change(): void
    {
        $user = $this->account('admin');
        $before = $this->sidebarKey($this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent());
        $user->update(['role_code' => 'superadmin']);
        $after = $this->sidebarKey($this->actingAs($user->fresh())->get(route('superadmin.dashboard'))->assertOk()->getContent());
        $this->assertNotSame($before, $after);
    }
}
