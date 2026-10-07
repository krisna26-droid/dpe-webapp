<?php

namespace App\Http\Requests\TeacherMessage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check()
            && auth()->user()->role_code === 'superadmin'
            && auth()->user()->is_active;
    }

    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'string',
                Rule::exists('students', 'id'),
            ],

            'teacher_id' => [
                'required',
                'string',
                Rule::exists('teachers', 'id'),
            ],

            'session_id' => [
                'nullable',
                'string',
                Rule::exists('lesson_sessions', 'id'),
            ],

            'message_body' => [
                'required',
                'string',
            ],

            'attachment_file_id' => [
                'nullable',
                'string',
                Rule::exists('file_assets', 'id'),
            ],
        ];
    }
}
