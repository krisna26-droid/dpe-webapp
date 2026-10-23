<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreStudentTeacherAssignmentRequest;
use App\Http\Requests\SuperAdmin\UpdateStudentTeacherAssignmentRequest;
use App\Models\Student;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Services\StudentTeacherAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class StudentTeacherAssignmentController extends Controller
{
    public function __construct(
        private StudentTeacherAssignmentService $studentTeacherAssignmentService
    ) {}

    public function index(): View
    {
        $assignments = StudentTeacherAssignment::query()
            ->with([
                'student',
                'teacher.user',
            ])
            ->orderByDesc('starts_on')
            ->paginate(15);

        return view(
            'superadmin.student-teacher-assignments.index',
            [
                'assignments' => $assignments,
            ]
        );
    }

    public function create(): View
    {
        return view(
            'superadmin.student-teacher-assignments.create',
            $this->formOptions()
        );
    }

    public function store(
        StoreStudentTeacherAssignmentRequest $request
    ): RedirectResponse {
        $assignment = $this->studentTeacherAssignmentService->create(
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.student-teacher-assignments.show',
                $assignment
            )
            ->with(
                'success',
                'Student teacher assignment berhasil dibuat.'
            );
    }

    public function show(
        StudentTeacherAssignment $studentTeacherAssignment
    ): View {
        $studentTeacherAssignment->load([
            'student',
            'teacher.user',
        ]);

        return view(
            'superadmin.student-teacher-assignments.show',
            [
                'assignment' => $studentTeacherAssignment,
            ]
        );
    }

    public function edit(
        StudentTeacherAssignment $studentTeacherAssignment
    ): View {
        $studentTeacherAssignment->load([
            'student',
            'teacher.user',
        ]);

        return view(
            'superadmin.student-teacher-assignments.edit',
            [
                'assignment' => $studentTeacherAssignment,
                ...$this->formOptions($studentTeacherAssignment),
            ]
        );
    }

    public function update(
        UpdateStudentTeacherAssignmentRequest $request,
        StudentTeacherAssignment $studentTeacherAssignment
    ): RedirectResponse {
        $this->studentTeacherAssignmentService->update(
            $studentTeacherAssignment,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.student-teacher-assignments.show',
                $studentTeacherAssignment
            )
            ->with(
                'success',
                'Student teacher assignment berhasil diperbarui.'
            );
    }

    private function formOptions(?StudentTeacherAssignment $current = null): array
    {
        return [
            'students' => Student::where(fn ($q) => $q->where('status', 'active')
                ->when($current, fn ($q) => $q->orWhere('id', $current->student_id)))->orderBy('full_name')->get(),
            'teachers' => Teacher::with('user')->where(function ($q) use ($current) {
                $q->where(fn ($q) => $q->where('is_active', true)->whereHas('user', fn ($u) => $u->where('is_active', true)->where('role_code', 'teacher')))
                    ->when($current, fn ($q) => $q->orWhere('id', $current->teacher_id));
            })->get()->sortBy('user.full_name')->values(),
        ];
    }
}
