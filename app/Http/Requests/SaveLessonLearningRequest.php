<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveLessonLearningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && in_array($this->user()?->role_code, ['teacher', 'admin', 'superadmin'], true);
    }

    public function rules(): array
    {
        return [
            'expected_snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'topic' => ['present', 'nullable', 'string', 'max:255'],
            'material' => ['present', 'nullable', 'string', 'max:255'],
            'activity' => ['present', 'nullable', 'string', 'max:255'],
            'student_notes' => ['sometimes', 'array'],
            'student_notes.*' => ['nullable', 'string', 'max:255'],
            'correction_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
