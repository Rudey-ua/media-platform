import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    accessTokenFromObject,
    isAccessTokenExpired,
    resolveAccessToken,
} from './tokenUtils';

const API_BASE = window.location.origin;
const ACCESS_TOKEN_STORAGE_KEY = 'hls_player_jwt_token';
const BROWSER_TOKEN_STORAGE_KEYS = [
    ACCESS_TOKEN_STORAGE_KEY,
    'access_token',
    'auth.access_token',
    'api_access_token',
    'auth_token',
    'jwt',
    'jwt_token',
    'token',
    'refresh_token',
    'auth.refresh_token',
    'api_refresh_token',
    'refreshToken',
    'hls_player_refresh_token',
];

function removeTokenKeysFromStorage(storage) {
    if (!storage) {
        return;
    }

    for (const key of BROWSER_TOKEN_STORAGE_KEYS) {
        try {
            storage.removeItem(key);
        } catch (_error) {
            continue;
        }
    }
}

export function useApiAuth() {
    const page = usePage();
    const accessToken = ref(null);
    const tokenSource = ref(null);
    const refreshInFlight = ref(null);
    const authStatus = ref('Token lookup pending...');

    const hasAccessToken = computed(() => {
        return typeof accessToken.value === 'string' && accessToken.value !== '';
    });

    function updateAccessToken(token, source) {
        accessToken.value = token;
        tokenSource.value = source;

        try {
            window.localStorage.setItem(ACCESS_TOKEN_STORAGE_KEY, token);
        } catch (_error) {
            return;
        }
    }

    function clearAuthTokens() {
        accessToken.value = null;
        tokenSource.value = null;
        authStatus.value = 'Logged out.';

        removeTokenKeysFromStorage(window.localStorage);
        removeTokenKeysFromStorage(window.sessionStorage);
    }

    async function refreshAccessToken() {
        if (refreshInFlight.value) {
            return refreshInFlight.value;
        }

        refreshInFlight.value = (async () => {
            try {
                const response = await fetch(`${API_BASE}/api/v1/refresh`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                    },
                    credentials: 'include',
                });

                if (!response.ok) {
                    return false;
                }

                const payload = await response.json().catch(() => {
                    return null;
                });
                const refreshedToken = accessTokenFromObject(payload);

                if (!refreshedToken || isAccessTokenExpired(refreshedToken)) {
                    return false;
                }

                updateAccessToken(refreshedToken, 'refresh');
                authStatus.value = 'Access token refreshed.';

                return true;
            } catch (_error) {
                return false;
            } finally {
                refreshInFlight.value = null;
            }
        })();

        return refreshInFlight.value;
    }

    async function bootstrapAuth() {
        const authTokensPayload = page.props?.auth?.api_tokens || {};
        const resolvedToken = resolveAccessToken(authTokensPayload);

        if (!resolvedToken || !resolvedToken.token) {
            const refreshed = await refreshAccessToken();

            if (!refreshed || !accessToken.value) {
                authStatus.value = 'No valid API token found in window/localStorage/sessionStorage.';
                return false;
            }

            return true;
        }

        updateAccessToken(resolvedToken.token, resolvedToken.source);
        authStatus.value = `Token source: ${resolvedToken.source}.`;

        return true;
    }

    async function fetchWithAuthorization(url, options = {}) {
        if (!hasAccessToken.value || isAccessTokenExpired(accessToken.value)) {
            const refreshedBeforeRequest = await refreshAccessToken();

            if (!refreshedBeforeRequest || !accessToken.value) {
                return fetch(url, {
                    ...options,
                    headers: {
                        ...(options.headers ?? {}),
                    },
                });
            }
        }

        const headers = {
            ...(options.headers ?? {}),
            Authorization: `Bearer ${accessToken.value}`,
        };

        let response = await fetch(url, {
            ...options,
            headers,
        });

        if (response.status !== 401) {
            return response;
        }

        const refreshed = await refreshAccessToken();

        if (!refreshed || !accessToken.value) {
            return response;
        }

        response = await fetch(url, {
            ...options,
            headers: {
                ...(options.headers ?? {}),
                Authorization: `Bearer ${accessToken.value}`,
            },
        });

        return response;
    }

    return {
        accessToken,
        hasAccessToken,
        tokenSource,
        authStatus,
        bootstrapAuth,
        refreshAccessToken,
        fetchWithAuthorization,
        clearAuthTokens,
    };
}
