<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeOwnPasswordRequest;
use App\Services\OwnAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountSettingsController extends Controller
{
    public function __construct(private OwnAccountService $account) {}

    public function index(Request $request): View
    {
        $role = explode('.', $request->route()->getName())[0];
        $actor = $this->account->actor($request->user(), $role);
        $student = $role === 'student' ? $actor->studentProfile : null;

        return view('account.settings', compact('actor', 'role', 'student'));
    }

    public function password(ChangeOwnPasswordRequest $request): RedirectResponse
    {
        $role = explode('.', $request->route()->getName())[0];
        $this->account->changePassword($request->user(), $role, $request->validated());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('account_success', 'Kata sandi berhasil diubah. Silakan masuk dengan kata sandi baru.');
    }
}
