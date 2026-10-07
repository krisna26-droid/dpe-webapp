<?php

namespace App\Http\Requests\MonthlyCharge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMonthlyChargeRequest extends FormRequest
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
            'student_id' => [
                'required',
                'string',
                Rule::exists('students', 'id'),
            ],

            'branch_id' => [
                'required',
                'string',
                Rule::exists('branches', 'id'),
            ],

            'charge_month' => [
                'required',
                'date_format:Y-m-d',
            ],

            'amount_idr' => [
                'required',
                'numeric',
                'min:0',
            ],

            'due_on' => [
                'nullable',
                'date_format:Y-m-d',
            ],
        ];
    }
}
