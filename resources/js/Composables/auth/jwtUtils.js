import { safeWindow } from './windowUtils';

function decodeJwtPayload(token) {
    const browserWindow = safeWindow();

    if (!browserWindow || typeof token !== 'string') {
        return null;
    }

    const tokenParts = token.split('.');

    if (tokenParts.length !== 3) {
        return null;
    }

    const payloadPart = tokenParts[1];
    const normalizedPayload = payloadPart.replace(/-/g, '+').replace(/_/g, '/');
    const requiredPadding = (4 - (normalizedPayload.length % 4)) % 4;
    const paddedPayload = normalizedPayload.padEnd(normalizedPayload.length + requiredPadding, '=');

    try {
        return JSON.parse(browserWindow.atob(paddedPayload));
    } catch (_error) {
        return null;
    }
}

function accessTokenExpiresAtMs(token) {
    const payload = decodeJwtPayload(token);

    if (!payload || typeof payload.exp !== 'number') {
        return null;
    }

    return payload.exp * 1000;
}

export function isAccessTokenExpired(token) {
    const expiresAtMs = accessTokenExpiresAtMs(token);

    if (expiresAtMs === null) {
        return false;
    }

    return expiresAtMs <= Date.now() + 30000;
}
