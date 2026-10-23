<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\SuperAdmin\StoreLessonSessionRequest;
use App\Services\BranchOperationsService;
use Illuminate\Validation\Rule;

class BranchLessonSessionRequest extends StoreLessonSessionRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->is_active || ! in_array($this->user()?->role_code, ['admin', 'superadmin'], true)) { return false; }
        if ($record = $this->route('lesson_session')) { app(BranchOperationsService::class)->authorize($this->user(), $record); }

        return true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $record = $this->route('lesson_session');
        $rules['status'] = ['required', Rule::in(array_filter(['scheduled', 'ongoing', 'completed', 'cancelled', $record?->status]))];
        $rules['actual_start_at'] = ['nullable', 'required_with:actual_end_at', 'date'];
        $rules['record_version'] = [$record ? 'required' : 'nullable', 'string', 'size:64'];

        return $rules;
    }
}
