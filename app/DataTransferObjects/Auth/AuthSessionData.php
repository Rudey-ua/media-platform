<?php

namespace App\DataTransferObjects\Auth;

use App\Models\User;

readonly class AuthSessionData
{
    public function __construct(
        public User $user,
        public string $refreshToken,
    ) {}
}
