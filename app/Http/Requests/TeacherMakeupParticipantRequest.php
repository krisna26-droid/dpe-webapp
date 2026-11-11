<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeacherMakeupParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()?->role_code === 'teacher';
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'expected_roster' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ];
    }
}
