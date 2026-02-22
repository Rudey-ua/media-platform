<?php

namespace App\Http\Requests\Authorization;

use App\DataTransferObjects\Auth\RefreshTokenData;
use App\Services\RefreshTokenCookieService;
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

    protected function prepareForValidation(): void
    {
        $inputRefreshToken = $this->input('refresh_token');

        if (is_string($inputRefreshToken) && trim($inputRefreshToken) !== '') {
            return;
        }
        $refreshTokenFromCookie = $this->cookie(RefreshTokenCookieService::COOKIE_NAME);

        if (! is_string($refreshTokenFromCookie) || trim($refreshTokenFromCookie) === '') {
            return;
        }
        $this->merge([
            'refresh_token' => $refreshTokenFromCookie,
        ]);
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
