<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonLearningIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && in_array($this->user()?->role_code, ['teacher', 'admin', 'superadmin'], true);
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'branch_id' => ['nullable', 'uuid'],
            'class_group_id' => ['nullable', 'string', 'max:36'],
            'completion' => ['nullable', Rule::in(['complete', 'pending'])],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
