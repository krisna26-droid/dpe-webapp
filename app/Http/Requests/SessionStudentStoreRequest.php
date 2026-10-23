<?php

namespace App\Http\Requests;

use App\Services\StudentAttendanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SessionStudentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()?->role_code === 'superadmin';
    }

    public function rules(): array
    {
        return [
            'session_id' => [
                'required',
                'uuid',
                'exists:lesson_sessions,id',
            ],
            'student_id' => [
                'required',
                'uuid',
                'exists:students,id',
            ],
            'attendance_status' => [
                'nullable',
                'string',
                Rule::in(array_keys(StudentAttendanceService::STATUSES)),
            ],
            'individual_learning_note' => [
                'nullable',
                'string',
                'max:255',
            ],
            'recorded_at' => [
                'nullable',
                'date',
            ],
            'correction_reason' => ['nullable', 'string', 'max:1000'],
            'expected_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }
}
