<?php

namespace App\Http\Requests\Web;

use App\DataTransferObjects\Auth\LoginData;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required',
            'email.email' => 'Email must be a valid email address',
            'password.required' => 'Password is required',
        ];
    }

    public function toData(): LoginData
    {
        /** @var array{email: string, password: string} $validated */
        $validated = $this->validated();

        return new LoginData(
            email: $validated['email'],
            password: $validated['password'],
        );
    }
}
