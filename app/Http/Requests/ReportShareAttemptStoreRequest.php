<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportShareAttemptStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quarterly_report_file_id' => [
                'required',
                'uuid',
                'exists:quarterly_report_files,id',
            ],

            'teacher_id' => [
                'required',
                'uuid',
                'exists:teachers,id',
            ],

            'guardian_id' => [
                'required',
                'uuid',
                'exists:guardians,id',
            ],

            'recipient_phone_snapshot' => [
                'required',
                'string',
            ],

            'status' => [
                'required',
                'string',
            ],

            'opened_at' => [
                'required',
                'date',
            ],

            'confirmed_at' => [
                'nullable',
                'date',
            ],

            'teacher_note' => [
                'nullable',
                'string',
            ],
        ];
    }
}