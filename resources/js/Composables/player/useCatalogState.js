import { ref } from 'vue';
import { API_BASE } from '../api/apiBase';
import { requestVideosList } from '../video/playerApiClient';
import { normalizeStatus } from '../video/playerViewModel';
import {
    AUTO_REFRESH_INTERVAL_MS,
    PROCESSING_STATUSES,
} from './playerConstants';
import { normalizeVideoId } from './videoId';

export function useCatalogState({
    hasAccessToken,
    fetchWithAuthorization,
    selectedVideoId,
    playingVideoId,
    isPlaybackLoading,
    canRefreshSilently,
    onNoVideosAvailable,
    onListLoadError,
}) {
    const videos = ref([]);
    const isVideoListLoading = ref(true);
    const isVideoListRefreshInFlight = ref(false);
    const refreshTimerId = ref(null);
    let loadVideosPromise = null;

    function clearRefreshTimer() {
        if (refreshTimerId.value !== null) {
            window.clearInterval(refreshTimerId.value);
            refreshTimerId.value = null;
        }
    }

    function findVideoById(videoId) {
        const normalizedVideoId = normalizeVideoId(videoId);

        if (normalizedVideoId === null) {
            return null;
        }

        return videos.value.find((video) => normalizeVideoId(video?.id) === normalizedVideoId) ?? null;
    }

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
                && canRefreshSilently()
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
                    onNoVideosAvailable();
                }

                configureAutoRefresh();
            } catch (error) {
                clearRefreshTimer();

                if (!silent) {
                    videos.value = [];
                }

                onListLoadError(error instanceof Error ? error.message : 'Failed to load videos.');
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

    return {
        videos,
        isVideoListLoading,
        loadVideos,
        clearRefreshTimer,
        findVideoById,
        replaceVideoInList,
    };
}
