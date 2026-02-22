<?php

namespace App\Http\Requests\Authorization;

use App\DataTransferObjects\Auth\RegisterData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:16'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(6)],
        ];
    }

    public function toData(): RegisterData
    {
        /** @var array{name: string, email: string, password: string} $validated */
        $validated = $this->validated();

        return new RegisterData(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
        );
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'name.string' => 'Name must be a string.',
            'name.min' => 'Name must be at least 3 characters.',
            'name.max' => 'Name may not be greater than 16 characters.',

            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'Email is taken',

            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
        ];
    }
}
