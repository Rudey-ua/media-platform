import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { API_BASE } from './api/apiBase';
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
import {
    requestVideoDeletion as requestVideoDeletionApi,
    requestVideoPlaylist,
    requestVideoTitleUpdate as requestVideoTitleUpdateApi,
    requestVideosList,
} from './video/videoApi';

const AUTO_REFRESH_INTERVAL_MS = 10000;
const PLAYBACK_SESSION_REFRESH_WINDOW_MS = 5 * 60 * 1000;
const PLAYBACK_SESSION_RETRY_DELAY_MS = 10000;
const PLAYBACK_SESSION_MAX_RETRY_ATTEMPTS = 6;
const PROCESSING_STATUSES = new Set(['uploading', 'uploaded', 'processing']);
const PLAYER_IDLE_TITLE = 'Select a video to begin playback';
const PLAYER_IDLE_DESCRIPTION = '';

function normalizeVideoId(rawValue) {
    if (typeof rawValue === 'string') {
        const trimmedValue = rawValue.trim();

        return trimmedValue !== '' ? trimmedValue : null;
    }

    if (typeof rawValue === 'number' && Number.isFinite(rawValue)) {
        return String(rawValue);
    }
    return null;
}

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
    const isVideoListRefreshInFlight = ref(false);
    const isPlaybackLoading = ref(false);
    const deletingVideoId = ref(null);
    const refreshTimerId = ref(null);
    const playbackSessionRefreshTimerId = ref(null);
    const playbackSessionRetryAttempts = ref(0);
    const isPlaybackSessionRefreshing = ref(false);
    const playerStatus = ref('');
    const surfaceMode = ref('idle');
    const surfaceVariant = ref(null);
    const surfaceTitle = ref(PLAYER_IDLE_TITLE);
    const surfaceDescription = ref(PLAYER_IDLE_DESCRIPTION);
    const videoElement = ref(null);
    const pendingDeletionVideo = ref(null);
    const pendingRenameVideo = ref(null);
    const renamingVideoId = ref(null);
    const videoRenameError = ref('');
    let loadVideosPromise = null;

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
        playbackSessionRetryAttempts.value = 0;
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

        if (playbackSessionRetryAttempts.value >= PLAYBACK_SESSION_MAX_RETRY_ATTEMPTS) {
            return;
        }

        playbackSessionRetryAttempts.value += 1;

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

        playbackSessionRetryAttempts.value = 0;
        schedulePlaybackSessionRefresh(videoId, playbackPayload.sessionExpiresAt);
    }

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

    function canDeleteVideo(video) {
        if (!video) {
            return false;
        }
        return normalizeStatus(video.status) !== 'processing';
    }

    function canRenameVideo(video) {
        if (!video) {
            return false;
        }
        return normalizeVideoId(video.id) !== null;
    }

    function isVideoDeleting(video) {
        const videoId = normalizeVideoId(video?.id);

        if (videoId === null) {
            return false;
        }
        return deletingVideoId.value === videoId;
    }

    function isVideoRenaming(video) {
        const videoId = normalizeVideoId(video?.id);

        if (videoId === null) {
            return false;
        }
        return renamingVideoId.value === videoId;
    }

    function findVideoById(videoId) {
        const normalizedVideoId = normalizeVideoId(videoId);

        if (normalizedVideoId === null) {
            return null;
        }
        return videos.value.find((video) => normalizeVideoId(video?.id) === normalizedVideoId) ?? null;
    }

    const isDeleteModalOpen = computed(() => pendingDeletionVideo.value !== null);
    const pendingDeletionVideoTitle = computed(() => {
        if (!pendingDeletionVideo.value) {
            return 'Untitled video';
        }

        const title = pendingDeletionVideo.value.title;

        return typeof title === 'string' && title.trim() !== '' ? title.trim() : 'Untitled video';
    });
    const isDeleteInProgress = computed(() => deletingVideoId.value !== null);
    const isRenameModalOpen = computed(() => pendingRenameVideo.value !== null);
    const pendingRenameVideoTitle = computed(() => {
        if (!pendingRenameVideo.value || typeof pendingRenameVideo.value.title !== 'string') {
            return '';
        }

        return pendingRenameVideo.value.title;
    });
    const isRenameInProgress = computed(() => renamingVideoId.value !== null);

    function isVideoSelected(video) {
        return selectedVideoId.value !== null && selectedVideoId.value === normalizeVideoId(video?.id);
    }

    function isVideoPlaying(video) {
        return playingVideoId.value !== null && playingVideoId.value === normalizeVideoId(video?.id);
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

    const videoListItems = computed(() => {
        return videos.value
            .map((video) => {
                const videoId = normalizeVideoId(video?.id);

                if (videoId === null) {
                    return null;
                }
                const isBusy = isPlaybackLoading.value || isVideoDeleting(video) || isVideoRenaming(video);

                return {
                    id: videoId,
                    title: typeof video.title === 'string' && video.title.trim() !== '' ? video.title.trim() : 'Untitled video',
                    createdAtLabel: formatDate(video.created_at),
                    buttonClass: videoButtonClass(video),
                    statusBadgeClass: videoStatusBadgeClass(video),
                    statusBadgeLabel: videoStatusBadgeLabel(video),
                    unavailableMessage: videoUnavailableMessage(video),
                    canPlay: canPlayVideo(video),
                    canRename: canRenameVideo(video),
                    canDelete: canDeleteVideo(video),
                    isBusy,
                };
            })
            .filter((video) => video !== null);
    });

    function replaceVideoInList(updatedVideo) {
        if (!updatedVideo || typeof updatedVideo !== 'object') {
            return;
        }

        const updatedVideoId = normalizeVideoId(updatedVideo.id);

        if (updatedVideoId === null) {
            return;
        }

        videos.value = videos.value.map((video) => {
            if (normalizeVideoId(video?.id) !== updatedVideoId) {
                return video;
            }
            return {
                ...video,
                ...updatedVideo,
            };
        });
    }

    function canRefreshVideoListSilently() {
        if (pendingDeletionVideo.value !== null || pendingRenameVideo.value !== null) {
            return false;
        }
        if (deletingVideoId.value !== null || renamingVideoId.value !== null) {
            return false;
        }
        return true;
    }

    function configureAutoRefresh() {
        clearRefreshTimer();

        const hasProcessingVideos = videos.value.some((video) => PROCESSING_STATUSES.has(normalizeStatus(video.status)));

        if (!hasProcessingVideos) {
            return;
        }

        refreshTimerId.value = window.setInterval(() => {
            if (
                !isVideoListLoading.value
                && !isVideoListRefreshInFlight.value
                && hasAccessToken.value
                && canRefreshVideoListSilently()
            ) {
                loadVideos({ silent: true });
            }
        }, AUTO_REFRESH_INTERVAL_MS);
    }

    async function loadVideos({ silent = false } = {}) {
        if (!hasAccessToken.value) {
            isVideoListLoading.value = false;
            return;
        }
        if (loadVideosPromise) {
            return loadVideosPromise;
        }
        if (!silent) {
            isVideoListLoading.value = true;
        }
        isVideoListRefreshInFlight.value = true;

        loadVideosPromise = (async () => {
            try {
                const nextVideos = await requestVideosList({
                    fetchWithAuthorization,
                    apiBase: API_BASE,
                });
                videos.value = nextVideos;

                if (selectedVideoId.value && !videos.value.some((video) => normalizeVideoId(video?.id) === selectedVideoId.value)) {
                    selectedVideoId.value = null;
                }
                if (playingVideoId.value && !videos.value.some((video) => normalizeVideoId(video?.id) === playingVideoId.value)) {
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

                if (!silent) {
                    videos.value = [];
                }
                if (shouldShowListErrorState()) {
                    setPlayerSurfaceMode('message', {
                        title: 'Unable to load videos',
                        description: error instanceof Error ? error.message : 'Failed to load videos.',
                        variant: 'error',
                    });
                }
            } finally {
                isVideoListRefreshInFlight.value = false;

                if (!silent) {
                    isVideoListLoading.value = false;
                }
                loadVideosPromise = null;
            }
        })();
        return loadVideosPromise;
    }

    async function startPlayback(video) {
        if (!video || !canPlayVideo(video)) {
            return;
        }

        const targetVideoId = normalizeVideoId(video.id);

        if (targetVideoId === null) {
            return;
        }
        if (isPlaybackLoading.value) {
            return;
        }
        selectedVideoId.value = targetVideoId;
        playingVideoId.value = null;
        isPlaybackLoading.value = true;
        playerStatus.value = 'Loading video...';

        setPlayerSurfaceMode('loading', {
            title: 'Preparing video',
            description: '',
        });

        destroyPlayer();

        try {
            await fetchAndAttachPlaybackPlaylist(targetVideoId);

            playingVideoId.value = targetVideoId;
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

    function requestVideoDeletion(video) {
        const videoId = normalizeVideoId(video?.id);

        if (videoId === null || !canDeleteVideo(video)) {
            return;
        }

        if (
            deletingVideoId.value !== null
            || renamingVideoId.value !== null
            || pendingRenameVideo.value !== null
            || isPlaybackLoading.value
        ) {
            return;
        }

        videoRenameError.value = '';
        pendingDeletionVideo.value = {
            id: videoId,
            title: typeof video.title === 'string' ? video.title : null,
        };
    }

    function cancelVideoDeletion() {
        if (deletingVideoId.value !== null || renamingVideoId.value !== null) {
            return;
        }

        pendingDeletionVideo.value = null;
    }

    function requestVideoRename(video) {
        const videoId = normalizeVideoId(video?.id);

        if (videoId === null || !canRenameVideo(video)) {
            return;
        }
        if (
            renamingVideoId.value !== null
            || deletingVideoId.value !== null
            || pendingDeletionVideo.value !== null
            || isPlaybackLoading.value
        ) {
            return;
        }

        pendingRenameVideo.value = {
            id: videoId,
            title: typeof video.title === 'string' ? video.title : '',
        };
        videoRenameError.value = '';
    }

    function cancelVideoRename() {
        if (renamingVideoId.value !== null || deletingVideoId.value !== null) {
            return;
        }

        pendingRenameVideo.value = null;
        videoRenameError.value = '';
    }

    async function confirmVideoRename(nextTitle) {
        if (!pendingRenameVideo.value) {
            return;
        }
        if (renamingVideoId.value !== null || deletingVideoId.value !== null || isPlaybackLoading.value) {
            return;
        }
        const targetVideoId = pendingRenameVideo.value.id;
        renamingVideoId.value = targetVideoId;
        videoRenameError.value = '';

        try {
            const updatedVideo = await requestVideoTitleUpdateApi({
                fetchWithAuthorization,
                apiBase: API_BASE,
                videoId: targetVideoId,
                title: nextTitle,
            });

            replaceVideoInList(updatedVideo);
            pendingRenameVideo.value = null;
        } catch (error) {
            videoRenameError.value = error instanceof Error ? error.message : 'Unable to rename video.';
        } finally {
            renamingVideoId.value = null;
        }
    }

    async function confirmVideoDeletion() {
        if (!pendingDeletionVideo.value) {
            return;
        }
        if (deletingVideoId.value !== null || renamingVideoId.value !== null || isPlaybackLoading.value) {
            return;
        }

        const targetVideoId = pendingDeletionVideo.value.id;
        deletingVideoId.value = targetVideoId;

        try {
            await requestVideoDeletionApi({
                fetchWithAuthorization,
                apiBase: API_BASE,
                videoId: targetVideoId,
            });

            if (playingVideoId.value === targetVideoId || selectedVideoId.value === targetVideoId) {
                destroyPlayer();
                playingVideoId.value = null;
                selectedVideoId.value = null;
                playerStatus.value = '';
                setPlayerSurfaceMode('idle');
            }

            pendingDeletionVideo.value = null;
            await loadVideos({ silent: true });
        } catch (error) {
            if (shouldShowListErrorState()) {
                setPlayerSurfaceMode('message', {
                    title: 'Unable to delete video',
                    description: error instanceof Error ? error.message : 'Failed to delete video.',
                    variant: 'error',
                });
            }
        } finally {
            deletingVideoId.value = null;
        }
    }

    async function bootstrapPlayerPage() {
        const isAuthenticatedForApi = await bootstrapAuth();

        if (!isAuthenticatedForApi) {
            playerStatus.value = '';
            isVideoListLoading.value = false;
            setPlayerSurfaceMode('message', {
                title: 'No valid API token found',
                description: 'Use API /api/v1/login to get access_token. Refresh token is handled via HttpOnly cookie.',
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

    function handleVideoSelection(videoId) {
        const video = findVideoById(videoId);

        if (!video) {
            return;
        }
        handleVideoClick(video);
    }

    function requestVideoRenameById(videoId) {
        const video = findVideoById(videoId);

        if (!video) {
            return;
        }
        requestVideoRename(video);
    }

    function requestVideoDeletionById(videoId) {
        const video = findVideoById(videoId);

        if (!video) {
            return;
        }
        requestVideoDeletion(video);
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
        videoListItems,
        videos,
        isVideoListLoading,
        isPlaybackLoading,
        isDeleteModalOpen,
        isDeleteInProgress,
        isRenameModalOpen,
        isRenameInProgress,
        videoRenameError,
        playerStatus,
        surfaceMode,
        surfaceTitle,
        surfaceDescription,
        surfaceBorderClass,
        noTokenMessage,
        emptyVideosMessage,
        hasAccessToken,
        pendingDeletionVideoTitle,
        pendingRenameVideoTitle,
        handleVideoSelection,
        requestVideoDeletionById,
        requestVideoRenameById,
        cancelVideoDeletion,
        confirmVideoDeletion,
        cancelVideoRename,
        confirmVideoRename,
        setVideoElement,
    };
}
