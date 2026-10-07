<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class UpdateStudentTeacherAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'superadmin'
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'string',
                Rule::exists('students', 'id'),
            ],

            'teacher_id' => [
                'required',
                'string',
                Rule::exists('teachers', 'id'),
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

            'is_report_owner' => [
                'boolean',
            ],
        ];
    }
}