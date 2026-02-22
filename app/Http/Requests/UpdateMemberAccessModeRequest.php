<?php

namespace App\Http\Requests;

use App\Enums\MemberVideoAccessMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberAccessModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access_mode' => ['required', 'string', Rule::in([
                MemberVideoAccessMode::All->value,
                MemberVideoAccessMode::Custom->value,
            ])],
        ];
    }

    public function accessMode(): MemberVideoAccessMode
    {
        return MemberVideoAccessMode::from((string) $this->validated('access_mode'));
    }
}
