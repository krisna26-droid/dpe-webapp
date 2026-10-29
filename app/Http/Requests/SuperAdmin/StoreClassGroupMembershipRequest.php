<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassGroupMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'superadmin'
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'select_learning_sessions' => ['sometimes', 'boolean'],
            'learning_session_ids' => ['required_if:select_learning_sessions,1', 'array', 'min:1'],
            'learning_session_ids.*' => ['required', 'string', 'distinct', 'max:40'],
            'session_effective_on' => ['nullable', 'date_format:Y-m-d'],
            'class_group_id' => [
                'required',
                'string',
                Rule::exists('class_groups', 'id'),
            ],

            'student_id' => [
                'required',
                'string',
                Rule::exists('students', 'id'),
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
