<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateUploadRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_CONTENT_TYPES = [
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/matroska',
        'video/mkv',
        'video/mp2t',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_name' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1', 'max:21474836480'],
            'content_type' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_CONTENT_TYPES)],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'content_type.in' => 'The content type must be one of: '.implode(', ', self::ALLOWED_CONTENT_TYPES).'.',
        ];
    }

    /**
     * @return array{file_name: string, file_size: int, content_type: string, title: ?string}
     */
    public function uploadAttributes(): array
    {
        /** @var array{file_name: string, file_size: int, content_type: string, title: ?string} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
