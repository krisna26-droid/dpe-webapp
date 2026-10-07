<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SessionStudentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            ],
            'individual_learning_note' => [
                'nullable',
                'string',
            ],
            'recorded_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
