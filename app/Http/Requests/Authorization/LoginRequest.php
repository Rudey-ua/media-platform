<?php

namespace App\Http\Requests\Authorization;

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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
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
