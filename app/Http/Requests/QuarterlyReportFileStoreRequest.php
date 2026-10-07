<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuarterlyReportFileStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cycle_id' => [
                'required',
                'uuid',
                'exists:report_cycles,id',
            ],

            'file_id' => [
                'required',
                'uuid',
                'exists:file_assets,id',
            ],

            'version_number' => [
                'required',
                'integer',
                'min:1',
            ],

            'generated_by_user_id' => [
                'required',
                'uuid',
                'exists:users,id',
            ],

            'generated_at' => [
                'nullable',
                'date',
            ],

            'source_snapshot_hash' => [
                'nullable',
                'string',
            ],
        ];
    }
}
