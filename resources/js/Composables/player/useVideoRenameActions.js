import { computed, ref } from 'vue';
import { API_BASE } from '../api/apiBase';
import { requestVideoTitleUpdate as requestVideoTitleUpdateApi } from '../video/playerApiClient';
import { canRenameVideoAction, isVideoActionInProgress } from './videoActionGuards';
import { normalizeVideoId } from './videoId';

export function useVideoRenameActions({
    fetchWithAuthorization,
    isPlaybackLoading,
    isDeleteFlowActive,
    findVideoById,
    replaceVideoInList,
}) {
    const pendingRenameVideo = ref(null);
    const renamingVideoId = ref(null);
    const videoRenameError = ref('');

    const isRenameModalOpen = computed(() => pendingRenameVideo.value !== null);
    const isRenameInProgress = computed(() => renamingVideoId.value !== null);

    const pendingRenameVideoTitle = computed(() => {
        if (!pendingRenameVideo.value || typeof pendingRenameVideo.value.title !== 'string') {
            return '';
        }

        return pendingRenameVideo.value.title;
    });

    function canRenameVideo(video) {
        return canRenameVideoAction(video);
    }

    function isVideoRenaming(video) {
        return isVideoActionInProgress(video, renamingVideoId.value);
    }

    function requestVideoRename(video) {
        const videoId = normalizeVideoId(video?.id);

        if (videoId === null || !canRenameVideo(video)) {
            return;
        }

        if (renamingVideoId.value !== null || isDeleteFlowActive() || isPlaybackLoading.value) {
            return;
        }

        pendingRenameVideo.value = {
            id: videoId,
            title: typeof video.title === 'string' ? video.title : '',
        };
        videoRenameError.value = '';
    }

    function requestVideoRenameById(videoId) {
        const video = findVideoById(videoId);

        if (!video) {
            return;
        }

        requestVideoRename(video);
    }

    function cancelVideoRename() {
        if (renamingVideoId.value !== null || isDeleteFlowActive()) {
            return;
        }

        pendingRenameVideo.value = null;
        videoRenameError.value = '';
    }

    async function confirmVideoRename(nextTitle) {
        if (!pendingRenameVideo.value) {
            return;
        }

        if (renamingVideoId.value !== null || isDeleteFlowActive() || isPlaybackLoading.value) {
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

    function clearVideoRenameError() {
        videoRenameError.value = '';
    }

    return {
        pendingRenameVideo,
        renamingVideoId,
        isRenameModalOpen,
        isRenameInProgress,
        pendingRenameVideoTitle,
        videoRenameError,
        canRenameVideo,
        isVideoRenaming,
        requestVideoRenameById,
        cancelVideoRename,
        confirmVideoRename,
        clearVideoRenameError,
    };
}
