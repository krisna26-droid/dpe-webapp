<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentAttendanceRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && in_array($this->user()?->role_code, ['admin', 'superadmin'], true);
    }

    public function rules(): array
    {
        return [
            'student_ids' => ['nullable', 'array', 'max:1000'],
            'student_ids.*' => ['required', 'uuid', 'distinct', 'exists:students,id'],
            'expected_roster' => ['required', 'string', 'size:64'],
        ];
    }
}
