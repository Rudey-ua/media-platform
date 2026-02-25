function parsePayloadMessage(payload, fallbackMessage) {
    if (payload && typeof payload.message === 'string' && payload.message !== '') {
        return payload.message;
    }

    return fallbackMessage;
}

function sortVideosByCreatedAt(videos) {
    return videos.slice().sort((left, right) => {
        const leftTime = new Date(left.created_at).getTime();
        const rightTime = new Date(right.created_at).getTime();

        return rightTime - leftTime;
    });
}

export async function requestVideosList({ fetchWithAuthorization, apiBase }) {
    const response = await fetchWithAuthorization(`${apiBase}/api/v1/videos`, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
        },
    });

    const payload = await response.json().catch(() => {
        return null;
    });

    if (response.status === 401) {
        throw new Error('Unauthorized. API token is expired/invalid and could not be refreshed. Web login does not renew API JWT.');
    }

    if (!response.ok) {
        throw new Error(parsePayloadMessage(payload, `Unable to load videos (${response.status}).`));
    }

    const payloadVideos = payload?.data?.videos;

    if (!Array.isArray(payloadVideos)) {
        return [];
    }

    return sortVideosByCreatedAt(payloadVideos);
}

async function extractPlaybackErrorMessage(response) {
    const contentType = response.headers.get('content-type') ?? '';
    const responseBody = await response.text().catch(() => {
        return '';
    });
    const trimmedBody = responseBody.trim();

    if (trimmedBody === '') {
        return `Playback request failed (${response.status}).`;
    }

    if (contentType.includes('application/json')) {
        try {
            const payload = JSON.parse(trimmedBody);

            if (payload && typeof payload.message === 'string' && payload.message !== '') {
                return payload.message;
            }
        } catch (_error) {
            return trimmedBody;
        }
    }

    return trimmedBody;
}

export async function requestVideoPlaylist({ fetchWithAuthorization, apiBase, videoId }) {
    const response = await fetchWithAuthorization(`${apiBase}/api/v1/videos/${encodeURIComponent(videoId)}/playback`, {
        method: 'GET',
        headers: {
            Accept: 'application/vnd.apple.mpegurl, application/json',
        },
    });

    if (response.status === 401) {
        throw new Error('Unauthorized. API token is expired/invalid and could not be refreshed. Web login does not renew API JWT.');
    }

    if (!response.ok) {
        throw new Error(await extractPlaybackErrorMessage(response));
    }

    const playlistText = await response.text();

    if (playlistText.trim() === '') {
        throw new Error('Received an empty playback playlist.');
    }

    const sessionExpiresAt = response.headers.get('x-playback-session-expires-at');

    return {
        playlistText,
        sessionExpiresAt: typeof sessionExpiresAt === 'string' && sessionExpiresAt.trim() !== ''
            ? sessionExpiresAt.trim()
            : null,
    };
}

export async function requestVideoDeletion({ fetchWithAuthorization, apiBase, videoId }) {
    const response = await fetchWithAuthorization(`${apiBase}/api/v1/videos/${encodeURIComponent(videoId)}`, {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
        },
    });

    const payload = await response.json().catch(() => {
        return null;
    });

    if (response.status === 401) {
        throw new Error('Unauthorized. API token is expired/invalid and could not be refreshed. Web login does not renew API JWT.');
    }

    if (!response.ok) {
        throw new Error(parsePayloadMessage(payload, `Unable to delete video (${response.status}).`));
    }
}

export async function requestVideoTitleUpdate({ fetchWithAuthorization, apiBase, videoId, title }) {
    const normalizedTitle = typeof title === 'string' ? title.trim() : '';

    const response = await fetchWithAuthorization(`${apiBase}/api/v1/videos/${encodeURIComponent(videoId)}`, {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            title: normalizedTitle !== '' ? normalizedTitle : null,
        }),
    });

    const payload = await response.json().catch(() => {
        return null;
    });

    if (response.status === 401) {
        throw new Error('Unauthorized. API token is expired/invalid and could not be refreshed. Web login does not renew API JWT.');
    }

    if (!response.ok) {
        throw new Error(parsePayloadMessage(payload, `Unable to update video title (${response.status}).`));
    }

    const updatedVideo = payload?.data?.video;

    if (!updatedVideo || typeof updatedVideo !== 'object') {
        throw new Error('Video title was updated, but response format is invalid.');
    }

    return updatedVideo;
}
