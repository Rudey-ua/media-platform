import { isAccessTokenExpired } from './jwtUtils';

const ACCESS_TOKEN_OBJECT_KEYS = ['access_token', 'accessToken', 'token', 'jwt', 'jwt_token'];
const REFRESH_TOKEN_OBJECT_KEYS = ['refresh_token', 'refreshToken'];

function isRecord(value) {
    return value !== null && typeof value === 'object';
}

export function normalizeAccessToken(rawValue) {
    if (typeof rawValue !== 'string') {
        return null;
    }

    const trimmedValue = rawValue.trim();

    if (trimmedValue === '' || trimmedValue.startsWith('{') || trimmedValue.startsWith('[')) {
        return null;
    }

    return trimmedValue.replace(/^Bearer\s+/i, '').trim();
}

export function normalizeRefreshToken(rawValue) {
    if (typeof rawValue !== 'string') {
        return null;
    }

    const trimmedValue = rawValue.trim();

    if (trimmedValue === '' || trimmedValue.startsWith('{') || trimmedValue.startsWith('[')) {
        return null;
    }

    return trimmedValue;
}

export function accessTokenFromObject(mixedValue) {
    if (!isRecord(mixedValue)) {
        return null;
    }

    for (const key of ACCESS_TOKEN_OBJECT_KEYS) {
        const token = normalizeAccessToken(mixedValue[key]);

        if (token && !isAccessTokenExpired(token)) {
            return token;
        }
    }

    if (isRecord(mixedValue.data)) {
        for (const key of ACCESS_TOKEN_OBJECT_KEYS) {
            const token = normalizeAccessToken(mixedValue.data[key]);

            if (token && !isAccessTokenExpired(token)) {
                return token;
            }
        }
    }

    return null;
}

export function refreshTokenFromObject(mixedValue) {
    if (!isRecord(mixedValue)) {
        return null;
    }

    for (const key of REFRESH_TOKEN_OBJECT_KEYS) {
        const token = normalizeRefreshToken(mixedValue[key]);

        if (token) {
            return token;
        }
    }

    if (isRecord(mixedValue.data)) {
        for (const key of REFRESH_TOKEN_OBJECT_KEYS) {
            const token = normalizeRefreshToken(mixedValue.data[key]);

            if (token) {
                return token;
            }
        }
    }

    return null;
}
