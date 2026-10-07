<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTeacherBranchAssignmentRequest;
use App\Http\Requests\SuperAdmin\UpdateTeacherBranchAssignmentRequest;
use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Services\TeacherBranchAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TeacherBranchAssignmentController extends Controller
{
    public function index(): View
    {
        $assignments = TeacherBranchAssignment::query()
            ->with([
                'teacher',
                'branch',
            ])
            ->orderByDesc('starts_on')
            ->paginate(15);

        return view(
            'superadmin.teacher-branch-assignments.index',
            [
                'assignments' => $assignments,
            ]
        );
    }

    public function create(): View
    {
        $teachers = Teacher::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'superadmin.teacher-branch-assignments.create',
            [
                'teachers' => $teachers,
                'branches' => $branches,
            ]
        );
    }

    public function store(
        StoreTeacherBranchAssignmentRequest $request,
        TeacherBranchAssignmentService $service
    ): RedirectResponse {
        $service->create($request->validated());

        return redirect()
            ->route('superadmin.teacher-branch-assignments.index')
            ->with(
                'success',
                'Assignment teacher berhasil dibuat.'
            );
    }

    public function show(
        TeacherBranchAssignment $teacherBranchAssignment
    ): View {
        $teacherBranchAssignment->load([
            'teacher',
            'branch',
        ]);

        return view(
            'superadmin.teacher-branch-assignments.show',
            [
                'assignment' => $teacherBranchAssignment,
            ]
        );
    }

    public function edit(
        TeacherBranchAssignment $teacherBranchAssignment
    ): View {
        $teachers = Teacher::query()
            ->where('is_active', true)
            ->orWhere('id', $teacherBranchAssignment->teacher_id)
            ->orderBy('full_name')
            ->get();

        $branches = Branch::query()
            ->where('is_active', true)
            ->orWhere('id', $teacherBranchAssignment->branch_id)
            ->orderBy('name')
            ->get();

        return view(
            'superadmin.teacher-branch-assignments.edit',
            [
                'assignment' => $teacherBranchAssignment,
                'teachers' => $teachers,
                'branches' => $branches,
            ]
        );
    }

    public function update(
        UpdateTeacherBranchAssignmentRequest $request,
        TeacherBranchAssignment $teacherBranchAssignment,
        TeacherBranchAssignmentService $service
    ): RedirectResponse {
        $service->update(
            $teacherBranchAssignment,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.teacher-branch-assignments.show',
                $teacherBranchAssignment
            )
            ->with(
                'success',
                'Assignment teacher berhasil diperbarui.'
            );
    }

    public function destroy(
        TeacherBranchAssignment $teacherBranchAssignment,
        TeacherBranchAssignmentService $service
    ): RedirectResponse {
        $service->delete($teacherBranchAssignment);

        return redirect()
            ->route('superadmin.teacher-branch-assignments.index')
            ->with(
                'success',
                'Assignment teacher berhasil dihapus.'
            );
    }
}