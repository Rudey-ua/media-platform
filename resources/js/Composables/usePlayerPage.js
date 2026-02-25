import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useAuthSession } from './auth/useAuthSession';
import { useCatalogState } from './player/useCatalogState';
import { usePlaybackState } from './player/usePlaybackState';
import { useSurfaceState } from './player/useSurfaceState';
import { useVideoActionsState } from './player/useVideoActionsState';
import { useVideoListItems } from './player/useVideoListItems';

export function usePlayerPage() {
    const {
        hasAccessToken,
        fetchWithAuthorization,
        bootstrapAuth,
    } = useAuthSession();

    const selectedVideoId = ref(null);
    const playingVideoId = ref(null);
    const playerStatus = ref('');

    const {
        surfaceMode,
        surfaceTitle,
        surfaceDescription,
        surfaceBorderClass,
        setPlayerSurfaceMode,
    } = useSurfaceState();

    const playbackState = usePlaybackState({
        fetchWithAuthorization,
        selectedVideoId,
        playingVideoId,
        setPlayerSurfaceMode,
        setPlayerStatus(status) {
            playerStatus.value = status;
        },
    });

    function shouldShowListErrorState() {
        if (playbackState.isPlaybackLoading.value) {
            return false;
        }

        if (playingVideoId.value) {
            return false;
        }

        return true;
    }

    let canRefreshSilently = () => true;

    const catalogState = useCatalogState({
        hasAccessToken,
        fetchWithAuthorization,
        selectedVideoId,
        playingVideoId,
        isPlaybackLoading: playbackState.isPlaybackLoading,
        canRefreshSilently: () => canRefreshSilently(),
        onNoVideosAvailable() {
            playbackState.destroyPlayback();
            setPlayerSurfaceMode('idle');
            playerStatus.value = '';
        },
        onListLoadError(errorMessage) {
            if (!shouldShowListErrorState()) {
                return;
            }

            setPlayerSurfaceMode('message', {
                title: 'Unable to load videos',
                description: errorMessage,
                variant: 'error',
            });
        },
    });

    const actionsState = useVideoActionsState({
        fetchWithAuthorization,
        isPlaybackLoading: playbackState.isPlaybackLoading,
        selectedVideoId,
        playingVideoId,
        findVideoById: catalogState.findVideoById,
        replaceVideoInList: catalogState.replaceVideoInList,
        loadVideos: catalogState.loadVideos,
        destroyPlayback: playbackState.destroyPlayback,
        setPlayerStatus(status) {
            playerStatus.value = status;
        },
        setPlayerSurfaceMode,
        shouldShowListErrorState,
    });

    canRefreshSilently = actionsState.canRefreshVideoListSilently;

    const {
        canPlayVideo,
        videoListItems,
    } = useVideoListItems({
        videos: catalogState.videos,
        selectedVideoId,
        playingVideoId,
        isPlaybackLoading: playbackState.isPlaybackLoading,
        canDeleteVideo: actionsState.canDeleteVideo,
        canRenameVideo: actionsState.canRenameVideo,
        isVideoDeleting: actionsState.isVideoDeleting,
        isVideoRenaming: actionsState.isVideoRenaming,
    });

    const noTokenMessage = 'No access token found.';
    const emptyVideosMessage = 'No videos yet.';

    async function bootstrapPlayerPage() {
        const isAuthenticatedForApi = await bootstrapAuth();

        if (!isAuthenticatedForApi) {
            playerStatus.value = '';
            catalogState.isVideoListLoading.value = false;
            setPlayerSurfaceMode('message', {
                title: 'No valid API token found',
                description: 'Use API /api/v1/login to get access_token. Refresh token is handled via HttpOnly cookie.',
                variant: 'error',
            });
            return;
        }

        setPlayerSurfaceMode('idle');

        await catalogState.loadVideos();
    }

    function handleVideoSelection(videoId) {
        const video = catalogState.findVideoById(videoId);

        if (!video || !canPlayVideo(video)) {
            return;
        }

        playbackState.startPlayback(video.id);
    }

    function handleBeforeUnload() {
        catalogState.clearRefreshTimer();
        playbackState.destroyPlayback();
    }

    onMounted(() => {
        window.addEventListener('beforeunload', handleBeforeUnload);
        bootstrapPlayerPage();
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', handleBeforeUnload);
        catalogState.clearRefreshTimer();
        playbackState.teardownPlayback();
    });

    return {
        videoListItems,
        videos: catalogState.videos,
        isVideoListLoading: catalogState.isVideoListLoading,
        isPlaybackLoading: playbackState.isPlaybackLoading,
        isDeleteModalOpen: actionsState.isDeleteModalOpen,
        isDeleteInProgress: actionsState.isDeleteInProgress,
        isRenameModalOpen: actionsState.isRenameModalOpen,
        isRenameInProgress: actionsState.isRenameInProgress,
        videoRenameError: actionsState.videoRenameError,
        playerStatus,
        surfaceMode,
        surfaceTitle,
        surfaceDescription,
        surfaceBorderClass,
        noTokenMessage,
        emptyVideosMessage,
        hasAccessToken,
        pendingDeletionVideoTitle: actionsState.pendingDeletionVideoTitle,
        pendingRenameVideoTitle: actionsState.pendingRenameVideoTitle,
        handleVideoSelection,
        requestVideoDeletionById: actionsState.requestVideoDeletionById,
        requestVideoRenameById: actionsState.requestVideoRenameById,
        cancelVideoDeletion: actionsState.cancelVideoDeletion,
        confirmVideoDeletion: actionsState.confirmVideoDeletion,
        cancelVideoRename: actionsState.cancelVideoRename,
        confirmVideoRename: actionsState.confirmVideoRename,
        setVideoElement: playbackState.setVideoElement,
    };
}
