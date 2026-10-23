<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassGroupFilterService
{
    public function __construct(private BranchOperationsService $operations) {}

    public function listing(Request $request): array
    {
        $actor = $request->user();
        $this->operations->actor($actor);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['bail', 'nullable', 'string', 'max:255', Rule::exists('branches', 'id')],
            'class_group_id' => ['bail', 'nullable', 'string', 'max:255', Rule::exists('class_groups', 'id')],
            'program_id' => ['bail', 'nullable', 'string', 'max:255', Rule::exists('programs', 'id')],
            'teacher_id' => ['bail', 'nullable', 'string', 'max:255', Rule::exists('teachers', 'id')],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        if (! empty($filters['branch_id'])) {
            $this->operations->authorizeBranch($actor, $filters['branch_id']);
        }

        // Options use the authorized scope, not the filtered result, so empty
        // combinations retain their selections and can be adjusted or reset.
        $scope = $this->operations->groups($actor);
        $groups = (clone $scope)->with('branch')->orderBy('name')->orderBy('code')->get();
        $programs = Program::whereIn('id', (clone $scope)->select('program_id'))->orderBy('name')->get();
        $teachers = Teacher::whereIn('id', (clone $scope)->select('default_teacher_id'))->with('user')->get()
            ->sortBy(fn ($teacher) => mb_strtolower($teacher->user?->full_name ?: $teacher->user?->username ?: $teacher->id))->values();
        if ($actor->role_code === 'admin') {
            foreach (['class_group_id' => $groups, 'program_id' => $programs, 'teacher_id' => $teachers] as $field => $options) {
                if (! empty($filters[$field])) {
                    abort_unless($options->contains('id', $filters[$field]), 403);
                }
            }
        }

        $search = $filters['q'] ?? '';
        $classGroups = $scope->with(['branch', 'program', 'defaultTeacher.user'])->withCount('memberships')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($filters['class_group_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($filters['program_id'] ?? null, fn ($query, $id) => $query->where('program_id', $id))
            ->when($filters['teacher_id'] ?? null, fn ($query, $id) => $query->where('default_teacher_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('branch_id')->orderBy('code')->paginate(15)->withQueryString();
        $prefix = $request->routeIs('admin.*') ? 'admin' : 'superadmin';

        return ['classGroups' => $classGroups, 'filters' => $filters,
            'hasClassGroupFilters' => collect($filters)->contains(fn ($value) => $value !== null && $value !== ''),
            'operationRoutePrefix' => $prefix, 'classGroupFilterOptions' => [
                'showBranch' => $actor->role_code === 'superadmin',
                'branches' => $this->operations->branches($actor)->orderBy('name')->get(),
                'groups' => $groups, 'programs' => $programs, 'teachers' => $teachers,
                'action' => route($prefix.'.class-groups.index'),
            ]];
    }
}
