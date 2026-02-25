import { isAccessTokenExpired } from './jwtExpiry.js';
import {
    accessTokenFromObject,
    normalizeAccessToken,
    normalizeRefreshToken,
    refreshTokenFromObject,
} from './tokenParsers.js';
import { safeWindow } from './browserWindow.js';

function resolveTokenFromWindow(browserWindow) {
    const candidateTokens = [
        {
            token: accessTokenFromObject(browserWindow.auth || {}),
            source: 'window.auth',
        },
        {
            token: accessTokenFromObject(browserWindow.App || {}),
            source: 'window.App',
        },
        {
            token: normalizeAccessToken(browserWindow.ACCESS_TOKEN),
            source: 'window.ACCESS_TOKEN',
        },
        {
            token: normalizeAccessToken(browserWindow.accessToken),
            source: 'window.accessToken',
        },
        {
            token: normalizeAccessToken(browserWindow.authToken),
            source: 'window.authToken',
        },
    ];

    for (const candidate of candidateTokens) {
        if (candidate.token && !isAccessTokenExpired(candidate.token)) {
            return candidate;
        }
    }

    return null;
}

function resolveRefreshTokenFromWindow(browserWindow) {
    const candidateTokens = [
        {
            token: refreshTokenFromObject(browserWindow.auth || {}),
            source: 'window.auth',
        },
        {
            token: refreshTokenFromObject(browserWindow.App || {}),
            source: 'window.App',
        },
        {
            token: normalizeRefreshToken(browserWindow.REFRESH_TOKEN),
            source: 'window.REFRESH_TOKEN',
        },
        {
            token: normalizeRefreshToken(browserWindow.refreshToken),
            source: 'window.refreshToken',
        },
    ];

    for (const candidate of candidateTokens) {
        if (candidate.token) {
            return candidate;
        }
    }

    return null;
}

export function resolveAccessToken(authTokensPayload) {
    const browserWindow = safeWindow();

    if (!browserWindow) {
        return null;
    }

    const tokenFromSharedPayload = accessTokenFromObject(authTokensPayload);

    if (tokenFromSharedPayload && !isAccessTokenExpired(tokenFromSharedPayload)) {
        return {
            token: tokenFromSharedPayload,
            source: 'inertia.shared.auth.api_tokens',
        };
    }

    return resolveTokenFromWindow(browserWindow);
}

export function resolveRefreshToken(authTokensPayload) {
    const browserWindow = safeWindow();

    if (!browserWindow) {
        return null;
    }

    const refreshTokenFromSharedPayload = refreshTokenFromObject(authTokensPayload);

    if (refreshTokenFromSharedPayload) {
        return {
            token: refreshTokenFromSharedPayload,
            source: 'inertia.shared.auth.api_tokens',
        };
    }

    return resolveRefreshTokenFromWindow(browserWindow);
}
