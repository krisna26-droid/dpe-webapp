<?php

namespace App\Http\Requests\LearningSkill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLearningSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check()
            && auth()->user()->role_code === 'superadmin'
            && auth()->user()->is_active;
    }

    public function rules(): array
    {
        $learningSkillId = $this->route('learning_skill')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('learning_skills', 'code')
                    ->ignore($learningSkillId),
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
