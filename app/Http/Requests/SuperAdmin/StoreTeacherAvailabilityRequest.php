<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'superadmin'
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'teacher_id' => [
                'required',
                'string',
                Rule::exists('teachers', 'id'),
            ],
            'branch_id' => [
                'required',
                'string',
                Rule::exists('branches', 'id'),
            ],
            'available_on' => [
                'required',
                'date',
            ],
            'starts_at' => [
                'required',
                'date_format:H:i:s',
            ],
            'ends_at' => [
                'required',
                'date_format:H:i:s',
                'after:starts_at',
            ],
            'class_type' => [
                'required',
                'string',
                'max:255',
            ],
            'availability_status' => [
                'required',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
