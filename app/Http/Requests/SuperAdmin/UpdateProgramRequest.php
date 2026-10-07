<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'superadmin'
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        $program = $this->route('program');

        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'code')
                    ->ignore(
                        $program instanceof Program
                            ? $program->id
                            : $program
                    ),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'class_type' => [
                'required',
                'string',
                'max:255',
            ],

            'monthly_video_target_override' => [
                'nullable',
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