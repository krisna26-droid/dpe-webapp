<?php

namespace App\Http\Requests;

use App\Services\OwnAccountService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangeOwnPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) { return false; }
        $role = explode('.', $this->route()->getName())[0];
        app(OwnAccountService::class)->actor($this->user(), $role);

        return in_array($role, ['teacher', 'student'], true);
    }

    public function rules(): array
    {
        return [
            'current_password' => ['bail', 'required', 'string', 'max:255'],
            'password' => ['bail', 'required', 'string', 'max:72', 'confirmed', Password::min(8)->letters()->numbers(),
                function ($field, $value, $fail) { if (strlen($value) > 72) { $fail('Kata sandi maksimal 72 byte. Gunakan kombinasi huruf dan angka yang lebih pendek.'); } }],
            'password_confirmation' => ['required', 'string', 'max:72'],
            'user_id' => ['prohibited'], 'teacher_id' => ['prohibited'], 'role_code' => ['prohibited'], 'username' => ['prohibited'],
            'is_active' => ['prohibited'], 'password_hash' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['current_password.required' => 'Isi kata sandi lama.', 'password.confirmed' => 'Konfirmasi kata sandi baru belum sama.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.', 'password.max' => 'Kata sandi baru maksimal 72 karakter.'];
    }
}
