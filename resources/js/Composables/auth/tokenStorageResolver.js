import { isAccessTokenExpired } from './jwtUtils';
import {
    accessTokenFromObject,
    normalizeAccessToken,
    normalizeRefreshToken,
    refreshTokenFromObject,
} from './tokenParsers';
import { safeWindow } from './windowUtils';

const ACCESS_TOKEN_KEYS = [
    'access_token',
    'auth.access_token',
    'api_access_token',
    'auth_token',
    'jwt',
    'jwt_token',
    'token',
    'hls_player_jwt_token',
];

const REFRESH_TOKEN_KEYS = [
    'refresh_token',
    'auth.refresh_token',
    'api_refresh_token',
    'refreshToken',
    'hls_player_refresh_token',
];

function accessTokenFromStorage(storage, storageLabel) {
    if (!storage) {
        return null;
    }

    for (const key of ACCESS_TOKEN_KEYS) {
        const value = storage.getItem(key);
        const directToken = normalizeAccessToken(value);

        if (directToken && !isAccessTokenExpired(directToken)) {
            return { token: directToken, source: `${storageLabel}:${key}` };
        }

        if (typeof value === 'string' && value.trim().startsWith('{')) {
            try {
                const parsedValue = JSON.parse(value);
                const token = accessTokenFromObject(parsedValue);

                if (token) {
                    return { token, source: `${storageLabel}:${key}` };
                }
            } catch (_error) {
                continue;
            }
        }
    }

    for (let index = 0; index < storage.length; index += 1) {
        const key = storage.key(index);

        if (!key) {
            continue;
        }

        const value = storage.getItem(key);
        const directToken = normalizeAccessToken(value);

        if (directToken && directToken.split('.').length === 3 && !isAccessTokenExpired(directToken)) {
            return { token: directToken, source: `${storageLabel}:${key}` };
        }

        if (typeof value === 'string' && value.trim().startsWith('{')) {
            try {
                const parsedValue = JSON.parse(value);
                const token = accessTokenFromObject(parsedValue);

                if (token) {
                    return { token, source: `${storageLabel}:${key}` };
                }
            } catch (_error) {
                continue;
            }
        }
    }

    return null;
}

function refreshTokenFromStorage(storage, storageLabel) {
    if (!storage) {
        return null;
    }

    for (const key of REFRESH_TOKEN_KEYS) {
        const value = storage.getItem(key);
        const directToken = normalizeRefreshToken(value);

        if (directToken) {
            return { token: directToken, source: `${storageLabel}:${key}` };
        }

        if (typeof value === 'string' && value.trim().startsWith('{')) {
            try {
                const parsedValue = JSON.parse(value);
                const token = refreshTokenFromObject(parsedValue);

                if (token) {
                    return { token, source: `${storageLabel}:${key}` };
                }
            } catch (_error) {
                continue;
            }
        }
    }

    for (let index = 0; index < storage.length; index += 1) {
        const key = storage.key(index);

        if (!key) {
            continue;
        }

        const value = storage.getItem(key);

        if (typeof value === 'string' && value.trim().startsWith('{')) {
            try {
                const parsedValue = JSON.parse(value);
                const token = refreshTokenFromObject(parsedValue);

                if (token) {
                    return { token, source: `${storageLabel}:${key}` };
                }
            } catch (_error) {
                continue;
            }
        }
    }

    return null;
}

export function resolveAccessToken(authTokensPayload) {
    const browserWindow = safeWindow();

    if (!browserWindow) {
        return null;
    }

    const candidateTokens = [
        normalizeAccessToken(browserWindow.ACCESS_TOKEN),
        normalizeAccessToken(browserWindow.accessToken),
        normalizeAccessToken(browserWindow.authToken),
        accessTokenFromObject(authTokensPayload),
        accessTokenFromObject(browserWindow.auth || {}),
        accessTokenFromObject(browserWindow.App || {}),
    ];

    for (const token of candidateTokens) {
        if (token && !isAccessTokenExpired(token)) {
            return { token, source: 'window' };
        }
    }

    const localStorageToken = accessTokenFromStorage(browserWindow.localStorage, 'localStorage');

    if (localStorageToken) {
        return localStorageToken;
    }

    return accessTokenFromStorage(browserWindow.sessionStorage, 'sessionStorage');
}

export function resolveRefreshToken(authTokensPayload) {
    const browserWindow = safeWindow();

    if (!browserWindow) {
        return null;
    }

    const candidateTokens = [
        normalizeRefreshToken(browserWindow.REFRESH_TOKEN),
        normalizeRefreshToken(browserWindow.refreshToken),
        refreshTokenFromObject(authTokensPayload),
        refreshTokenFromObject(browserWindow.auth || {}),
        refreshTokenFromObject(browserWindow.App || {}),
    ];

    for (const token of candidateTokens) {
        if (token) {
            return { token, source: 'window' };
        }
    }

    const localStorageToken = refreshTokenFromStorage(browserWindow.localStorage, 'localStorage');

    if (localStorageToken) {
        return localStorageToken;
    }

    return refreshTokenFromStorage(browserWindow.sessionStorage, 'sessionStorage');
}
