<?php

namespace App\Http\Requests\LearningSkill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLearningSkillRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('learning_skills', 'code'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'display_order' => [
                'required',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
