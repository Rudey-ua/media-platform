<?php

namespace App\Http\Requests\Authorization;

use App\DataTransferObjects\Auth\RefreshTokenData;
use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }

    public function toData(): RefreshTokenData
    {
        /** @var array{refresh_token: string} $validated */
        $validated = $this->validated();

        return new RefreshTokenData(
            refreshToken: $validated['refresh_token'],
        );
    }
}
