<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportStatusEventStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $eventId = $this->route('id');

        return [
            'id' => [
                'required',
                'uuid',
                'unique:report_status_events,id,' . $eventId,
            ],
            'report_id' => [
                'required',
                'uuid',
                'exists:monthly_reports,id',
            ],
            'actor_user_id' => [
                'required',
                'uuid',
                'exists:users,id',
            ],
            'from_status' => [
                'nullable',
                'string',
            ],
            'to_status' => [
                'required',
                'string',
            ],
            'comment' => [
                'nullable',
                'string',
            ],
            'occurred_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
