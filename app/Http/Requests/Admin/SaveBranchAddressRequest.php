<?php

namespace App\Http\Requests\Admin;

use App\Services\DashboardSettingsService;
use Illuminate\Foundation\Http\FormRequest;

class SaveBranchAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) { return false; }
        app(DashboardSettingsService::class)->branch($this->user(), $this->route('branch')->id, editable: true);

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('address'))) { $this->merge(['address' => trim($this->input('address'))]); }
    }

    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'max:5000'],
            'record_version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'id' => ['prohibited'], 'branch_id' => ['prohibited'], 'code' => ['prohibited'], 'name' => ['prohibited'],
            'timezone_name' => ['prohibited'], 'payment_recap_day' => ['prohibited'], 'is_active' => ['prohibited'],
            'portal_attendance_visible' => ['prohibited'], 'admin_user_id' => ['prohibited'], 'user_id' => ['prohibited'],
        ];
    }
}
