<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentService;
use App\Services\TeachingDirectoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    public function index(TeachingDirectoryService $directory): View
    {
        $actor = auth()->user();
        $today = Carbon::today();
        $students = Student::query()
            ->with([
                'portalUser',
                'enrollments' => fn ($query) => $directory->scopeEnrollments($query, $actor)->with(['branch', 'program']),
            ])
            ->when(
                auth()->user()?->role_code === 'admin',
                function ($query) use ($today) {
                    $query->whereHas('enrollments', function ($enrollments) use ($today) {
                        $enrollments
                            ->whereDate('starts_on', '<=', $today)
                            ->where(function ($period) use ($today) {
                                $period
                                    ->whereNull('ends_on')
                                    ->orWhereDate('ends_on', '>=', $today);
                            })
                            ->whereIn('branch_id', function ($branches) use ($today) {
                                $branches
                                    ->select('branch_id')
                                    ->from('branch_admin_assignments')
                                    ->where('admin_user_id', auth()->id())
                                    ->whereDate('starts_on', '<=', $today)
                                    ->where(function ($period) use ($today) {
                                        $period
                                            ->whereNull('ends_on')
                                            ->orWhereDate('ends_on', '>=', $today);
                                    });
                            });
                    });
                }
            )
            ->orderBy('full_name')
            ->paginate(15);

        $studentTeachers = $directory->teachersForStudents($actor, $students->getCollection()->pluck('id'));

        return view('admin.students.index', compact('students', 'studentTeachers'));
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

        $student->load([
            'portalUser',
            'enrollments.branch',
            'enrollments.program',
        ]);

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
