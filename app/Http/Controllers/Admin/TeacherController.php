<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Services\TeacherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function __construct(
        private readonly TeacherService $teacherService
    ) {
    }

    public function index(Request $request): View
    {
        $teachers = Teacher::with(['user', 'photoFile'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    })
                    ->orWhere('whatsapp_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.teachers.index', compact('teachers'));
    }

    public function create(): View
    {
        $users = $this->availableTeacherUsers();

        return view('admin.teachers.create', compact('users'));
    }

    public function store(
        StoreTeacherRequest $request
    ): RedirectResponse {

        $this->teacherService->create(
            $request->validated(),
            $request->file('photo')
        );

        return redirect()
            ->route('admin.teachers.index')
            ->with('success', 'Profil guru berhasil ditambahkan.');
    }


    public function show(Teacher $teacher): View
    {
        Gate::authorize('view', $teacher);

        $teacher->load([
            'user',
            'photoFile',
        ]);

        return view('admin.teachers.show', compact('teacher'));
    }


    public function edit(Teacher $teacher): View
    {
        Gate::authorize('view', $teacher);

        $teacher->load([
            'user',
            'photoFile',
        ]);

        $users = $this->availableTeacherUsers($teacher);

        return view(
            'admin.teachers.edit',
            compact('teacher', 'users')
        );
    }


    public function update(
        UpdateTeacherRequest $request,
        Teacher $teacher
    ): RedirectResponse {
        Gate::authorize('update', $teacher);

        $this->teacherService->update(
            $teacher,
            $request->validated(),
            $request->file('photo')
        );

        return redirect()
            ->route('admin.teachers.index')
            ->with('success', 'Profil guru berhasil diperbarui.');
    }


    private function availableTeacherUsers(
        ?Teacher $teacher = null
    ) {
        $assignedUserIds = Teacher::query()
            ->when(
                $teacher,
                fn ($query) => $query->where('id', '!=', $teacher->id)
            )
            ->pluck('user_id');

        return User::query()
            ->where('role_code', 'teacher')
            ->where('is_active', true)
            ->whereNotIn('id', $assignedUserIds)
            ->orderBy('full_name')
            ->get([
                'id',
                'username',
                'full_name',
                'email',
            ]);
    }
}