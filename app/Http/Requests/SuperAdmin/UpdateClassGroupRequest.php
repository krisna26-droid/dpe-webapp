<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'superadmin'
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        $classGroupId = $this->route('class_group')?->id;

        return [
            'branch_id' => [
                'required',
                'string',
                Rule::exists('branches', 'id'),
            ],

            'program_id' => [
                'required',
                'string',
                Rule::exists('programs', 'id'),
            ],

            'default_teacher_id' => [
                'nullable',
                'string',
                Rule::exists('teachers', 'id'),
            ],

            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('class_groups', 'code')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'branch_id',
                                $this->input('branch_id')
                            )
                    )
                    ->ignore($classGroupId),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}