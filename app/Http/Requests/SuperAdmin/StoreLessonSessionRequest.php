<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonSessionRequest extends FormRequest
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
                Rule::exists('branches', 'id'),
            ],

            'teacher_id' => [
                'required',
                'string',
                Rule::exists('teachers', 'id'),
            ],

            'program_id' => [
                'required',
                'string',
                Rule::exists('programs', 'id'),
            ],

            'class_group_id' => [
                'nullable',
                'string',
                Rule::exists('class_groups', 'id'),
            ],

            'planned_start_at' => [
                'required',
                'date',
            ],

            'planned_end_at' => [
                'required',
                'date',
                'after:planned_start_at',
            ],

            'actual_start_at' => [
                'nullable',
                'date',
            ],

            'actual_end_at' => [
                'nullable',
                'date',
                'after_or_equal:actual_start_at',
            ],

            'status' => [
                'required',
                'string',
                'max:255',
            ],

            'rescheduled_from_session_id' => [
                'nullable',
                'string',
                Rule::exists('lesson_sessions', 'id'),
            ],

            'topic' => [
                'nullable',
                'string',
                'max:255',
            ],

            'material' => [
                'nullable',
                'string',
                'max:255',
            ],

            'activity' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}