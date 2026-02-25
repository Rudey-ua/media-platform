import { computed } from 'vue';
import {
    canPlayVideo as canPlayVideoByStatus,
    formatDate,
    videoButtonClass as videoButtonClassByState,
    videoStatusBadgeClass as videoStatusBadgeClassByState,
    videoStatusBadgeLabel as videoStatusBadgeLabelByState,
    videoUnavailableMessage as videoUnavailableMessageByStatus,
} from '../video/playerViewModel';
import { normalizeVideoId } from './videoId';

export function useVideoListItems({
    videos,
    selectedVideoId,
    playingVideoId,
    isPlaybackLoading,
    canDeleteVideo,
    canRenameVideo,
    isVideoDeleting,
    isVideoRenaming,
}) {
    function canPlayVideo(video) {
        return canPlayVideoByStatus(video);
    }

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

    return {
        canPlayVideo,
        videoListItems,
    };
}
