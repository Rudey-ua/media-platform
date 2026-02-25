<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVideoTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.present' => 'Title field must be present.',
            'title.string' => 'Title must be a string.',
            'title.max' => 'Title may not be greater than 255 characters.',
        ];
    }

    public function title(): ?string
    {
        $title = $this->validated('title');

        if (! is_string($title)) {
            return null;
        }
        $normalizedTitle = trim($title);

        return $normalizedTitle === '' ? null : $normalizedTitle;
    }
}
