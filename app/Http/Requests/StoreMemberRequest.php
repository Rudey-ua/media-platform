<?php

namespace App\Http\Requests;

use App\Enums\MemberVideoAccessMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'access_mode' => ['sometimes', 'string', Rule::in([
                MemberVideoAccessMode::All->value,
                MemberVideoAccessMode::Custom->value,
            ])],
        ];
    }

    public function memberEmail(): string
    {
        return (string) $this->validated('email');
    }

    public function accessMode(): MemberVideoAccessMode
    {
        $mode = (string) $this->validated('access_mode', MemberVideoAccessMode::All->value);

        return MemberVideoAccessMode::from($mode);
    }
}
