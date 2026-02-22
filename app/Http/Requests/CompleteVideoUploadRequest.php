<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteVideoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uploaded_size' => ['nullable', 'integer', 'min:1', 'max:21474836480'],
            'etag' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function uploadedSize(): ?int
    {
        $value = $this->validated('uploaded_size');

        if (! is_int($value)) {
            return null;
        }
        return $value;
    }
}
