<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreUserRequest;
use App\Http\Requests\SuperAdmin\UpdateUserRequest;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $user = User::create([
            'id' => (string) Str::uuid(),
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'password_hash' => Hash::make($validated['password']),
            'full_name' => $validated['full_name'],
            'role_code' => $validated['role_code'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $response = redirect()
            ->route('superadmin.users.index')
            ->with('success', 'Pengguna berhasil dibuat.');

        if ($user->role_code === 'student' && $user->is_active) {
            $response->with('created_student_user_id', $user->id);
        }

        if ($user->role_code === 'teacher' && $user->is_active) {
            $response->with('created_teacher_user_id', $user->id);
        }

        if ($user->role_code === 'admin' && $user->is_active) {
            $response->with('created_admin_user_id', $user->id);
        }

        return $response;
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

        return DB::transaction(function () use ($validated, $user, $request) {
            // Kunci data SuperAdmin aktif sebelum memeriksa jumlahnya.
            $activeSuperadmins = User::query()
                ->where('role_code', 'superadmin')
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            // Ambil ulang target user di dalam transaksi.
            $target = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newRole = $validated['role_code'];
            $newStatus = filter_var(
                $validated['is_active'],
                FILTER_VALIDATE_BOOLEAN
            );

            if ($target->id === $request->user()->id && ($newRole !== 'superadmin' || ! $newStatus)) {
                return back()->withErrors(['user' => 'Super Admin tidak dapat menonaktifkan atau mengubah role akunnya sendiri.'])->withInput();
            }

            // Akun siswa harus tetap menggunakan role student.
            $hasStudentProfile = Student::query()
                ->where('portal_user_id', $target->id)
                ->exists();

            if ($hasStudentProfile && $newRole !== 'student') {
                return back()
                    ->withErrors([
                        'role_code' => 'Role akun tidak dapat diubah karena akun terhubung dengan profil siswa.',
                    ])
                    ->withInput();
            }

            // Akun guru harus tetap menggunakan role teacher.
            $hasTeacherProfile = Teacher::query()
                ->where('user_id', $target->id)
                ->exists();

            if ($hasTeacherProfile && $newRole !== 'teacher') {
                return back()
                    ->withErrors([
                        'role_code' => 'Role akun tidak dapat diubah karena akun terhubung dengan profil guru.',
                    ])
                    ->withInput();
            }

            // Jangan sampai perubahan ini menghilangkan
            // seluruh SuperAdmin aktif.
            $isActiveSuperadmin =
                $target->role_code === 'superadmin'
                && $target->is_active;

            $willLoseSuperadminAccess =
                $newRole !== 'superadmin'
                || ! $newStatus;

            if (
                $isActiveSuperadmin
                && $willLoseSuperadminAccess
                && $activeSuperadmins->count() <= 1
            ) {
                return back()
                    ->withErrors([
                        'user' => 'SuperAdmin aktif terakhir tidak dapat dinonaktifkan atau diturunkan rolenya.',
                    ])
                    ->withInput();
            }

            $data = [
                'username' => $validated['username'],
                'email' => $validated['email'] ?? null,
                'full_name' => $validated['full_name'],
                'role_code' => $newRole,
                'is_active' => $newStatus,
            ];

            if (! empty($validated['password'])) {
                $data['password_hash'] = Hash::make(
                    $validated['password']
                );
            }

            $target->update($data);

            return redirect()
                ->route('superadmin.users.show', $target)
                ->with('success', 'User berhasil diperbarui.');
        });
    }

    public function toggleStatus(
        Request $request,
        User $user
    ): RedirectResponse {
        return DB::transaction(function () use ($request, $user) {
            // Kunci seluruh SuperAdmin aktif terlebih dahulu
            // agar pengecekan jumlah SuperAdmin konsisten.
            $activeSuperadmins = User::query()
                ->where('role_code', 'superadmin')
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            // Ambil ulang target user di dalam transaction.
            $target = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            // User tidak boleh mengubah status akunnya sendiri.
            if ($target->id === $request->user()->id) {
                return back()
                    ->withErrors([
                        'user' => 'SuperAdmin tidak dapat mengubah status akunnya sendiri melalui fitur ini.',
                    ])
                    ->withInput();
            }

            // Jangan sampai sistem kehilangan seluruh SuperAdmin aktif.
            if (
                $target->role_code === 'superadmin'
                && $target->is_active
                && $activeSuperadmins->count() <= 1
            ) {
                return back()
                    ->withErrors([
                        'user' => 'SuperAdmin aktif terakhir tidak dapat dinonaktifkan.',
                    ])
                    ->withInput();
            }

            $target->update([
                'is_active' => ! $target->is_active,
            ]);

            return redirect()
                ->route('superadmin.users.show', $target)
                ->with(
                    'success',
                    $target->is_active
                        ? 'User berhasil diaktifkan.'
                        : 'User berhasil dinonaktifkan.'
                );
        });
    }
}
