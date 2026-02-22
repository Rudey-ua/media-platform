<?php

namespace App\DataTransferObjects\Auth;

readonly class RefreshTokenData
{
    public function __construct(public string $refreshToken) {}
}
