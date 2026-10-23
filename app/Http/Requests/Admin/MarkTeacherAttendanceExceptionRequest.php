<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkTeacherAttendanceExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active
            && in_array($this->user()?->role_code, ['admin', 'superadmin'], true);
    }

    public function rules(): array
    {
        return [
            'attendance_type' => [
                'required',
                'string',
                Rule::in(['absent', 'leave', 'sick']),
            ],
            'manual_note' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:attendance_type,leave,sick',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'manual_note.required_if' => 'Catatan wajib diisi untuk izin atau sakit.',
        ];
    }
}
