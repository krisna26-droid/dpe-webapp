<?php

namespace App\Http\Requests\LearningVideo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLearningVideoRequest extends FormRequest
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

            'video_on' => [
                'required',
                'date_format:Y-m-d',
            ],

            'topic' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'video_url' => [
                'required',
                'string',
            ],
        ];
    }
}
