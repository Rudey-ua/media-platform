import Hls from 'hls.js';

function extractHttpStatusCode(errorData) {
    const responseCode = errorData?.response?.code ?? errorData?.response?.status;
    const parsedCode = Number(responseCode);

    return Number.isFinite(parsedCode) ? parsedCode : null;
}

async function ensureMetadataLoaded(videoElement) {
    if (!(videoElement instanceof HTMLVideoElement)) {
        return;
    }

    if (videoElement.readyState >= 1) {
        return;
    }

    await new Promise((resolve) => {
        const onLoadedMetadata = () => {
            videoElement.removeEventListener('loadedmetadata', onLoadedMetadata);
            videoElement.removeEventListener('error', onError);
            resolve();
        };

        const onError = () => {
            videoElement.removeEventListener('loadedmetadata', onLoadedMetadata);
            videoElement.removeEventListener('error', onError);
            resolve();
        };

        videoElement.addEventListener('loadedmetadata', onLoadedMetadata, { once: true });
        videoElement.addEventListener('error', onError, { once: true });
    });
}

export function createHlsPlaybackEngine({ getVideoElement, onFatalError, onPlaybackSessionExpired }) {
    let hlsInstance = null;
    let playlistBlobUrl = null;

    function destroy() {
        if (hlsInstance) {
            hlsInstance.destroy();
            hlsInstance = null;
        }

        if (playlistBlobUrl) {
            URL.revokeObjectURL(playlistBlobUrl);
            playlistBlobUrl = null;
        }

        const currentVideoElement = getVideoElement();

        if (currentVideoElement instanceof HTMLVideoElement) {
            currentVideoElement.pause();
            currentVideoElement.removeAttribute('src');
            currentVideoElement.load();
        }
    }

    async function attachPlaylist(playlistText, options = {}) {
        destroy();

        const requestedResumeTime = Number(options.resumeTime);
        const resumeTime = Number.isFinite(requestedResumeTime) && requestedResumeTime > 0 ? requestedResumeTime : 0;
        const autoplay = options.autoplay !== false;

        playlistBlobUrl = URL.createObjectURL(
            new Blob([playlistText], { type: 'application/vnd.apple.mpegurl' }),
        );

        const currentVideoElement = getVideoElement();

        if (!(currentVideoElement instanceof HTMLVideoElement)) {
            throw new Error('Video element is not available.');
        }

        if (Hls.isSupported()) {
            const hls = new Hls({
                enableWorker: true,
            });

            hlsInstance = hls;
            const source = playlistBlobUrl;

            await new Promise((resolve, reject) => {
                let isResolved = false;

                hls.on(Hls.Events.ERROR, (_event, data) => {
                    if (!data || !data.fatal) {
                        return;
                    }
                    const statusCode = extractHttpStatusCode(data);

                    if (statusCode === 401 || statusCode === 403) {
                        if (!isResolved) {
                            reject(new Error('Playback session expired.'));
                            return;
                        }

                        const playbackState = {
                            resumeTime: Number.isFinite(currentVideoElement.currentTime) ? currentVideoElement.currentTime : 0,
                            autoplay: !currentVideoElement.paused,
                        };

                        destroy();
                        onPlaybackSessionExpired?.(playbackState);
                        return;
                    }

                    const errorMessage = `Fatal HLS error: ${data.details || data.type || 'unknown'}`;

                    if (isResolved) {
                        destroy();
                        onFatalError(errorMessage);
                        return;
                    }

                    reject(new Error(errorMessage));
                });

                hls.on(Hls.Events.MEDIA_ATTACHED, () => {
                    hls.loadSource(source);
                });

                hls.on(Hls.Events.MANIFEST_PARSED, () => {
                    isResolved = true;
                    resolve();
                });

                hls.attachMedia(currentVideoElement);
            });
        } else if (currentVideoElement.canPlayType('application/vnd.apple.mpegurl')) {
            await new Promise((resolve, reject) => {
                const onLoadedMetadata = () => {
                    currentVideoElement.removeEventListener('loadedmetadata', onLoadedMetadata);
                    currentVideoElement.removeEventListener('error', onError);
                    resolve();
                };

                const onError = () => {
                    currentVideoElement.removeEventListener('loadedmetadata', onLoadedMetadata);
                    currentVideoElement.removeEventListener('error', onError);
                    reject(new Error('Native HLS failed to load.'));
                };

                currentVideoElement.addEventListener('loadedmetadata', onLoadedMetadata);
                currentVideoElement.addEventListener('error', onError);
                currentVideoElement.src = playlistBlobUrl;
            });
        } else {
            throw new Error('HLS playback is not supported in this browser.');
        }

        if (resumeTime > 0) {
            await ensureMetadataLoaded(currentVideoElement);

            if (Number.isFinite(currentVideoElement.duration)) {
                const safeResumeTime = Math.min(Math.max(0, resumeTime), Math.max(0, currentVideoElement.duration - 0.25));
                currentVideoElement.currentTime = safeResumeTime;
            } else {
                currentVideoElement.currentTime = resumeTime;
            }
        }

        if (autoplay) {
            await currentVideoElement.play().catch(() => {
                return null;
            });
        }
    }

    return {
        attachPlaylist,
        destroy,
    };
}
