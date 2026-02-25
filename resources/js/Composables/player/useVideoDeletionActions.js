import { computed, ref } from 'vue';
import { API_BASE } from '../api/apiBase';
import { requestVideoDeletion as requestVideoDeletionApi } from '../video/playerApiClient';
import {
    canDeleteVideoAction,
    isVideoActionInProgress,
    resolveActionVideoTitle,
} from './videoActionGuards';
import { normalizeVideoId } from './videoId';

export function useVideoDeletionActions({
    fetchWithAuthorization,
    isPlaybackLoading,
    isRenameFlowActive,
    selectedVideoId,
    playingVideoId,
    findVideoById,
    loadVideos,
    destroyPlayback,
    setPlayerStatus,
    setPlayerSurfaceMode,
    shouldShowListErrorState,
}) {
    const pendingDeletionVideo = ref(null);
    const deletingVideoId = ref(null);

    const isDeleteModalOpen = computed(() => pendingDeletionVideo.value !== null);
    const isDeleteInProgress = computed(() => deletingVideoId.value !== null);

    const pendingDeletionVideoTitle = computed(() => {
        return resolveActionVideoTitle(pendingDeletionVideo.value);
    });

    function canDeleteVideo(video) {
        return canDeleteVideoAction(video);
    }

    function isVideoDeleting(video) {
        return isVideoActionInProgress(video, deletingVideoId.value);
    }

    function requestVideoDeletion(video) {
        const videoId = normalizeVideoId(video?.id);

        if (videoId === null || !canDeleteVideo(video)) {
            return;
        }

        if (deletingVideoId.value !== null || isRenameFlowActive() || isPlaybackLoading.value) {
            return;
        }

        pendingDeletionVideo.value = {
            id: videoId,
            title: typeof video.title === 'string' ? video.title : null,
        };
    }

    function requestVideoDeletionById(videoId) {
        const video = findVideoById(videoId);

        if (!video) {
            return;
        }

        requestVideoDeletion(video);
    }

    function cancelVideoDeletion() {
        if (deletingVideoId.value !== null || isRenameFlowActive()) {
            return;
        }

        pendingDeletionVideo.value = null;
    }

    async function confirmVideoDeletion() {
        if (!pendingDeletionVideo.value) {
            return;
        }

        if (deletingVideoId.value !== null || isRenameFlowActive() || isPlaybackLoading.value) {
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
                destroyPlayback();
                playingVideoId.value = null;
                selectedVideoId.value = null;
                setPlayerStatus('');
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

    return {
        pendingDeletionVideo,
        deletingVideoId,
        isDeleteModalOpen,
        isDeleteInProgress,
        pendingDeletionVideoTitle,
        canDeleteVideo,
        isVideoDeleting,
        requestVideoDeletionById,
        cancelVideoDeletion,
        confirmVideoDeletion,
    };
}
