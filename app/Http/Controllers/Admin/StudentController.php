<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    public function index(): View
    {
        $students = Student::query()
            ->with('portalUser')
            ->orderBy('full_name')
            ->paginate(15);

        return view('admin.students.index', compact('students'));
    }

    public function create(): View
    {
        return view('admin.students.create', [
            'studentUsers' => $this->availableStudentUsers(),
        ]);
    }

    public function store(
        StoreStudentRequest $request,
        StudentService $studentService
    ): RedirectResponse {
        $studentService->create(
            $request->validated(),
            $request->file('photo')
        );

        return redirect()
            ->route('admin.students.index')
            ->with('success', 'Profil siswa berhasil dibuat.');
    }

    public function show(Student $student): View
    {
        Gate::authorize('view', $student);

        $student->load('portalUser');

        return view('admin.students.show', compact('student'));
    }

    public function edit(Student $student): View
    {
        Gate::authorize('view', $student);

        return view('admin.students.edit', [
            'student' => $student,
            'studentUsers' => $this->availableStudentUsers($student),
        ]);
    }

    public function update(
        UpdateStudentRequest $request,
        Student $student,
        StudentService $studentService
    ): RedirectResponse {
        Gate::authorize('update', $student);

        $studentService->update(
            $student,
            $request->validated(),
            $request->file('photo')
        );

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', 'Profil siswa berhasil diperbarui.');
    }

    private function availableStudentUsers(?Student $currentStudent = null)
    {
        $assignedUserIds = Student::query()
            ->whereNotNull('portal_user_id')
            ->when(
                $currentStudent,
                fn ($query) => $query->where(
                    'id',
                    '!=',
                    $currentStudent->id
                )
            )
            ->select('portal_user_id');

        return User::query()
            ->where('role_code', 'student')
            ->where('is_active', true)
            ->whereNotIn('id', $assignedUserIds)
            ->orderBy('full_name')
            ->get([
                'id',
                'username',
                'full_name',
            ]);
    }
}