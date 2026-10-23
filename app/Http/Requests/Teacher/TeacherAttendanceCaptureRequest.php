<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class TeacherAttendanceCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['teacher', 'superadmin'], true)
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
            ],
            'location_status' => [
                'required',
                'string',
                'in:captured,unavailable',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
                'required_if:location_status,captured',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
                'required_if:location_status,captured',
            ],
            'accuracy' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100000',
            ],
            'location_note' => [
                'nullable',
                'string',
                'max:500',
                'required_if:location_status,unavailable',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'photo' => 'foto absensi',
            'location_status' => 'status lokasi',
            'latitude' => 'latitude lokasi',
            'longitude' => 'longitude lokasi',
            'accuracy' => 'akurasi lokasi',
            'location_note' => 'alasan lokasi tidak tersedia',
        ];
    }
}
