<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherAttendanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['admin', 'superadmin'], true)
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'attendance_mode' => ['nullable', Rule::in(['branch_visit', 'private_session', 'branch_day', 'legacy_session'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'string', Rule::exists('branches', 'id')],
            'teacher_id' => ['nullable', 'string', Rule::exists('teachers', 'id')],
            'status' => [
                'nullable',
                'string',
                Rule::in(['in_progress', 'pending_review', 'approved', 'needs_correction', 'recorded']),
            ],
            'attendance_type' => [
                'nullable',
                'string',
                Rule::in(['present', 'absent', 'leave', 'sick', 'unrecorded']),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('date_from') ?: now()->startOfMonth()->toDateString();
            $to = $this->input('date_to') ?: now()->toDateString();

            if (! $validator->errors()->has('date_from') && ! $validator->errors()->has('date_to') && $from > $to) {
                $validator->errors()->add(
                    'date_to',
                    'Tanggal sampai tidak boleh lebih awal dari tanggal mulai.'
                );
            }
        });
    }
}
