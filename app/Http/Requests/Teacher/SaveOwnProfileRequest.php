<?php

namespace App\Http\Requests\Teacher;

use App\Services\OwnAccountService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOwnProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) { return false; }
        $service = app(OwnAccountService::class);
        $actor = $service->actor($this->user(), 'teacher');
        $service->teacher($actor, active: true);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['full_name', 'email', 'whatsapp_number'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) { $values[$field] = $field === 'email' ? strtolower(trim($value)) : trim($value); }
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['bail', 'required', 'string', 'max:255'],
            'email' => ['bail', 'nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id, 'id')],
            'whatsapp_number' => ['bail', 'required', 'string', 'max:32', 'regex:/^\+?[0-9][0-9 ()-]{5,30}[0-9]$/D',
                function ($field, $value, $fail) { $length = strlen(preg_replace('/\D/', '', $value)); if ($length < 8 || $length > 15) { $fail('Nomor WhatsApp harus memuat 8–15 angka.'); } }],
            'photo' => ['bail', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'record_version' => ['required', 'string', 'size:64'],
            'user_id' => ['prohibited'], 'teacher_id' => ['prohibited'], 'role_code' => ['prohibited'],
            'username' => ['prohibited'], 'is_active' => ['prohibited'], 'photo_file_id' => ['prohibited'], 'password_hash' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['whatsapp_number.regex' => 'Isi nomor WhatsApp yang valid, misalnya 081234567890 atau +6281234567890.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.', 'photo.mimes' => 'Gunakan foto JPG, PNG, atau WebP.',
            'photo.extensions' => 'Nama file foto harus berakhiran JPG, PNG, atau WebP.',
            'photo.dimensions' => 'Ukuran foto maksimal 4096 × 4096 piksel.'];
    }
}
