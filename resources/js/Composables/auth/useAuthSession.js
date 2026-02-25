import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { API_BASE } from '../api/apiBase';
import {
    accessTokenFromObject,
    isAccessTokenExpired,
    resolveAccessToken,
} from './tokenAccess';

export function useAuthSession() {
    const page = usePage();
    const accessToken = ref(null);
    const tokenSource = ref(null);
    const refreshInFlight = ref(null);
    const bootstrapInFlight = ref(null);
    const authStatus = ref('Token lookup pending...');

    const hasAccessToken = computed(() => {
        return typeof accessToken.value === 'string' && accessToken.value !== '';
    });

    function updateAccessToken(token, source) {
        accessToken.value = token;
        tokenSource.value = source;
    }

    function clearAuthTokens() {
        accessToken.value = null;
        tokenSource.value = null;
        refreshInFlight.value = null;
        bootstrapInFlight.value = null;
        authStatus.value = 'Logged out.';
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
        if (bootstrapInFlight.value) {
            return bootstrapInFlight.value;
        }

        bootstrapInFlight.value = (async () => {
            const authTokensPayload = page.props?.auth?.api_tokens || {};
            const resolvedToken = resolveAccessToken(authTokensPayload);

            if (!resolvedToken || !resolvedToken.token) {
                const refreshed = await refreshAccessToken();

                if (!refreshed || !accessToken.value) {
                    authStatus.value = 'No valid API token found.';
                    return false;
                }

                return true;
            }

            updateAccessToken(resolvedToken.token, resolvedToken.source);
            authStatus.value = `Token source: ${resolvedToken.source}.`;

            return true;
        })();

        try {
            return await bootstrapInFlight.value;
        } finally {
            bootstrapInFlight.value = null;
        }
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
