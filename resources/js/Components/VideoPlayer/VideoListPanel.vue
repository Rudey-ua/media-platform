<script setup>
defineProps({
    videos: {
        type: Array,
        required: true,
    },
    isVideoListLoading: {
        type: Boolean,
        default: false,
    },
    hasAccessToken: {
        type: Boolean,
        required: true,
    },
    noTokenMessage: {
        type: String,
        required: true,
    },
    emptyVideosMessage: {
        type: String,
        required: true,
    },
    isPlaybackLoading: {
        type: Boolean,
        required: true,
    },
    canPlayVideo: {
        type: Function,
        required: true,
    },
    videoButtonClass: {
        type: Function,
        required: true,
    },
    videoStatusBadgeClass: {
        type: Function,
        required: true,
    },
    videoStatusBadgeLabel: {
        type: Function,
        required: true,
    },
    formatDate: {
        type: Function,
        required: true,
    },
    isVideoPlaying: {
        type: Function,
        required: true,
    },
    videoUnavailableMessage: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['select-video']);

function selectVideo(video) {
    emit('select-video', video);
}
</script>

<template>
    <aside class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h2 class="text-base font-semibold text-gray-900">Your videos</h2>
        </div>

        <div class="max-h-[70vh] overflow-y-auto p-2">
            <Transition name="fade" mode="out-in">
                <div v-if="isVideoListLoading && videos.length === 0" key="loading" class="flex flex-col items-center justify-center space-y-3 px-5 py-12">
                    <svg class="h-8 w-8 animate-spin text-[#0D9488]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm text-gray-500">Loading videos...</span>
                </div>

                <div v-else-if="!hasAccessToken" key="no-token" class="px-5 py-8 text-center text-sm text-gray-500">
                    {{ noTokenMessage }}
                </div>

                <div v-else-if="videos.length === 0" key="empty" class="px-5 py-8 text-center text-sm text-gray-500">
                    {{ emptyVideosMessage }}
                </div>

                <ul v-else key="list" class="divide-y divide-gray-100">
                    <li v-for="video in videos" :key="video.id" class="mb-1 last:mb-0">
                        <button
                            type="button"
                            :data-video-id="String(video.id)"
                            :aria-label="`Video ${video.id}`"
                            :disabled="!canPlayVideo(video) || isPlaybackLoading"
                            :class="videoButtonClass(video)"
                            @click="selectVideo(video)"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex flex-col items-start gap-1">
                                    <span class="font-medium">{{ video.title || 'Untitled video' }}</span>
                                    <span
                                        class="text-xs text-gray-500"
                                        v-text="formatDate(video.created_at)"
                                    ></span>
                                </div>
                                <span :class="videoStatusBadgeClass(video)" v-text="videoStatusBadgeLabel(video)"></span>
                            </div>

                            <p
                                v-if="!canPlayVideo(video) && videoUnavailableMessage(video) !== ''"
                                class="mt-2 text-xs text-gray-500"
                                v-text="videoUnavailableMessage(video)"
                            ></p>
                        </button>
                    </li>
                </ul>
            </Transition>
        </div>
</aside>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
