import { normalizeStatus } from '../video/playerViewModel';
import { normalizeVideoId } from './videoId';

export function canDeleteVideoAction(video) {
    if (!video) {
        return false;
    }

    return normalizeStatus(video.status) !== 'processing';
}

export function canRenameVideoAction(video) {
    if (!video) {
        return false;
    }

    return normalizeVideoId(video.id) !== null;
}

export function isVideoActionInProgress(video, activeVideoId) {
    const videoId = normalizeVideoId(video?.id);

    if (videoId === null) {
        return false;
    }

    return activeVideoId === videoId;
}

export function resolveActionVideoTitle(video, fallbackTitle = 'Untitled video') {
    if (!video || typeof video.title !== 'string') {
        return fallbackTitle;
    }

    const normalizedTitle = video.title.trim();

    return normalizedTitle !== '' ? normalizedTitle : fallbackTitle;
}
