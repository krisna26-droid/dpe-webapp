<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentUserRequest;
use App\Models\Program;
use App\Services\StudentAccountProvisioningService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentUserController extends Controller
{
    public function __construct(private readonly StudentAccountProvisioningService $accounts)
    {
    }

    public function index(Request $request): View
    {
        $actor = $request->user();
        $users = $this->accounts->users($actor)
            ->with(['studentProfile.enrollments' => fn ($q) => $this->accounts->scopeEnrollments($q, $actor)->with(['branch', 'program'])])
            ->orderBy('full_name')->orderBy('id')->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    public function create(Request $request): View
    {
        return view('admin.users.create', [
            'branches' => $this->accounts->branches($request->user())->orderBy('name')->get(),
            'programs' => Program::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreStudentUserRequest $request): RedirectResponse
    {
        $user = $this->accounts->create($request->user(), $request->validated());

        return redirect()->route('admin.users.show', $user)->with('success',
            'Akun dan profil siswa berhasil dibuat serta langsung terdaftar di cabang Anda.');
    }

    public function show(Request $request, string $user): View
    {
        $actor = $request->user();
        $managedUser = $this->accounts->users($actor)
            ->with(['studentProfile.enrollments' => fn ($q) => $this->accounts->scopeEnrollments($q, $actor)->with(['branch', 'program'])])
            ->findOrFail($user);

        return view('admin.users.show', compact('managedUser'));
    }
}
