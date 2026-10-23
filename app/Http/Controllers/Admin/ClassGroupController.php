<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BranchClassGroupRequest;
use App\Models\ClassGroup;
use App\Services\BranchOperationsService;
use App\Services\ClassGroupFilterService;
use App\Services\ClassGroupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassGroupController extends Controller
{
    public function __construct(private BranchOperationsService $operations, private ClassGroupService $groups) {}

    public function index(Request $request, ClassGroupFilterService $filters): View
    {
        return view('superadmin.class-groups.index', $filters->listing($request));
    }

    public function create(Request $request): View
    {
        $options = $this->operations->options($request->user());
        abort_if($options['branches']->isEmpty(), 403, 'Belum ada cabang aktif yang dapat Anda kelola.');

        return view('superadmin.class-groups.create', $options);
    }

    public function store(BranchClassGroupRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $group = DB::transaction(function () use ($request, $data) {
            $this->operations->validateTeacher($request->user(), $data);

            return $this->groups->create($data);
        });

        return redirect()->route('admin.class-groups.show', $group)->with('success', 'Kelompok kelas berhasil ditambahkan.');
    }

    public function show(Request $request, ClassGroup $classGroup): View
    {
        $this->operations->authorize($request->user(), $classGroup);
        $classGroup->load(['branch', 'program', 'defaultTeacher.user', 'memberships.student.portalUser']);

        return view('superadmin.class-groups.show', ['classGroup' => $classGroup, 'operationRoutePrefix' => 'admin',
            'recordVersion' => $this->operations->version($classGroup)]);
    }

    public function edit(Request $request, ClassGroup $classGroup): View
    {
        $this->operations->authorize($request->user(), $classGroup);

        return view('superadmin.class-groups.edit', ['classGroup' => $classGroup] + $this->operations->options($request->user(), $classGroup));
    }

    public function update(BranchClassGroupRequest $request, ClassGroup $classGroup): RedirectResponse
    {
        DB::transaction(function () use ($request, $classGroup) {
            $current = ClassGroup::whereKey($classGroup->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            $this->operations->authorize($request->user(), $current);
            $this->operations->checkVersion($current, $data);
            $this->operations->validateTeacher($request->user(), $data, $current);
            $this->groups->update($current, $data);
        });

        return redirect()->route('admin.class-groups.show', $classGroup)->with('success', 'Kelompok kelas berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, ClassGroup $classGroup): RedirectResponse
    {
        $this->operations->authorize($request->user(), $classGroup);
        $data = $request->validate(['record_version' => ['required', 'string', 'size:64']]);
        DB::transaction(function () use ($request, $classGroup, $data) {
            $current = ClassGroup::whereKey($classGroup->id)->lockForUpdate()->firstOrFail();
            $this->operations->authorize($request->user(), $current);
            $this->operations->checkVersion($current, $data);
            $this->groups->toggleStatus($current);
        });

        return redirect()->route('admin.class-groups.show', $classGroup)->with('success', 'Status kelompok kelas berhasil diperbarui.');
    }
}
