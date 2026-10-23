<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreClassGroupRequest;
use App\Http\Requests\SuperAdmin\UpdateClassGroupRequest;
use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\Program;
use App\Models\Teacher;
use App\Services\ClassGroupFilterService;
use App\Services\ClassGroupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClassGroupController extends Controller
{
    public function index(Request $request, ClassGroupFilterService $filters): View
    {
        return view('superadmin.class-groups.index', $filters->listing($request));
    }

    public function create(): View
    {
        return view('superadmin.class-groups.create', [
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),

            'programs' => Program::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),

            'teachers' => $this->teacherOptions(),
        ]);
    }

    public function store(
        StoreClassGroupRequest $request,
        ClassGroupService $service
    ): RedirectResponse {
        $classGroup = $service->create(
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.class-groups.show',
                $classGroup
            )
            ->with(
                'success',
                'Class group berhasil dibuat.'
            );
    }

    public function show(
        ClassGroup $classGroup
    ): View {
        $classGroup->load([
            'branch',
            'program',
            'defaultTeacher.user',
            'memberships.student.portalUser',
        ]);

        return view('superadmin.class-groups.show', [
            'classGroup' => $classGroup,
        ]);
    }

    public function edit(
        ClassGroup $classGroup
    ): View {
        return view('superadmin.class-groups.edit', [
            'classGroup' => $classGroup,

            'branches' => Branch::query()
                ->where('is_active', true)
                ->orWhere('id', $classGroup->branch_id)
                ->orderBy('name')
                ->get(),

            'programs' => Program::query()
                ->where('is_active', true)
                ->orWhere('id', $classGroup->program_id)
                ->orderBy('name')
                ->get(),

            'teachers' => $this->teacherOptions(
                $classGroup->default_teacher_id
            ),
        ]);
    }

    public function update(
        UpdateClassGroupRequest $request,
        ClassGroup $classGroup,
        ClassGroupService $service
    ): RedirectResponse {
        $service->update(
            $classGroup,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.class-groups.show',
                $classGroup
            )
            ->with(
                'success',
                'Class group berhasil diperbarui.'
            );
    }

    public function toggleStatus(
        ClassGroup $classGroup,
        ClassGroupService $service
    ): RedirectResponse {
        $service->toggleStatus($classGroup);

        return redirect()
            ->route(
                'superadmin.class-groups.show',
                $classGroup
            )
            ->with(
                'success',
                $classGroup->is_active
                    ? 'Class group berhasil diaktifkan.'
                    : 'Class group berhasil dinonaktifkan.'
            );
    }

    /**
     * Return active teachers sorted by the related portal user's display name.
     * The teachers table stores user_id, not full_name.
     */
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
                return mb_strtolower(
                    $teacher->user?->full_name
                        ?: ($teacher->user?->username ?: $teacher->id)
                );
            })
            ->values();
    }
}
