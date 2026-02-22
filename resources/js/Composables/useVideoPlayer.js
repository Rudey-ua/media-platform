import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useApiAuth } from './auth/useApiAuth';
import { createHlsPlaybackEngine } from './video/hlsPlaybackEngine';
import {
    canPlayVideo as canPlayVideoByStatus,
    formatDate,
    normalizeStatus,
    videoButtonClass as videoButtonClassByState,
    videoStatusBadgeClass as videoStatusBadgeClassByState,
    videoStatusBadgeLabel as videoStatusBadgeLabelByState,
    videoUnavailableMessage as videoUnavailableMessageByStatus,
} from './video/playerPresentation';
import { requestVideoPlaylist, requestVideosList } from './video/videoApi';

const API_BASE = window.location.origin;
const AUTO_REFRESH_INTERVAL_MS = 10000;
const PLAYBACK_SESSION_REFRESH_WINDOW_MS = 5 * 60 * 1000;
const PLAYBACK_SESSION_RETRY_DELAY_MS = 10000;
const PROCESSING_STATUSES = new Set(['uploading', 'uploaded', 'processing']);
const PLAYER_IDLE_TITLE = 'Select a video to begin playback';
const PLAYER_IDLE_DESCRIPTION = '';

export function useVideoPlayer() {
    const {
        hasAccessToken,
        fetchWithAuthorization,
        bootstrapAuth,
    } = useApiAuth();

    const videos = ref([]);
    const selectedVideoId = ref(null);
    const playingVideoId = ref(null);
    const isVideoListLoading = ref(true);
    const isPlaybackLoading = ref(false);
    const refreshTimerId = ref(null);
    const playbackSessionRefreshTimerId = ref(null);
    const isPlaybackSessionRefreshing = ref(false);
    const playerStatus = ref('');
    const surfaceMode = ref('idle');
    const surfaceVariant = ref(null);
    const surfaceTitle = ref(PLAYER_IDLE_TITLE);
    const surfaceDescription = ref(PLAYER_IDLE_DESCRIPTION);
    const videoElement = ref(null);

    const noTokenMessage = 'No access token found.';
    const emptyVideosMessage = 'No videos yet.';

    function setPlayerSurfaceMode(mode, payload = {}) {
        surfaceMode.value = mode;
        surfaceVariant.value = payload.variant ?? null;

        if (mode === 'loading') {
            surfaceTitle.value = payload.title ?? 'Preparing playback...';
            surfaceDescription.value = payload.description ?? 'Fetching playlist and initializing video player.';
            return;
        }

        if (mode === 'idle') {
            surfaceTitle.value = PLAYER_IDLE_TITLE;
            surfaceDescription.value = PLAYER_IDLE_DESCRIPTION;
            return;
        }

        surfaceTitle.value = payload.title ?? PLAYER_IDLE_TITLE;
        surfaceDescription.value = payload.description ?? PLAYER_IDLE_DESCRIPTION;
    }

    function shouldShowListErrorState() {
        if (isPlaybackLoading.value) {
            return false;
        }

        if (playingVideoId.value) {
            return false;
        }

        return true;
    }

    const playbackEngine = createHlsPlaybackEngine({
        getVideoElement() {
            return videoElement.value;
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
            playerStatus.value = errorMessage;
            setPlayerSurfaceMode('message', {
                title: 'Playback interrupted',
                description: errorMessage,
                variant: 'error',
            });
        },
    });

    function destroyPlayer() {
        clearPlaybackSessionRefreshTimer();
        isPlaybackSessionRefreshing.value = false;
        playbackEngine.destroy();
    }

    const surfaceBorderClass = computed(() => {
        if (surfaceMode.value === 'message' && surfaceVariant.value === 'error') {
            return 'border-red-300';
        }

        return 'border-slate-200';
    });

    const handleVideoElementError = () => {
        if (!isPlaybackLoading.value) {
            playingVideoId.value = null;
            playerStatus.value = 'Playback failed. Video element reported an error.';
            setPlayerSurfaceMode('message', {
                title: 'Playback failed',
                description: 'Video element reported a playback error.',
                variant: 'error',
            });
        }
    };

    watch(videoElement, (currentElement, previousElement) => {
        if (previousElement instanceof HTMLVideoElement) {
            previousElement.removeEventListener('error', handleVideoElementError);
        }

        if (currentElement instanceof HTMLVideoElement) {
            currentElement.addEventListener('error', handleVideoElementError);
        }
    });

    function setVideoElement(element) {
        videoElement.value = element instanceof HTMLVideoElement ? element : null;
    }

    function clearRefreshTimer() {
        if (refreshTimerId.value !== null) {
            window.clearInterval(refreshTimerId.value);
            refreshTimerId.value = null;
        }
    }

    function clearPlaybackSessionRefreshTimer() {
        if (playbackSessionRefreshTimerId.value !== null) {
            window.clearTimeout(playbackSessionRefreshTimerId.value);
            playbackSessionRefreshTimerId.value = null;
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

    function schedulePlaybackSessionRefresh(videoId, sessionExpiresAt) {
        clearPlaybackSessionRefreshTimer();

        if (typeof videoId !== 'string' || videoId === '') {
            return;
        }

        const expiresAtTimestamp = parseSessionExpiry(sessionExpiresAt);

        if (expiresAtTimestamp === null) {
            return;
        }

        const refreshAtTimestamp = Math.max(
            Date.now() + 1000,
            expiresAtTimestamp - PLAYBACK_SESSION_REFRESH_WINDOW_MS,
        );
        const delayMs = Math.max(0, refreshAtTimestamp - Date.now());

        playbackSessionRefreshTimerId.value = window.setTimeout(() => {
            refreshPlaybackSession({
                reason: 'scheduled',
                videoId,
            }).catch(() => {
                return null;
            });
        }, delayMs);
    }

    function schedulePlaybackSessionRetry(videoId) {
        clearPlaybackSessionRefreshTimer();

        if (typeof videoId !== 'string' || videoId === '') {
            return;
        }

        playbackSessionRefreshTimerId.value = window.setTimeout(() => {
            refreshPlaybackSession({
                reason: 'retry',
                videoId,
            }).catch(() => {
                return null;
            });
        }, PLAYBACK_SESSION_RETRY_DELAY_MS);
    }

    function currentPlaybackState() {
        const currentVideoElement = videoElement.value;

        if (!(currentVideoElement instanceof HTMLVideoElement)) {
            return {
                resumeTime: 0,
                autoplay: true,
            };
        }

        const elementCurrentTime = Number(currentVideoElement.currentTime);
        const resumeTime = Number.isFinite(elementCurrentTime) && elementCurrentTime > 0 ? elementCurrentTime : 0;

        return {
            resumeTime,
            autoplay: !currentVideoElement.paused,
        };
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

        schedulePlaybackSessionRefresh(videoId, playbackPayload.sessionExpiresAt);
    }

    async function refreshPlaybackSession({
        reason = 'manual',
        videoId = null,
        force = false,
        playbackState = null,
    } = {}) {
        const targetVideoId = typeof videoId === 'string' && videoId !== '' ? videoId : playingVideoId.value;

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
            : currentPlaybackState();

        try {
            await fetchAndAttachPlaybackPlaylist(targetVideoId, effectivePlaybackState);
            playingVideoId.value = targetVideoId;
            selectedVideoId.value = targetVideoId;
            playerStatus.value = 'Playing video';
            setPlayerSurfaceMode('playing');
        } catch (error) {
            if (reason === 'scheduled' || reason === 'retry') {
                schedulePlaybackSessionRetry(targetVideoId);
                return;
            }

            destroyPlayer();
            playingVideoId.value = null;
            playerStatus.value = error instanceof Error ? error.message : 'Playback refresh failed.';
            setPlayerSurfaceMode('message', {
                title: 'Playback interrupted',
                description: playerStatus.value,
                variant: 'error',
            });
        } finally {
            isPlaybackSessionRefreshing.value = false;
        }
    }

    function canPlayVideo(video) {
        return canPlayVideoByStatus(video);
    }

    function isVideoSelected(video) {
        return selectedVideoId.value === video.id;
    }

    function isVideoPlaying(video) {
        return playingVideoId.value === video.id;
    }

    function videoButtonClass(video) {
        return videoButtonClassByState({
            selected: isVideoSelected(video),
            playing: isVideoPlaying(video),
        });
    }

    function videoStatusBadgeClass(video) {
        return videoStatusBadgeClassByState({
            playing: isVideoPlaying(video),
            status: video.status,
        });
    }

    function videoStatusBadgeLabel(video) {
        return videoStatusBadgeLabelByState({
            playing: isVideoPlaying(video),
            status: video.status,
        });
    }

    function videoUnavailableMessage(video) {
        return videoUnavailableMessageByStatus(video.status);
    }

    function configureAutoRefresh() {
        clearRefreshTimer();

        const hasProcessingVideos = videos.value.some((video) => PROCESSING_STATUSES.has(normalizeStatus(video.status)));

        if (!hasProcessingVideos) {
            return;
        }

        refreshTimerId.value = window.setInterval(() => {
            if (!isVideoListLoading.value && hasAccessToken.value) {
                loadVideos({ silent: true });
            }
        }, AUTO_REFRESH_INTERVAL_MS);
    }

    async function loadVideos({ silent = false } = {}) {
        if (!hasAccessToken.value) {
            isVideoListLoading.value = false;
            return;
        }

        if (!silent) {
            isVideoListLoading.value = true;
        }

        try {
            videos.value = await requestVideosList({
                fetchWithAuthorization,
                apiBase: API_BASE,
            });

            if (selectedVideoId.value && !videos.value.some((video) => video.id === selectedVideoId.value)) {
                selectedVideoId.value = null;
            }

            if (playingVideoId.value && !videos.value.some((video) => video.id === playingVideoId.value)) {
                playingVideoId.value = null;
            }

            if (!isPlaybackLoading.value && !playingVideoId.value && videos.value.length === 0) {
                destroyPlayer();
                setPlayerSurfaceMode('idle');
                playerStatus.value = '';
            }

            configureAutoRefresh();
        } catch (error) {
            clearRefreshTimer();
            videos.value = [];

            if (shouldShowListErrorState()) {
                setPlayerSurfaceMode('message', {
                    title: 'Unable to load videos',
                    description: error instanceof Error ? error.message : 'Failed to load videos.',
                    variant: 'error',
                });
            }
        } finally {
            isVideoListLoading.value = false;
        }
    }

    async function startPlayback(video) {
        if (!video || !canPlayVideo(video)) {
            return;
        }

        if (isPlaybackLoading.value) {
            return;
        }

        selectedVideoId.value = video.id;
        playingVideoId.value = null;
        isPlaybackLoading.value = true;
        playerStatus.value = 'Loading video...';

        setPlayerSurfaceMode('loading', {
            title: 'Preparing video',
            description: '',
        });

        destroyPlayer();

        try {
            await fetchAndAttachPlaybackPlaylist(video.id);

            playingVideoId.value = video.id;
            playerStatus.value = 'Playing video';
            setPlayerSurfaceMode('playing');
        } catch (error) {
            destroyPlayer();
            playingVideoId.value = null;
            playerStatus.value = error instanceof Error ? error.message : 'Unable to start playback.';
            setPlayerSurfaceMode('message', {
                title: 'Video is not available',
                description: playerStatus.value,
                variant: 'error',
            });
        } finally {
            isPlaybackLoading.value = false;
        }
    }

    async function bootstrapPlayerPage() {
        const isAuthenticatedForApi = await bootstrapAuth();

        if (!isAuthenticatedForApi) {
            playerStatus.value = '';
            isVideoListLoading.value = false;
            setPlayerSurfaceMode('message', {
                title: 'No valid API token found',
                description: 'Use API /api/v1/login to get access_token + refresh_token and store them in browser storage.',
                variant: 'error',
            });
            return;
        }

        setPlayerSurfaceMode('idle');

        await loadVideos();
    }

    function handleVideoClick(video) {
        if (!video || !canPlayVideo(video)) {
            return;
        }

        startPlayback(video);
    }

    function handleBeforeUnload() {
        clearRefreshTimer();
        clearPlaybackSessionRefreshTimer();
        destroyPlayer();
    }

    onMounted(() => {
        window.addEventListener('beforeunload', handleBeforeUnload);
        bootstrapPlayerPage();
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', handleBeforeUnload);

        if (videoElement.value instanceof HTMLVideoElement) {
            videoElement.value.removeEventListener('error', handleVideoElementError);
        }

        clearRefreshTimer();
        clearPlaybackSessionRefreshTimer();
        destroyPlayer();
    });

    return {
        videos,
        isVideoListLoading,
        isPlaybackLoading,
        playerStatus,
        surfaceMode,
        surfaceTitle,
        surfaceDescription,
        surfaceBorderClass,
        noTokenMessage,
        emptyVideosMessage,
        hasAccessToken,
        canPlayVideo,
        videoButtonClass,
        videoStatusBadgeClass,
        videoStatusBadgeLabel,
        formatDate,
        isVideoPlaying,
        videoUnavailableMessage,
        handleVideoClick,
        setVideoElement,
    };
}
