<?php

namespace App\Http\Requests;

use App\Services\StudentAttendanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordStudentAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && in_array($this->user()?->role_code, ['teacher', 'admin', 'superadmin'], true);
    }

    public function rules(): array
    {
        return [
            'attendance_status' => ['present', 'nullable', 'string', Rule::in(array_keys(StudentAttendanceService::STATUSES))],
            'individual_learning_note' => ['nullable', 'string', 'max:255'],
            'correction_reason' => ['nullable', 'string', 'max:1000'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
