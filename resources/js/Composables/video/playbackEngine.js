import Hls from 'hls.js';

const MANIFEST_LOAD_TIMEOUT_MS = 20000;
const NATIVE_METADATA_TIMEOUT_MS = 15000;

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

export function createPlaybackEngine({ getVideoElement, onFatalError, onPlaybackSessionExpired }) {
    let playbackLibraryInstance = null;
    let playlistBlobUrl = null;

    function destroy() {
        if (playbackLibraryInstance) {
            playbackLibraryInstance.destroy();
            playbackLibraryInstance = null;
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

            playbackLibraryInstance = hls;
            const source = playlistBlobUrl;

            await new Promise((resolve, reject) => {
                let isSettled = false;
                let manifestParsed = false;
                const timeoutId = window.setTimeout(() => {
                    if (isSettled) {
                        return;
                    }
                    isSettled = true;
                    cleanup();
                    reject(new Error('Timed out while loading HLS manifest.'));
                }, MANIFEST_LOAD_TIMEOUT_MS);

                const cleanup = () => {
                    window.clearTimeout(timeoutId);
                    hls.off(Hls.Events.ERROR, onError);
                    hls.off(Hls.Events.MEDIA_ATTACHED, onMediaAttached);
                    hls.off(Hls.Events.MANIFEST_PARSED, onManifestParsed);
                };

                const onError = (_event, data) => {
                    if (!data || !data.fatal) {
                        return;
                    }
                    const statusCode = extractHttpStatusCode(data);

                    if (statusCode === 401 || statusCode === 403) {
                        if (!manifestParsed) {
                            if (isSettled) {
                                return;
                            }
                            isSettled = true;
                            cleanup();
                            reject(new Error('Playback session expired.'));
                            return;
                        }

                        const playbackState = {
                            resumeTime: Number.isFinite(currentVideoElement.currentTime) ? currentVideoElement.currentTime : 0,
                            autoplay: !currentVideoElement.paused,
                        };

                        if (isSettled) {
                            return;
                        }
                        isSettled = true;
                        cleanup();
                        destroy();
                        onPlaybackSessionExpired?.(playbackState);
                        return;
                    }

                    const errorMessage = `Fatal playback error: ${data.details || data.type || 'unknown'}`;

                    if (manifestParsed) {
                        if (isSettled) {
                            return;
                        }
                        isSettled = true;
                        cleanup();
                        destroy();
                        onFatalError(errorMessage);
                        return;
                    }
                    if (isSettled) {
                        return;
                    }
                    isSettled = true;
                    cleanup();
                    reject(new Error(errorMessage));
                };
                const onMediaAttached = () => {
                    hls.loadSource(source);
                };

                const onManifestParsed = () => {
                    if (isSettled) {
                        return;
                    }
                    manifestParsed = true;
                    isSettled = true;
                    cleanup();
                    resolve();
                };

                hls.on(Hls.Events.ERROR, onError);
                hls.on(Hls.Events.MEDIA_ATTACHED, onMediaAttached);
                hls.on(Hls.Events.MANIFEST_PARSED, onManifestParsed);
                hls.attachMedia(currentVideoElement);
            });
        } else if (currentVideoElement.canPlayType('application/vnd.apple.mpegurl')) {
            await new Promise((resolve, reject) => {
                let isSettled = false;
                const timeoutId = window.setTimeout(() => {
                    if (isSettled) {
                        return;
                    }
                    isSettled = true;
                    cleanup();
                    reject(new Error('Timed out while loading native HLS metadata.'));
                }, NATIVE_METADATA_TIMEOUT_MS);

                const cleanup = () => {
                    window.clearTimeout(timeoutId);
                    currentVideoElement.removeEventListener('loadedmetadata', onLoadedMetadata);
                    currentVideoElement.removeEventListener('error', onError);
                };
                const onLoadedMetadata = () => {
                    if (isSettled) {
                        return;
                    }
                    isSettled = true;
                    cleanup();
                    resolve();
                };

                const onError = () => {
                    if (isSettled) {
                        return;
                    }
                    isSettled = true;
                    cleanup();
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
