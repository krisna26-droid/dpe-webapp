<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentAttendanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && in_array($this->user()?->role_code, ['teacher', 'admin', 'superadmin'], true);
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'date_format:Y-m'], 'month' => ['nullable', 'date_format:Y-m'],
            'branch_id' => ['nullable', 'uuid'], 'student_id' => ['nullable', 'uuid'],
            'class_group_id' => ['nullable', 'regex:/^(private|[a-fA-F0-9-]{36})$/'],
            'q' => ['nullable', 'string', 'max:80'],
        ];
    }
}
