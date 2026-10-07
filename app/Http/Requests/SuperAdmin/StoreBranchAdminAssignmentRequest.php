<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchAdminAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'superadmin'
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'branch_id' => [
                'required',
                'string',
                Rule::exists('branches', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],

            'admin_user_id' => [
                'required',
                'string',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('role_code', 'admin')
                        ->where('is_active', true)),
            ],

            'starts_on' => [
                'required',
                'date',
            ],

            'ends_on' => [
                'nullable',
                'date',
                'after_or_equal:starts_on',
            ],
        ];
    }
}