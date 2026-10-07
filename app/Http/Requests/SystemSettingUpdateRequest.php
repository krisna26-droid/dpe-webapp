<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SystemSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_name' => [
                'required',
                'string',
            ],

            'organization_description' => [
                'nullable',
                'string',
            ],

            'contact_email' => [
                'nullable',
                'email',
            ],

            'contact_phone' => [
                'nullable',
                'string',
            ],

            'logo_file_id' => [
                'nullable',
                'uuid',
                'exists:file_assets,id',
            ],

            'default_report_due_day' => [
                'required',
                'integer',
                'between:1,31',
            ],

            'default_monthly_video_target' => [
                'required',
                'integer',
                'min:0',
            ],

            'in_app_reminders_enabled' => [
                'required',
                'boolean',
            ],
        ];
    }
}
