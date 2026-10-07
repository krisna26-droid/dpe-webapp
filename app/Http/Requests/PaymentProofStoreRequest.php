<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentProofStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'charge_id' => [
                'required',
                'uuid',
                'exists:monthly_charges,id',
            ],

            'image_file_id' => [
                'required',
                'uuid',
                'exists:file_assets,id',
            ],

            'uploaded_by_user_id' => [
                'required',
                'uuid',
                'exists:users,id',
            ],

            'submitted_at' => [
                'nullable',
                'date',
            ],

            'status' => [
                'required',
                'string',
                'max:255',
            ],

            'reviewed_by_user_id' => [
                'nullable',
                'uuid',
                'exists:users,id',
            ],

            'reviewed_at' => [
                'nullable',
                'date',
            ],

            'rejection_reason' => [
                'nullable',
                'string',
            ],
        ];
    }
}
