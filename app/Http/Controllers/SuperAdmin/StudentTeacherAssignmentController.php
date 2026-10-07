<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreStudentTeacherAssignmentRequest;
use App\Http\Requests\SuperAdmin\UpdateStudentTeacherAssignmentRequest;
use App\Models\StudentTeacherAssignment;
use App\Services\StudentTeacherAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class StudentTeacherAssignmentController extends Controller
{
    public function __construct(
        private StudentTeacherAssignmentService $studentTeacherAssignmentService
    ) {
    }

    public function index(): View
    {
        $assignments = StudentTeacherAssignment::query()
            ->with([
                'student',
                'teacher',
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
            'superadmin.student-teacher-assignments.create'
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
            'teacher',
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
            'teacher',
        ]);

        return view(
            'superadmin.student-teacher-assignments.edit',
            [
                'assignment' => $studentTeacherAssignment,
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
}