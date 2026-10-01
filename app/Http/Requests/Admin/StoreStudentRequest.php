<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'admin';
    }

    public function rules(): array
    {
        return [
            'portal_user_id' => [
                'required',
                'string',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('role_code', 'student')
                        ->where('is_active', true)),
                Rule::unique('students', 'portal_user_id'),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'school_name' => ['nullable', 'string', 'max:255'],
            'grade_name' => ['nullable', 'string', 'max:255'],
            'began_on' => ['required', 'date'],
            'special_notes_internal' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->full_name !== null
                ? trim($this->full_name)
                : null,
            'school_name' => $this->school_name !== null
                ? trim($this->school_name)
                : null,
            'grade_name' => $this->grade_name !== null
                ? trim($this->grade_name)
                : null,
        ]);
    }
}