<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BranchClassGroupMembershipRequest;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Services\BranchOperationsService;
use App\Services\ClassGroupMembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClassGroupMembershipController extends Controller
{
    public function __construct(private BranchOperationsService $operations, private ClassGroupMembershipService $memberships) {}

    public function index(Request $request): View
    {
        $context = $this->operations->filters($request, 'memberships');
        $f = $context['filters'];
        $search = $f['q'] ?? '';
        $memberships = $this->operations->memberships($request->user())->with(['classGroup.branch', 'classGroup.program', 'student'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->whereHas('student', fn ($s) => $s->where('full_name', 'like', '%'.$search.'%'))
                ->orWhereHas('classGroup', fn ($g) => $g->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'))))
            ->whereHas('classGroup', fn ($q) => $q
                ->when($f['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
                ->when($f['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
                ->when($f['teacher_id'] ?? null, fn ($q, $id) => $q->where('default_teacher_id', $id)))
            ->when($f['status'] ?? null, function ($q, $status) {
                if ($status === 'active') {
                    $q->whereDate('starts_on', '<=', today())->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
                } else {
                    $q->where(fn ($q) => $q->whereDate('starts_on', '>', today())->orWhereDate('ends_on', '<', today()));
                }
            })
            ->orderByDesc('starts_on')->orderBy('id')->paginate(15)->withQueryString();

        return view('superadmin.class-group-memberships.index', $context + compact('memberships'));
    }

    public function create(Request $request): View
    {
        $selected = $request->validate(['class_group_id' => ['nullable', 'string', Rule::exists('class_groups', 'id')]])['class_group_id'] ?? null;
        if ($selected) { $this->operations->authorize($request->user(), ClassGroup::findOrFail($selected)); }

        return view('superadmin.class-group-memberships.create', $this->options($request));
    }

    public function store(BranchClassGroupMembershipRequest $request): RedirectResponse
    {
        $membership = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $this->validateReferences($request, $data);

            return $this->memberships->create($data);
        });

        return redirect()->route('admin.class-group-memberships.show', $membership)->with('success', 'Anggota kelas berhasil ditambahkan.');
    }

    public function show(Request $request, ClassGroupMembership $classGroupMembership): View
    {
        $this->operations->authorize($request->user(), $classGroupMembership);
        $classGroupMembership->load(['classGroup.branch', 'classGroup.program', 'classGroup.defaultTeacher.user', 'student.portalUser']);

        return view('superadmin.class-group-memberships.show', compact('classGroupMembership') + ['operationRoutePrefix' => 'admin']);
    }

    public function edit(Request $request, ClassGroupMembership $classGroupMembership): View
    {
        $this->operations->authorize($request->user(), $classGroupMembership);

        return view('superadmin.class-group-memberships.edit', compact('classGroupMembership') + $this->options($request, $classGroupMembership));
    }

    public function update(BranchClassGroupMembershipRequest $request, ClassGroupMembership $classGroupMembership): RedirectResponse
    {
        DB::transaction(function () use ($request, $classGroupMembership) {
            $current = ClassGroupMembership::whereKey($classGroupMembership->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            $this->operations->authorize($request->user(), $current);
            $this->operations->checkVersion($current, $data);
            $this->validateReferences($request, $data);
            $this->memberships->update($current, $data);
        });

        return redirect()->route('admin.class-group-memberships.show', $classGroupMembership)->with('success', 'Keanggotaan kelas berhasil diperbarui.');
    }

    private function validateReferences(Request $request, array $data): void
    {
        $group = ClassGroup::whereKey($data['class_group_id'])->lockForUpdate()->firstOrFail();
        $this->operations->authorize($request->user(), $group);
        abort_unless($this->operations->students($request->user())->whereKey($data['student_id'])->exists(), 403);
    }

    private function options(Request $request, ?ClassGroupMembership $current = null): array
    {
        $groups = $this->operations->groups($request->user())->where(fn ($q) => $q->where('is_active', true)
            ->when($current, fn ($q) => $q->orWhere('id', $current->class_group_id)))->with(['branch', 'program'])->orderBy('name')->get();
        $students = $this->operations->students($request->user())->where(fn ($q) => $q->where('status', 'active')
            ->when($current, fn ($q) => $q->orWhere('id', $current->student_id)))->with('enrollments')->orderBy('full_name')->get();
        $studentGroups = [];
        foreach ($students as $student) {
            $studentGroups[$student->id] = $groups->filter(fn ($group) => $student->enrollments->contains(
                fn ($e) => $e->branch_id === $group->branch_id && $e->program_id === $group->program_id
                    && ($e->ends_on === null || $e->ends_on->gte($student->began_on))))->pluck('id')->all();
        }
        if ($current && $students->contains('id', $current->student_id)) {
            $studentGroups[$current->student_id] = array_values(array_unique([...($studentGroups[$current->student_id] ?? []), $current->class_group_id]));
        }

        return ['classGroups' => $groups, 'students' => $students, 'studentGroups' => $studentGroups,
            'operationRoutePrefix' => 'admin', 'recordVersion' => $current ? $this->operations->version($current) : null];
    }
}
