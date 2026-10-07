<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreBranchAdminAssignmentRequest;
use App\Http\Requests\SuperAdmin\UpdateBranchAdminAssignmentRequest;
use App\Models\BranchAdminAssignment;
use App\Services\BranchAdminAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BranchAdminAssignmentController extends Controller
{
    public function index(): View
    {
        $assignments = BranchAdminAssignment::query()
            ->with(['branch', 'admin'])
            ->orderByDesc('starts_on')
            ->paginate(15);

        return view('superadmin.branch-admin-assignments.index', [
            'assignments' => $assignments,
        ]);
    }

    public function create(): View
    {
        return view('superadmin.branch-admin-assignments.create', [
            'branches' => \App\Models\Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),

            'admins' => \App\Models\User::query()
                ->where('role_code', 'admin')
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(),
        ]);
    }

    public function store(
        StoreBranchAdminAssignmentRequest $request,
        BranchAdminAssignmentService $service
    ): RedirectResponse {
        $service->create($request->validated());

        return redirect()
            ->route('superadmin.branch-admin-assignments.index')
            ->with('success', 'Assignment admin branch berhasil dibuat.');
    }

    public function show(BranchAdminAssignment $branchAdminAssignment): View
    {
        $branchAdminAssignment->load([
            'branch',
            'admin',
        ]);

        return view('superadmin.branch-admin-assignments.show', [
            'assignment' => $branchAdminAssignment,
        ]);
    }

    public function edit(
        BranchAdminAssignment $branchAdminAssignment
    ): View {
        return view('superadmin.branch-admin-assignments.edit', [
            'assignment' => $branchAdminAssignment,

            'branches' => \App\Models\Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),

            'admins' => \App\Models\User::query()
                ->where('role_code', 'admin')
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(),
        ]);
    }

    public function update(
        UpdateBranchAdminAssignmentRequest $request,
        BranchAdminAssignment $branchAdminAssignment,
        BranchAdminAssignmentService $service
    ): RedirectResponse {
        $service->update(
            $branchAdminAssignment,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.branch-admin-assignments.show',
                $branchAdminAssignment
            )
            ->with('success', 'Assignment admin branch berhasil diperbarui.');
    }

    public function destroy(
        BranchAdminAssignment $branchAdminAssignment,
        BranchAdminAssignmentService $service
    ): RedirectResponse {
        $service->delete($branchAdminAssignment);

        return redirect()
            ->route('superadmin.branch-admin-assignments.index')
            ->with('success', 'Assignment admin branch berhasil dihapus.');
    }
}