<?php

namespace App\Http\Requests\Teacher;

use App\Models\TeacherAvailability;
use App\Services\TeacherWorkspaceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) { return false; }
        $service = app(TeacherWorkspaceService::class);
        $actor = $service->actor($this->user());
        $slot = $this->route('availability');
        if ($slot instanceof TeacherAvailability) { $service->authorizeSlot($actor, $slot); }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['starts_at', 'ends_at'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && preg_match('/^\d{2}:\d{2}$/D', $value)) { $values[$field] = $value.':00'; }
        }
        foreach (['class_type', 'availability_status'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) { $values[$field] = ucfirst(strtolower(trim($value))); }
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        // Laravel's dependent date comparison expects a string, even if the start field already failed validation.
        $endRules = ['bail', 'required', 'string', 'date_format:H:i:s'];
        if (is_string($this->input('starts_at'))) { $endRules[] = 'after:starts_at'; }

        return [
            'teacher_id' => ['prohibited'], 'branch_id' => ['required', 'string', 'max:64'],
            'available_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'starts_at' => ['bail', 'required', 'string', 'date_format:H:i:s'], 'ends_at' => $endRules,
            'class_type' => ['required', Rule::in(array_keys(TeacherWorkspaceService::SLOT_TYPES))],
            'availability_status' => ['required', Rule::in(array_keys(TeacherWorkspaceService::SLOT_STATUSES))],
            'notes' => ['nullable', 'string', 'max:255'],
            'record_version' => [$this->route('availability') ? 'required' : 'nullable', 'string', 'size:64'],
        ];
    }
}
