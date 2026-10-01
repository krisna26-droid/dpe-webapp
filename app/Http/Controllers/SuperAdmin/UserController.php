<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreUserRequest;
use App\Http\Requests\SuperAdmin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->orderBy('role_code')
            ->orderBy('full_name')
            ->paginate(15);

        return view('superadmin.users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('superadmin.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::create([
            'id' => (string) Str::uuid(),
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'password_hash' => Hash::make($validated['password']),
            'full_name' => $validated['full_name'],
            'role_code' => $validated['role_code'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('superadmin.users.index')
            ->with('success', 'User berhasil dibuat.');
    }

    public function show(User $user): View
    {
        return view('superadmin.users.show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user): View
    {
        return view('superadmin.users.edit', [
            'user' => $user,
        ]);
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): RedirectResponse {
        $validated = $request->validated();

        $data = [
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'full_name' => $validated['full_name'],
            'role_code' => $validated['role_code'],
            'is_active' => $validated['is_active'],
        ];

        if (! empty($validated['password'])) {
            $data['password_hash'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return redirect()
            ->route('superadmin.users.show', $user)
            ->with('success', 'User berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()
                ->withErrors([
                    'user' => 'Superadmin tidak dapat menonaktifkan akun sendiri.',
                ]);
        }

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        return redirect()
            ->route('superadmin.users.show', $user)
            ->with(
                'success',
                $user->is_active
                    ? 'User berhasil diaktifkan.'
                    : 'User berhasil dinonaktifkan.'
            );
    }
}