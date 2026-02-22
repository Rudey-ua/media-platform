<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profile_image' => ['required', 'file', 'mimes:jpeg,png,jpg,heic,heif', 'max:5120'],
        ];
    }
}
