<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileNameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255', 'regex:/\S/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'name.string' => 'Name must be a string.',
            'name.min' => 'Name must be at least 2 characters.',
            'name.max' => 'Name may not be greater than 255 characters.',
            'name.regex' => 'Name must contain visible characters.',
        ];
    }

    public function profileName(): string
    {
        return trim((string) $this->validated('name'));
    }
}
