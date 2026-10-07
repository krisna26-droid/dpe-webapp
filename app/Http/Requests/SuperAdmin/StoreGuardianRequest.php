<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuardianRequest extends FormRequest
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

            'full_name' => [
                'required',
                'string',
                'max:255',
            ],

            'relationship_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'whatsapp_number' => [
                'required',
                'string',
                'max:255',
            ],

            'is_primary' => [
                'boolean',
            ],
        ];
    }
}
