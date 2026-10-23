<?php

namespace App\Http\Requests;

use App\Services\DashboardSettingsService;
use Illuminate\Foundation\Http\FormRequest;

class SaveSystemSettingsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) { return false; }
        app(DashboardSettingsService::class)->actor($this->user(), global: true);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['organization_name', 'organization_description', 'contact_email', 'contact_phone'] as $field) {
            if (is_string($this->input($field))) { $values[$field] = trim($this->input($field)); }
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'organization_name' => ['bail', 'required', 'string', 'max:255'],
            'organization_description' => ['nullable', 'string', 'max:5000'],
            'contact_email' => ['bail', 'nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'default_report_due_day' => ['required', 'integer', 'between:1,31'],
            'default_monthly_video_target' => ['required', 'integer', 'between:0,32767'],
            'in_app_reminders_enabled' => ['required', 'boolean'],
            'record_version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'id' => ['prohibited'], 'logo_file_id' => ['prohibited'], 'updated_at' => ['prohibited'],
            'role_code' => ['prohibited'], 'user_id' => ['prohibited'], 'branch_id' => ['prohibited'],
        ];
    }
}
