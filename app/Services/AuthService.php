<?php

namespace App\Services;

use App\DataTransferObjects\Auth\AuthSessionData;
use App\DataTransferObjects\Auth\LoginData;
use App\DataTransferObjects\Auth\RefreshTokenData;
use App\DataTransferObjects\Auth\RegisterData;
use App\Exceptions\ApiException;
use App\Models\RefreshToken;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthService
{
    public function __construct(public UserRepository $userRepository) {}

    public function handleRegister(RegisterData $registerData): AuthSessionData
    {
        $user = $this->userRepository->create($registerData);
        $refreshToken = $this->issueRefreshToken($user);

        return new AuthSessionData(
            user: $user,
            refreshToken: $refreshToken,
        );
    }

    public function handleLogin(LoginData $loginData): AuthSessionData
    {
        $user = $this->userRepository->findUserByEmail($loginData->email);

        if (is_null($user) || ! Hash::check($loginData->password, $user->password)) {
            throw new ApiException(
                message: 'Invalid credentials',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        $refreshToken = $this->issueRefreshToken($user);

        return new AuthSessionData(
            user: $user,
            refreshToken: $refreshToken,
        );
    }

    public function handleRefreshToken(RefreshTokenData $refreshTokenData): string
    {
        $hashed = hash('sha256', $refreshTokenData->refreshToken);

        $stored = RefreshToken::where('token', $hashed)->first();

        if (! $stored || $stored->expires_at->isPast()) {
            throw new ApiException(
                message: 'Invalid or expired refresh token',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $user = $stored->user;

        return auth('api')->login($user);
    }

    private function issueRefreshToken(User $user): string
    {
        $refreshToken = Str::random(64);

        $user->refreshTokens()->delete();

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $refreshToken),
            'expires_at' => now()->addDays(30),
        ]);

        return $refreshToken;
    }
}
