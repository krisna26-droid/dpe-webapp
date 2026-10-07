<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicContentSectionStoreRequest extends FormRequest
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
                Rule::unique('public_content_sections', 'id')->ignore(
                    $this->route('id') ?? $this->input('id')
                ),
            ],
            'section_key' => [
                'required',
                'string',
                Rule::unique('public_content_sections', 'section_key')->ignore(
                    $this->route('id') ?? $this->input('id'),
                    'id'
                ),
            ],
            'title' => [
                'required',
                'string',
            ],
            'body' => [
                'nullable',
                'string',
            ],
            'image_file_id' => [
                'nullable',
                'uuid',
                'exists:file_assets,id',
            ],
            'display_order' => [
                'required',
                'integer',
                'min:0',
            ],
            'is_published' => [
                'required',
                'boolean',
            ],
        ];
    }
}
