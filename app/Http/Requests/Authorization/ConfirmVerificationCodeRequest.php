<?php

namespace App\Http\Requests\Authorization;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmVerificationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }
}
