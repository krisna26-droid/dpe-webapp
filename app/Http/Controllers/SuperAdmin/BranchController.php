<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreBranchRequest;
use App\Http\Requests\SuperAdmin\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\BranchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(): View
    {
        $branches = Branch::query()
            ->orderBy('name')
            ->paginate(15);

        return view('superadmin.branches.index', [
            'branches' => $branches,
        ]);
    }

    public function create(): View
    {
        return view('superadmin.branches.create');
    }

    public function store(
        StoreBranchRequest $request,
        BranchService $branchService
    ): RedirectResponse {
        $branchService->create(
            $request->validated()
        );

        return redirect()
            ->route('superadmin.branches.index')
            ->with('success', 'Branch berhasil dibuat.');
    }

    public function show(Branch $branch): View
    {
        return view('superadmin.branches.show', [
            'branch' => $branch,
        ]);
    }

    public function edit(Branch $branch): View
    {
        return view('superadmin.branches.edit', [
            'branch' => $branch,
        ]);
    }

    public function update(
        UpdateBranchRequest $request,
        Branch $branch,
        BranchService $branchService
    ): RedirectResponse {
        $branchService->update(
            $branch,
            $request->validated()
        );

        return redirect()
            ->route('superadmin.branches.show', $branch)
            ->with('success', 'Branch berhasil diperbarui.');
    }

    public function toggleStatus(
        Request $request,
        Branch $branch,
        BranchService $branchService
    ): RedirectResponse {
        $branchService->toggleStatus($branch);

        return redirect()
            ->route('superadmin.branches.show', $branch)
            ->with(
                'success',
                $branch->fresh()->is_active
                    ? 'Branch berhasil diaktifkan.'
                    : 'Branch berhasil dinonaktifkan.'
            );
    }
}