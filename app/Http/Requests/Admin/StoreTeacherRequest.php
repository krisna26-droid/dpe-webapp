<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check()
            && auth()->user()->role_code === 'admin';
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')
                    ->where('role_code', 'teacher')
                    ->where('is_active', 1),
                Rule::unique('teachers', 'user_id'),
            ],

            'whatsapp_number' => [
                'required',
                'string',
                'max:255',
            ],

            'photo_file_id' => [
                'nullable',
                'uuid',
                Rule::exists('file_assets', 'id'),
            ],

            'photo' => [
                'nullable',
                'image',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' =>
                'Akun guru wajib dipilih.',

            'user_id.uuid' =>
                'Akun guru yang dipilih tidak valid.',

            'user_id.exists' =>
                'Akun harus aktif dan memiliki role teacher.',

            'user_id.unique' =>
                'Akun ini sudah terhubung dengan profil guru lain.',

            'whatsapp_number.required' =>
                'Nomor WhatsApp wajib diisi.',

            'whatsapp_number.max' =>
                'Nomor WhatsApp maksimal 255 karakter.',

            'photo_file_id.exists' =>
                'File foto yang dipilih tidak ditemukan.',
        ];
    }
}
