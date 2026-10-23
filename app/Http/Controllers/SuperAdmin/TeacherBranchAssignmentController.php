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
                'teacher.user',
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
        return view(
            'superadmin.teacher-branch-assignments.create',
            [
                'teachers' => $this->teacherOptions(),
                'branches' => $this->branchOptions(),
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
            'teacher.user',
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
        return view(
            'superadmin.teacher-branch-assignments.edit',
            [
                'assignment' => $teacherBranchAssignment,
                'teachers' => $this->teacherOptions($teacherBranchAssignment->teacher_id),
                'branches' => $this->branchOptions($teacherBranchAssignment->branch_id),
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

    private function teacherOptions(?string $selectedId = null)
    {
        return Teacher::query()
            ->with('user')
            ->where(function ($query) use ($selectedId) {
                $query->where('is_active', true);

                if ($selectedId !== null) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->get()
            ->sortBy(function (Teacher $teacher) {
                return strtolower((string) (
                    $teacher->user?->full_name
                    ?: $teacher->user?->username
                    ?: $teacher->id
                ));
            })
            ->values();
    }

    private function branchOptions(?string $selectedId = null)
    {
        return Branch::query()
            ->where(function ($query) use ($selectedId) {
                $query->where('is_active', true);

                if ($selectedId !== null) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
