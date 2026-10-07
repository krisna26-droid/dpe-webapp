<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRecapRunStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => [
                'required',
                'uuid',
                'exists:branches,id',
            ],

            'recap_month' => [
                'required',
                'date',
            ],

            'scheduled_on' => [
                'required',
                'date',
            ],

            'status' => [
                'required',
                'string',
                'max:255',
            ],

            'generated_at' => [
                'nullable',
                'date',
            ],

            'generated_by_user_id' => [
                'nullable',
                'uuid',
                'exists:users,id',
            ],

            'export_file_id' => [
                'nullable',
                'uuid',
                'exists:file_assets,id',
            ],
        ];
    }
}
