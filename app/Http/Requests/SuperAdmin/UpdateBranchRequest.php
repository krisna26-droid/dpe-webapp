<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'superadmin'
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        /** @var Branch|null $branch */
        $branch = $this->route('branch');

        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'code')
                    ->ignore($branch?->id),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'timezone_name' => [
                'required',
                'string',
                'max:255',
                Rule::in(\DateTimeZone::listIdentifiers()),
            ],

            'payment_recap_day' => [
                'required',
                'integer',
                'between:1,31',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(trim((string) $this->input('code'))),
            ]);
        }

        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }

        if ($this->has('timezone_name')) {
            $this->merge([
                'timezone_name' => trim(
                    (string) $this->input('timezone_name')
                ),
            ]);
        }
    }
}