<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentEnrollmentRequest extends FormRequest
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

            'branch_id' => [
                'required',
                'string',
                Rule::exists('branches', 'id'),
            ],

            'program_id' => [
                'required',
                'string',
                Rule::exists('programs', 'id'),
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