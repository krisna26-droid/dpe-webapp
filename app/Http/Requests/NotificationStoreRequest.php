<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'uuid',
                Rule::unique('notifications', 'id')->ignore(
                    $this->route('id') ?? $this->input('id')
                ),
            ],
            'user_id' => [
                'required',
                'uuid',
                'exists:users,id',
            ],
            'notification_type' => [
                'required',
                'string',
            ],
            'title' => [
                'required',
                'string',
            ],
            'body' => [
                'required',
                'string',
            ],
            'action_path' => [
                'nullable',
                'string',
            ],
        ];
    }
}
