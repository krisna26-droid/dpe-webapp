<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTeacherAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['admin', 'superadmin'], true)
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(['approved', 'needs_correction']),
            ],
            'review_note' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:status,needs_correction',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'hasil pemeriksaan',
            'review_note' => 'catatan pemeriksaan',
        ];
    }
}
