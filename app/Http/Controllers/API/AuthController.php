<?php

namespace App\Http\Controllers\API;

use App\DataTransferObjects\User\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Authorization\LoginRequest;
use App\Http\Requests\Authorization\RefreshTokenRequest;
use App\Http\Requests\Authorization\RegisterRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuthService;
use App\Services\RefreshTokenCookieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    public function __construct(private readonly RefreshTokenCookieService $refreshTokenCookieService) {}

    public function register(RegisterRequest $request, AuthService $authService): JsonResponse
    {
        $authSessionData = $authService->handleRegister($request->toData());

        /** @var JWTGuard $guard */
        $guard = auth('api');

        $response = ApiResponse::created(
            request: $request,
            data: [
                'user' => UserData::fromModel($authSessionData->user),
                'access_token' => $guard->login($authSessionData->user),
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        );
        return $response->withCookie($this->refreshTokenCookieService->make($authSessionData->refreshToken));
    }

    public function login(LoginRequest $request, AuthService $authService): JsonResponse
    {
        $authSessionData = $authService->handleLogin($request->toData());

        /** @var JWTGuard $guard */
        $guard = auth('api');

        $response = ApiResponse::success(
            request: $request,
            data: [
                'user' => UserData::fromModel($authSessionData->user),
                'access_token' => $guard->login($authSessionData->user),
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        );
        return $response->withCookie($this->refreshTokenCookieService->make($authSessionData->refreshToken));
    }

    public function refreshToken(RefreshTokenRequest $request, AuthService $authService): JsonResponse
    {
        $authSessionData = $authService->handleRefreshToken($request->toData());

        /** @var JWTGuard $guard */
        $guard = auth('api');

        $response = ApiResponse::success(
            request: $request,
            data: [
                'access_token' => $guard->login($authSessionData->user),
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        );
        return $response->withCookie($this->refreshTokenCookieService->make($authSessionData->refreshToken));
    }

    public function logout(): JsonResponse
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $user->refreshTokens()->delete();
        }
        auth('api')->logout();

        $response = ApiResponse::info(
            message: 'Successfully logged out',
        );
        return $response->withCookie($this->refreshTokenCookieService->forget());
    }
}
