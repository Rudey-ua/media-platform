<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LoginRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\RefreshTokenCookieService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(private readonly RefreshTokenCookieService $refreshTokenCookieService) {}

    public function showLogin(): InertiaResponse
    {
        return Inertia::render('Auth/Login', [
            'loginStoreUrl' => route('login.store', absolute: false),
        ]);
    }

    public function login(LoginRequest $request, AuthService $authService): RedirectResponse
    {
        try {
            $authSessionData = $authService->handleLogin($request->toData());
        } catch (ApiException $exception) {
            if ($exception->status !== Response::HTTP_UNAUTHORIZED) {
                throw $exception;
            }
            return back()->withErrors(['email' => 'Invalid credentials'])->onlyInput('email');
        }

        if (! $authSessionData->user->hasAnyRole(['owner', 'member', 'admin'])) {
            $authSessionData->user->refreshTokens()->delete();
            return back()->withErrors(['email' => 'Access is allowed only for admin, owner, and member users'])->onlyInput('email');
        }
        Auth::guard('web')->login($authSessionData->user, $request->boolean('remember'));

        $request->session()->regenerate();

        $request->session()->put('api_auth_tokens',
            $this->createApiAuthPayload(
                user: $authSessionData->user,
            ),
        );
        $defaultRedirectPath = $authSessionData->user->hasRole('admin')
            ? $this->telescopePath()
            : route('player.home');

        return redirect()->intended($defaultRedirectPath)->withCookie($this->refreshTokenCookieService->make($authSessionData->refreshToken));
    }

    public function logout(Request $request): RedirectResponse
    {
        return $this->performLogout($request);
    }

    public function logoutAdmin(Request $request): RedirectResponse
    {
        return $this->performLogout($request);
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    private function createApiAuthPayload(User $user): array
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return [
            'access_token' => $guard->login($user),
            'token_type' => 'bearer',
            'expires_in' => $guard->factory()->getTTL() * 60,
        ];
    }

    private function telescopePath(): string
    {
        $configuredPath = trim((string) config('telescope.path', 'telescope'));

        if ($configuredPath === '') {
            return '/telescope';
        }
        return '/'.ltrim($configuredPath, '/');
    }

    private function performLogout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->refreshTokens()->delete();
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withCookie($this->refreshTokenCookieService->forget());
    }
}
