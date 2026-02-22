<?php

namespace App\Services;

use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;

class RefreshTokenCookieService
{
    public const string COOKIE_NAME = 'api_refresh_token';

    public function make(string $refreshToken): HttpCookie
    {
        return Cookie::make(
            name: $this->cookieName(),
            value: $refreshToken,
            minutes: $this->ttlMinutes(),
            path: $this->cookiePath(),
            domain: $this->cookieDomain(),
            secure: $this->secure(),
            sameSite: $this->sameSite(),
        );
    }

    public function forget(): HttpCookie
    {
        return Cookie::forget(
            name: $this->cookieName(),
            path: $this->cookiePath(),
            domain: $this->cookieDomain(),
        );
    }

    public function cookieName(): string
    {
        return self::COOKIE_NAME;
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('auth.refresh_cookie.ttl_minutes', 43200));
    }

    private function cookiePath(): string
    {
        return (string) config('auth.refresh_cookie.path', '/');
    }

    private function cookieDomain(): ?string
    {
        $configuredDomain = config('auth.refresh_cookie.domain');

        if (! is_string($configuredDomain) || trim($configuredDomain) === '') {
            return null;
        }
        return trim($configuredDomain);
    }

    private function secure(): ?bool
    {
        $configuredSecure = config('auth.refresh_cookie.secure');

        if (is_bool($configuredSecure)) {
            return $configuredSecure;
        }
        if (is_numeric($configuredSecure)) {
            return (bool) $configuredSecure;
        }
        if (is_string($configuredSecure)) {
            $normalizedSecure = strtolower(trim($configuredSecure));

            if ($normalizedSecure === 'true' || $normalizedSecure === '1') {
                return true;
            }
            if ($normalizedSecure === 'false' || $normalizedSecure === '0') {
                return false;
            }
        }
        return null;
    }

    private function sameSite(): ?string
    {
        $configuredSameSite = config('auth.refresh_cookie.same_site', 'lax');

        if (! is_string($configuredSameSite)) {
            return null;
        }
        $normalizedSameSite = strtolower(trim($configuredSameSite));

        if (! in_array($normalizedSameSite, ['lax', 'strict', 'none'], true)) {
            return null;
        }
        return $normalizedSameSite;
    }
}
