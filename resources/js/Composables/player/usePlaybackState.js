import { ref } from 'vue';
import { API_BASE } from '../api/apiBase';
import { requestVideoPlaylist } from '../video/playerApiClient';
import { createPlaybackEngine } from '../video/playbackEngine';
import { normalizeVideoId } from './videoId';
import { usePlaybackSessionTimer } from './usePlaybackSessionTimer';
import { usePlaybackVideoElement } from './usePlaybackVideoElement';

export function usePlaybackState({
    fetchWithAuthorization,
    selectedVideoId,
    playingVideoId,
    setPlayerSurfaceMode,
    setPlayerStatus,
}) {
    const isPlaybackLoading = ref(false);
    const isPlaybackSessionRefreshing = ref(false);

    function formatVideoElementError(details) {
        if (!details || typeof details !== 'object') {
            return 'Video element reported a playback error.';
        }

        const errorCode = Number(details.code);
        const mediaErrorCodeLabelMap = {
            1: 'MEDIA_ERR_ABORTED',
            2: 'MEDIA_ERR_NETWORK',
            3: 'MEDIA_ERR_DECODE',
            4: 'MEDIA_ERR_SRC_NOT_SUPPORTED',
        };
        const mediaErrorLabel = mediaErrorCodeLabelMap[errorCode];

        if (typeof mediaErrorLabel === 'string') {
            return `Video element reported a playback error (${mediaErrorLabel}).`;
        }

        return 'Video element reported a playback error.';
    }

    const videoElementState = usePlaybackVideoElement({
        onVideoElementError(errorDetails) {
            if (!isPlaybackLoading.value) {
                playingVideoId.value = null;
                const videoElementErrorMessage = formatVideoElementError(errorDetails);

                setPlayerStatus(`Playback failed. ${videoElementErrorMessage}`);
                setPlayerSurfaceMode('message', {
                    title: 'Playback failed',
                    description: videoElementErrorMessage,
                    variant: 'error',
                });
            }
        },
    });

    async function refreshPlaybackSession({
        reason = 'manual',
        videoId = null,
        force = false,
        playbackState = null,
    } = {}) {
        const targetVideoId = normalizeVideoId(videoId) ?? playingVideoId.value;

        if (typeof targetVideoId !== 'string' || targetVideoId === '') {
            return;
        }

        if (isPlaybackSessionRefreshing.value || (!force && isPlaybackLoading.value)) {
            return;
        }

        isPlaybackSessionRefreshing.value = true;
        const effectivePlaybackState = playbackState && typeof playbackState === 'object'
            ? {
                resumeTime: Number(playbackState.resumeTime) || 0,
                autoplay: playbackState.autoplay !== false,
            }
            : videoElementState.readCurrentPlaybackState();

        try {
            await fetchAndAttachPlaybackPlaylist(targetVideoId, effectivePlaybackState);
            playingVideoId.value = targetVideoId;
            selectedVideoId.value = targetVideoId;
            setPlayerStatus('Playing video');
            setPlayerSurfaceMode('playing');
        } catch (error) {
            if (reason === 'scheduled' || reason === 'retry') {
                playbackSessionTimer.scheduleRetry(targetVideoId);
                return;
            }

            destroyPlayback();
            playingVideoId.value = null;
            setPlayerStatus(error instanceof Error ? error.message : 'Playback refresh failed.');
            setPlayerSurfaceMode('message', {
                title: 'Playback interrupted',
                description: error instanceof Error ? error.message : 'Playback refresh failed.',
                variant: 'error',
            });
        } finally {
            isPlaybackSessionRefreshing.value = false;
        }
    }

    const playbackSessionTimer = usePlaybackSessionTimer({
        onRefreshRequested: refreshPlaybackSession,
    });

    const playbackEngine = createPlaybackEngine({
        getVideoElement() {
            return videoElementState.videoElement.value;
        },
        onPlaybackSessionExpired(playbackState) {
            refreshPlaybackSession({
                reason: 'segment-access-expired',
                force: true,
                playbackState,
            }).catch(() => {
                return null;
            });
        },
        onFatalError(errorMessage) {
            playingVideoId.value = null;
            setPlayerStatus(errorMessage);
            setPlayerSurfaceMode('message', {
                title: 'Playback interrupted',
                description: errorMessage,
                variant: 'error',
            });
        },
    });

    function destroyPlayback() {
        playbackSessionTimer.teardownTimer();
        isPlaybackSessionRefreshing.value = false;
        playbackEngine.destroy();
    }

    async function fetchAndAttachPlaybackPlaylist(videoId, playbackState = { resumeTime: 0, autoplay: true }) {
        const playbackPayload = await requestVideoPlaylist({
            fetchWithAuthorization,
            apiBase: API_BASE,
            videoId,
        });

        await playbackEngine.attachPlaylist(playbackPayload.playlistText, {
            resumeTime: playbackState.resumeTime,
            autoplay: playbackState.autoplay,
        });

        playbackSessionTimer.resetRetryAttempts();
        playbackSessionTimer.scheduleRefresh(videoId, playbackPayload.sessionExpiresAt);
    }

    async function startPlayback(videoId) {
        const targetVideoId = normalizeVideoId(videoId);

        if (targetVideoId === null || isPlaybackLoading.value) {
            return;
        }

        selectedVideoId.value = targetVideoId;
        playingVideoId.value = null;
        isPlaybackLoading.value = true;
        setPlayerStatus('Loading video...');

        setPlayerSurfaceMode('loading', {
            title: 'Preparing video',
            description: '',
        });

        destroyPlayback();

        try {
            await fetchAndAttachPlaybackPlaylist(targetVideoId);
            playingVideoId.value = targetVideoId;
            setPlayerStatus('Playing video');
            setPlayerSurfaceMode('playing');
        } catch (error) {
            destroyPlayback();
            playingVideoId.value = null;
            setPlayerStatus(error instanceof Error ? error.message : 'Unable to start playback.');
            setPlayerSurfaceMode('message', {
                title: 'Video is not available',
                description: error instanceof Error ? error.message : 'Unable to start playback.',
                variant: 'error',
            });
        } finally {
            isPlaybackLoading.value = false;
        }
    }

    function teardownPlayback() {
        videoElementState.teardownVideoElement();
        destroyPlayback();
    }

    return {
        isPlaybackLoading,
        startPlayback,
        destroyPlayback,
        setVideoElement: videoElementState.setVideoElement,
        teardownPlayback,
    };
}
