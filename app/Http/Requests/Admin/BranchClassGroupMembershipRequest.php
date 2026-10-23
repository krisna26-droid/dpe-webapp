<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\SuperAdmin\StoreClassGroupMembershipRequest;
use App\Services\BranchOperationsService;

class BranchClassGroupMembershipRequest extends StoreClassGroupMembershipRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->is_active || ! in_array($this->user()?->role_code, ['admin', 'superadmin'], true)) { return false; }
        if ($record = $this->route('class_group_membership')) { app(BranchOperationsService::class)->authorize($this->user(), $record); }

        return true;
    }

    public function rules(): array
    {
        return parent::rules() + ['record_version' => [$this->route('class_group_membership') ? 'required' : 'nullable', 'string', 'size:64']];
    }
}
