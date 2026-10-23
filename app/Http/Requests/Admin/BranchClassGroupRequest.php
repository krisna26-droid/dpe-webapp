<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\SuperAdmin\StoreClassGroupRequest;
use App\Services\BranchOperationsService;
use Illuminate\Validation\Rule;

class BranchClassGroupRequest extends StoreClassGroupRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->is_active || ! in_array($this->user()?->role_code, ['admin', 'superadmin'], true)) { return false; }
        if ($record = $this->route('class_group')) { app(BranchOperationsService::class)->authorize($this->user(), $record); }

        return true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $record = $this->route('class_group');
        $rules['code'] = ['required', 'string', 'max:255', Rule::unique('class_groups', 'code')
            ->where('branch_id', $this->input('branch_id'))->ignore($record?->id)];
        $rules['record_version'] = [$record ? 'required' : 'nullable', 'string', 'size:64'];

        return $rules;
    }
}
