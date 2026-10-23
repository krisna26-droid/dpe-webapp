<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Services\TeacherService;
use App\Services\TeachingDirectoryService;
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

    public function index(Request $request, TeachingDirectoryService $directory): View
    {
        $actor = $request->user();
        $teachers = $directory->teachers($actor)->with(['user', 'photoFile',
                'branchAssignments' => fn ($query) => $directory->scopeTeacherBranches($query, $actor)->with('branch')])
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

        $teacherSummaries = $directory->teacherSummaries($actor, $teachers->getCollection()->pluck('id'));
        $editableTeacherIds = $teachers->getCollection()->filter(fn ($teacher) => $actor->role_code === 'superadmin'
            || $teacher->branchAssignments->contains(fn ($assignment) => $assignment->isActiveOn()))->pluck('id');

        return view('admin.teachers.index', compact('teachers', 'teacherSummaries', 'editableTeacherIds'));
    }

    public function create(): View
    {
        $users = $this->availableTeacherUsers();

        return view('admin.teachers.create', compact('users'));
    }

    public function store(
        StoreTeacherRequest $request
    ): RedirectResponse {

        $teacher = $this->teacherService->create(
            $request->validated(),
            $request->file('photo')
        );

        if (auth()->user()?->role_code === 'superadmin') {
            return redirect()
                ->route('superadmin.teacher-branch-assignments.create', [
                    'teacher_id' => $teacher->id,
                ])
                ->with(
                    'success',
                    'Profil guru berhasil dibuat. Lanjutkan dengan memilih cabang dan periode penugasannya.'
                );
        }

        return redirect()
            ->route('admin.teachers.index')
            ->with('success', 'Profil guru berhasil ditambahkan.');
    }


    public function show(Request $request, Teacher $teacher, TeachingDirectoryService $directory): View
    {
        Gate::authorize('view', $teacher);

        $teacher->load([
            'user',
            'photoFile',
            'branchAssignments' => fn ($query) => $directory->scopeTeacherBranches($query, $request->user())->with('branch'),
        ]);

        $teachingStudents = $directory->studentsForTeacher($request->user(), $teacher)
            ->paginate(15, ['*'], 'students_page')->withQueryString()->fragment('teacher-students');
        $canEditTeacher = Gate::allows('update', $teacher);

        return view('admin.teachers.show', compact('teacher', 'teachingStudents', 'canEditTeacher'));
    }


    public function edit(Teacher $teacher): View
    {
        Gate::authorize('update', $teacher);

        $teacher->load([
            'user',
            'photoFile',
            'branchAssignments.branch',
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
