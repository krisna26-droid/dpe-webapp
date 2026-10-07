<?php

namespace App\Http\Requests\StudentChallenge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentChallengeRequest extends FormRequest
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

            'category_name' => [
                'required',
                'string',
                'max:255',
            ],

            'internal_note' => [
                'nullable',
                'string',
            ],

            'logged_on' => [
                'required',
                'date_format:Y-m-d',
            ],
        ];
    }
}
