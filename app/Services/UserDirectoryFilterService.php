<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserDirectoryFilterService
{
    public function filters(Request $request): array
    {
        $roles = $request->user()->role_code === 'superadmin'
            ? ['superadmin', 'admin', 'teacher', 'student'] : ['student'];
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role_code' => ['nullable', Rule::in($roles)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        return ['q' => trim($data['q'] ?? ''), 'role_code' => $data['role_code'] ?? '',
            'status' => $data['status'] ?? ''];
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
