import { useVideoDeletionActions } from './useVideoDeletionActions';
import { useVideoRenameActions } from './useVideoRenameActions';

export function useVideoActionsState({
    fetchWithAuthorization,
    isPlaybackLoading,
    selectedVideoId,
    playingVideoId,
    findVideoById,
    replaceVideoInList,
    loadVideos,
    destroyPlayback,
    setPlayerStatus,
    setPlayerSurfaceMode,
    shouldShowListErrorState,
}) {
    let renameState = null;

    const deletionState = useVideoDeletionActions({
        fetchWithAuthorization,
        isPlaybackLoading,
        isRenameFlowActive() {
            if (renameState === null) {
                return false;
            }

            return renameState.pendingRenameVideo.value !== null || renameState.renamingVideoId.value !== null;
        },
        selectedVideoId,
        playingVideoId,
        findVideoById,
        loadVideos,
        destroyPlayback,
        setPlayerStatus,
        setPlayerSurfaceMode,
        shouldShowListErrorState,
    });

    renameState = useVideoRenameActions({
        fetchWithAuthorization,
        isPlaybackLoading,
        isDeleteFlowActive() {
            return deletionState.pendingDeletionVideo.value !== null || deletionState.deletingVideoId.value !== null;
        },
        findVideoById,
        replaceVideoInList,
    });

    function canRefreshVideoListSilently() {
        if (deletionState.pendingDeletionVideo.value !== null || renameState.pendingRenameVideo.value !== null) {
            return false;
        }

        if (deletionState.deletingVideoId.value !== null || renameState.renamingVideoId.value !== null) {
            return false;
        }

        return true;
    }

    function requestVideoDeletionById(videoId) {
        renameState.clearVideoRenameError();
        deletionState.requestVideoDeletionById(videoId);
    }

    return {
        isDeleteModalOpen: deletionState.isDeleteModalOpen,
        isDeleteInProgress: deletionState.isDeleteInProgress,
        isRenameModalOpen: renameState.isRenameModalOpen,
        isRenameInProgress: renameState.isRenameInProgress,
        pendingDeletionVideoTitle: deletionState.pendingDeletionVideoTitle,
        pendingRenameVideoTitle: renameState.pendingRenameVideoTitle,
        videoRenameError: renameState.videoRenameError,
        canDeleteVideo: deletionState.canDeleteVideo,
        canRenameVideo: renameState.canRenameVideo,
        isVideoDeleting: deletionState.isVideoDeleting,
        isVideoRenaming: renameState.isVideoRenaming,
        canRefreshVideoListSilently,
        requestVideoRenameById: renameState.requestVideoRenameById,
        requestVideoDeletionById,
        cancelVideoDeletion: deletionState.cancelVideoDeletion,
        confirmVideoDeletion: deletionState.confirmVideoDeletion,
        cancelVideoRename: renameState.cancelVideoRename,
        confirmVideoRename: renameState.confirmVideoRename,
    };
}
