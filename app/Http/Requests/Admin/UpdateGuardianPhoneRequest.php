<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuardianPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['admin', 'superadmin'], true)
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'whatsapp_number' => [
                'required',
                'string',
                'max:30',
                'regex:/^\+?[0-9][0-9\s().-]{6,28}[0-9}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'whatsapp_number.required' => 'Isi nomor telepon atau WhatsApp wali.',
            'whatsapp_number.regex' => 'Masukkan nomor telepon wali yang valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'whatsapp_number' => $this->whatsapp_number !== null
                ? trim((string) $this->whatsapp_number)
                : null,
        ]);
    }
}
