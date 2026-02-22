import Hls from 'hls.js';

export function createHlsPlaybackEngine({ getVideoElement, onFatalError }) {
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

    async function attachPlaylist(playlistText) {
        destroy();

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

        await currentVideoElement.play().catch(() => {
            return null;
        });
    }

    return {
        attachPlaylist,
        destroy,
    };
}
