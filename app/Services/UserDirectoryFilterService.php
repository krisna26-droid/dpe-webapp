<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserDirectoryFilterService
{
    public function __construct(private StudentAccountDirectoryService $students) {}

    public function filters(Request $request, bool $studentsOnly = false): array
    {
        $global = $request->user()->role_code === 'superadmin';
        $roles = $global && ! $studentsOnly
            ? ['superadmin', 'admin', 'teacher', 'student'] : ['student'];
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role_code' => ['nullable', Rule::in($roles)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'branch_id' => $global ? ['nullable', 'uuid'] : ['prohibited'],
            'program_id' => ['nullable', 'uuid'],
            'teacher_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $filters = ['q' => trim($data['q'] ?? ''), 'role_code' => $data['role_code'] ?? '',
            'status' => $data['status'] ?? '', 'branch_id' => $global ? ($data['branch_id'] ?? '') : '',
            'program_id' => $data['program_id'] ?? '', 'teacher_id' => $data['teacher_id'] ?? ''];
        if ($filters['branch_id'] !== '' || $filters['program_id'] !== '' || $filters['teacher_id'] !== '') {
            if (! in_array($filters['role_code'], ['', 'student'], true)) {
                throw ValidationException::withMessages(['role_code' => 'Filter cabang, program, dan guru utama hanya berlaku untuk akun siswa. Pilih Semua role atau Siswa / Parent.']);
            }
            if ($global) $filters['role_code'] = 'student';
        }

        return $filters;
    }

    public function query(User $user, array $filters, bool $studentsOnly = false): Builder
    {
        $actor = $this->students->actor($user);
        $query = $this->apply($this->students->baseQuery($actor), $filters);
        if ($studentsOnly) $query->where('role_code', 'student');

        return $this->students->apply($query, $actor, $filters);
    }

    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($search) => $search
                ->where('full_name', 'like', '%'.$filters['q'].'%')
                ->orWhere('username', 'like', '%'.$filters['q'].'%')
                ->orWhere('email', 'like', '%'.$filters['q'].'%')))
            ->when($filters['role_code'] !== '', fn ($q) => $q->where('role_code', $filters['role_code']))
            ->when($filters['status'] !== '', fn ($q) => $q->where('is_active', $filters['status'] === 'active'));
    }
}
