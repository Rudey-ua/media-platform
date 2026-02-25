<script setup>
import { ref } from 'vue';
import ApiLoadingState from '../ApiLoadingState.vue';
import VideoListItem from './VideoListItem.vue';
import { useVideoActionMenu } from '../../Composables/player/useVideoActionMenu';

const props = defineProps({
    videoItems: {
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
    canManageVideos: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['select-video', 'delete-video', 'rename-video']);

const rootElement = ref(null);
const scrollContainerElement = ref(null);

const {
    actionMenuVideoId,
    actionMenuPlacement,
    closeActionMenu,
    toggleActionMenu,
} = useVideoActionMenu({
    videoItems() {
        return props.videoItems;
    },
    rootElement,
    scrollContainerElement,
});

function handleVideoSelection(videoId) {
    closeActionMenu();
    emit('select-video', videoId);
}

function handleRenameVideo(videoId) {
    closeActionMenu();
    emit('rename-video', videoId);
}

function handleDeleteVideo(videoId) {
    closeActionMenu();
    emit('delete-video', videoId);
}

function handleToggleActionMenu(videoId, triggerElement) {
    const normalizedVideoId = String(videoId ?? '');

    toggleActionMenu(normalizedVideoId, triggerElement);
}
</script>

<template>
    <aside ref="rootElement" class="flex h-[420px] max-h-[70vh] flex-col overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h2 class="text-base font-semibold text-gray-900">Your videos</h2>
        </div>

        <div ref="scrollContainerElement" class="min-h-0 flex-1 overflow-y-auto p-2">
            <Transition name="fade" mode="out-in">
                <ApiLoadingState
                    v-if="props.isVideoListLoading && props.videoItems.length === 0"
                    key="loading"
                    message="Loading videos..."
                />

                <div v-else-if="!props.hasAccessToken" key="no-token" class="px-5 py-8 text-center text-sm text-gray-500">
                    {{ props.noTokenMessage }}
                </div>

                <div v-else-if="props.videoItems.length === 0" key="empty" class="px-5 py-8 text-center text-sm text-gray-500">
                    {{ props.emptyVideosMessage }}
                </div>

                <ul v-else key="list" class="divide-y divide-gray-100">
                    <VideoListItem
                        v-for="video in props.videoItems"
                        :key="video.id"
                        :video="video"
                        :can-manage-videos="props.canManageVideos"
                        :is-action-menu-open="actionMenuVideoId === String(video.id)"
                        :action-menu-placement="actionMenuPlacement"
                        @select-video="handleVideoSelection"
                        @rename-video="handleRenameVideo"
                        @delete-video="handleDeleteVideo"
                        @toggle-action-menu="handleToggleActionMenu"
                    />
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
