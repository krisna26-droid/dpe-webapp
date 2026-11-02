<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active
            && in_array($this->user()?->role_code, ['admin', 'superadmin'], true);
    }

    public function rules(): array
    {
        return [
            'slot_capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'slot_capacity.required' => 'Isi jumlah slot siswa untuk sesi ini.',
            'slot_capacity.integer' => 'Jumlah slot harus berupa angka bulat.',
            'slot_capacity.min' => 'Jumlah slot minimal 1 siswa.',
            'slot_capacity.max' => 'Jumlah slot maksimal 500 siswa.',
        ];
    }
}
