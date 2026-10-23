<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'admin' && $this->user()?->is_active;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['full_name', 'username', 'email', 'school_name', 'grade_name'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $values[$field] = $value === '' ? null : ($field === 'email' ? mb_strtolower($value) : $value);
            }
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'program_id' => ['required', 'string', Rule::exists('programs', 'id')->where('is_active', true)],
            'began_on' => ['required', 'date_format:Y-m-d'],
            'school_name' => ['nullable', 'string', 'max:255'],
            'grade_name' => ['nullable', 'string', 'max:255'],
            // Authorization of the selected branch is repeated inside the write transaction.
            'branch_id' => ['nullable', 'string', 'max:36'],
            'role_code' => ['sometimes', Rule::in(['student'])],
            'is_active' => ['sometimes', Rule::in([true, 1, '1'])],
            'id' => ['prohibited'],
            'portal_user_id' => ['prohibited'],
            'student_id' => ['prohibited'],
            'ends_on' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_code.in' => 'Admin Cabang hanya dapat membuat akun siswa.',
            'is_active.in' => 'Akun siswa baru dibuat dalam keadaan aktif.',
            'program_id.exists' => 'Pilih program belajar yang masih aktif.',
            'password.confirmed' => 'Konfirmasi password belum sama.',
            'username.unique' => 'Username sudah digunakan. Pilih username lain.',
            'email.unique' => 'Email sudah digunakan akun lain.',
        ];
    }
}
