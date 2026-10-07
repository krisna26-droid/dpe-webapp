<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreClassGroupMembershipRequest;
use App\Http\Requests\SuperAdmin\UpdateClassGroupMembershipRequest;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\Student;
use App\Services\ClassGroupMembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ClassGroupMembershipController extends Controller
{
    public function index(): View
    {
        $memberships = ClassGroupMembership::query()
            ->with([
                'classGroup.branch',
                'classGroup.program',
                'student',
            ])
            ->orderByDesc('starts_on')
            ->paginate(15);

        return view(
            'superadmin.class-group-memberships.index',
            compact('memberships')
        );
    }

    public function create(): View
    {
        return view('superadmin.class-group-memberships.create', [
            'classGroups' => ClassGroup::query()
                ->where('is_active', true)
                ->with(['branch', 'program'])
                ->orderBy('code')
                ->get(),

            'students' => Student::query()
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(),
        ]);
    }

    public function store(
        StoreClassGroupMembershipRequest $request,
        ClassGroupMembershipService $service
    ): RedirectResponse {
        $membership = $service->create($request->validated());

        return redirect()
            ->route(
                'superadmin.class-group-memberships.show',
                $membership
            )
            ->with('success', 'Class group membership berhasil dibuat.');
    }

    public function show(
        ClassGroupMembership $classGroupMembership
    ): View {
        $classGroupMembership->load([
            'classGroup.branch',
            'classGroup.program',
            'classGroup.defaultTeacher',
            'student',
        ]);

        return view(
            'superadmin.class-group-memberships.show',
            compact('classGroupMembership')
        );
    }

    public function edit(
        ClassGroupMembership $classGroupMembership
    ): View {
        return view('superadmin.class-group-memberships.edit', [
            'classGroupMembership' => $classGroupMembership,

            'classGroups' => ClassGroup::query()
                ->where('is_active', true)
                ->orWhere('id', $classGroupMembership->class_group_id)
                ->with(['branch', 'program'])
                ->orderBy('code')
                ->get(),

            'students' => Student::query()
                ->where('status', 'active')
                ->orWhere('id', $classGroupMembership->student_id)
                ->orderBy('full_name')
                ->get(),
        ]);
    }

    public function update(
        UpdateClassGroupMembershipRequest $request,
        ClassGroupMembership $classGroupMembership,
        ClassGroupMembershipService $service
    ): RedirectResponse {
        $service->update(
            $classGroupMembership,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.class-group-memberships.show',
                $classGroupMembership
            )
            ->with('success', 'Class group membership berhasil diperbarui.');
    }
}