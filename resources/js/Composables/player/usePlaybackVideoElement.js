import { ref, watch } from 'vue';

export function usePlaybackVideoElement({ onVideoElementError }) {
    const videoElement = ref(null);

    const handleVideoElementError = () => {
        onVideoElementError();
    };

    watch(videoElement, (currentElement, previousElement) => {
        if (previousElement instanceof HTMLVideoElement) {
            previousElement.removeEventListener('error', handleVideoElementError);
        }

        if (currentElement instanceof HTMLVideoElement) {
            currentElement.addEventListener('error', handleVideoElementError);
        }
    });

    function setVideoElement(element) {
        videoElement.value = element instanceof HTMLVideoElement ? element : null;
    }

    function readCurrentPlaybackState() {
        const currentVideoElement = videoElement.value;

        if (!(currentVideoElement instanceof HTMLVideoElement)) {
            return {
                resumeTime: 0,
                autoplay: true,
            };
        }

        const elementCurrentTime = Number(currentVideoElement.currentTime);
        const resumeTime = Number.isFinite(elementCurrentTime) && elementCurrentTime > 0 ? elementCurrentTime : 0;

        return {
            resumeTime,
            autoplay: !currentVideoElement.paused,
        };
    }

    function teardownVideoElement() {
        if (videoElement.value instanceof HTMLVideoElement) {
            videoElement.value.removeEventListener('error', handleVideoElementError);
        }
    }

    return {
        videoElement,
        setVideoElement,
        readCurrentPlaybackState,
        teardownVideoElement,
    };
}
