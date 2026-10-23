<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $credentials['is_active'] = 1;

        if (! Auth::attempt($credentials)) {
            return back()
                ->withErrors([
                    'username' => 'Username atau password tidak sesuai.',
                ])
                ->onlyInput('username');
        }

        $request->session()->regenerate();

        // Only resume an internal child report link; never trust an arbitrary return URL.
        $intended = $request->session()->pull('url.intended');
        if (Auth::user()->role_code === 'student' && is_string($intended)) {
            $parts = parse_url($intended);
            $root = parse_url($request->root());
            $path = is_array($parts) ? ($parts['path'] ?? '') : '';
            $sameOrigin = is_array($parts) && ($parts['scheme'] ?? null) === ($root['scheme'] ?? null)
                && ($parts['host'] ?? null) === ($root['host'] ?? null) && ($parts['port'] ?? null) === ($root['port'] ?? null)
                && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['query']) && ! isset($parts['fragment']);
            if ($sameOrigin && preg_match('~^'.preg_quote($request->getBaseUrl(), '~').'/student/reports/quarterly/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$~iD', $path)) {
                return redirect($path);
            }
        }

        return match (Auth::user()->role_code) {
            'superadmin' => redirect()->route('superadmin.dashboard'),
            'admin' => redirect()->route('admin.dashboard'),
            'teacher' => redirect()->route('teacher.dashboard'),
            'student' => redirect()->route('student.dashboard'),
            default => $this->logoutWithError($request),
        };
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function logoutWithError(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors([
                'username' => 'Role pengguna tidak dikenali.',
            ]);
    }
}