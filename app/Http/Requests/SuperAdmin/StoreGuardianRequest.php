<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Guardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_code === 'superadmin'
            && $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'string',
                Rule::exists('students', 'id'),
            ],

            'full_name' => [
                'required',
                'string',
                'max:255',
            ],

            'relationship_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'whatsapp_number' => [
                'required',
                'string',
                'max:255',
            ],

            'is_primary' => [
                'boolean',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        return;
                    }

                    $alreadyHasPrimary = Guardian::query()
                        ->where('student_id', $this->input('student_id'))
                        ->where('is_primary', true)
                        ->exists();

                    if ($alreadyHasPrimary) {
                        $fail(
                            'Siswa sudah memiliki penerima utama. Edit kontak tersebut jika ingin menggantinya.'
                        );
                    }
                },
            ],
        ];
    }
}
