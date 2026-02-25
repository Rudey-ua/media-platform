import { ref } from 'vue';
import {
    PLAYBACK_SESSION_MAX_RETRY_ATTEMPTS,
    PLAYBACK_SESSION_REFRESH_WINDOW_MS,
    PLAYBACK_SESSION_RETRY_DELAY_MS,
} from './playerConstants';

export function usePlaybackSessionTimer({ onRefreshRequested }) {
    const refreshTimerId = ref(null);
    const retryAttempts = ref(0);

    function clearRefreshTimer() {
        if (refreshTimerId.value !== null) {
            window.clearTimeout(refreshTimerId.value);
            refreshTimerId.value = null;
        }
    }

    function parseSessionExpiry(sessionExpiresAt) {
        if (typeof sessionExpiresAt !== 'string' || sessionExpiresAt.trim() === '') {
            return null;
        }

        const expiresAtTimestamp = Date.parse(sessionExpiresAt);

        if (!Number.isFinite(expiresAtTimestamp)) {
            return null;
        }

        return expiresAtTimestamp;
    }

    function scheduleRefresh(videoId, sessionExpiresAt) {
        clearRefreshTimer();

        if (typeof videoId !== 'string' || videoId === '') {
            return;
        }

        const expiresAtTimestamp = parseSessionExpiry(sessionExpiresAt);

        if (expiresAtTimestamp === null) {
            return;
        }

        const refreshAtTimestamp = Math.max(Date.now() + 1000, expiresAtTimestamp - PLAYBACK_SESSION_REFRESH_WINDOW_MS);
        const delayMs = Math.max(0, refreshAtTimestamp - Date.now());

        refreshTimerId.value = window.setTimeout(() => {
            onRefreshRequested({
                reason: 'scheduled',
                videoId,
            }).catch(() => {
                return null;
            });
        }, delayMs);
    }

    function scheduleRetry(videoId) {
        clearRefreshTimer();

        if (typeof videoId !== 'string' || videoId === '') {
            return;
        }

        if (retryAttempts.value >= PLAYBACK_SESSION_MAX_RETRY_ATTEMPTS) {
            return;
        }

        retryAttempts.value += 1;

        refreshTimerId.value = window.setTimeout(() => {
            onRefreshRequested({
                reason: 'retry',
                videoId,
            }).catch(() => {
                return null;
            });
        }, PLAYBACK_SESSION_RETRY_DELAY_MS);
    }

    function resetRetryAttempts() {
        retryAttempts.value = 0;
    }

    function teardownTimer() {
        clearRefreshTimer();
        resetRetryAttempts();
    }

    return {
        scheduleRefresh,
        scheduleRetry,
        resetRetryAttempts,
        teardownTimer,
    };
}
