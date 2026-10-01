<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'superadmin';
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'username' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'full_name' => [
                'required',
                'string',
                'max:255',
            ],

            'role_code' => [
                'required',
                Rule::in([
                    'admin',
                    'teacher',
                    'student',
                ]),
            ],

            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => $this->username !== null
                ? trim($this->username)
                : null,

            'email' => $this->email !== null
                ? strtolower(trim($this->email))
                : null,

            'full_name' => $this->full_name !== null
                ? trim($this->full_name)
                : null,
        ]);
    }
}