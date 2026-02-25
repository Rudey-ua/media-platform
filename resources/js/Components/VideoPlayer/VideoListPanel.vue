<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ApiLoadingState from '../ApiLoadingState.vue';

const props = defineProps({
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
    canManageVideos: {
        type: Boolean,
        default: false,
    },
    canPlayVideo: {
        type: Function,
        required: true,
    },
    canDeleteVideo: {
        type: Function,
        required: true,
    },
    canRenameVideo: {
        type: Function,
        required: true,
    },
    isVideoDeleting: {
        type: Function,
        required: true,
    },
    isVideoRenaming: {
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

const emit = defineEmits(['select-video', 'delete-video', 'rename-video']);

const actionMenuVideoId = ref(null);
const rootElement = ref(null);

watch(
    () => props.videos.map((video) => String(video.id)),
    (videoIds) => {
        if (actionMenuVideoId.value !== null && !videoIds.includes(actionMenuVideoId.value)) {
            actionMenuVideoId.value = null;
        }
    },
    { immediate: true },
);

function resolveVideoId(video) {
    return String(video?.id ?? '');
}

function closeActionMenu() {
    actionMenuVideoId.value = null;
}

function hasActions(video) {
    return props.canManageVideos && (props.canRenameVideo(video) || props.canDeleteVideo(video));
}

function canRenameAction(video) {
    return props.canRenameVideo(video)
        && !props.isPlaybackLoading
        && !props.isVideoDeleting(video)
        && !props.isVideoRenaming(video);
}

function canDeleteAction(video) {
    return props.canDeleteVideo(video)
        && !props.isPlaybackLoading
        && !props.isVideoDeleting(video)
        && !props.isVideoRenaming(video);
}

function toggleActionMenu(video) {
    const videoId = resolveVideoId(video);

    if (videoId === '') {
        return;
    }

    actionMenuVideoId.value = actionMenuVideoId.value === videoId ? null : videoId;
}

function handleVideoCardClick(video) {
    closeActionMenu();
    emit('select-video', video);
}

function handleRenameAction(video) {
    if (!canRenameAction(video)) {
        return;
    }

    closeActionMenu();
    emit('rename-video', video);
}

function handleDeleteAction(video) {
    if (!canDeleteAction(video)) {
        return;
    }

    closeActionMenu();
    emit('delete-video', video);
}

function handleDocumentPointerDown(event) {
    if (actionMenuVideoId.value === null) {
        return;
    }

    if (!(event.target instanceof Node)) {
        closeActionMenu();
        return;
    }

    if (rootElement.value instanceof HTMLElement && rootElement.value.contains(event.target)) {
        return;
    }

    closeActionMenu();
}

function handleDocumentKeyDown(event) {
    if (event.key === 'Escape') {
        closeActionMenu();
    }
}

onMounted(() => {
    window.addEventListener('pointerdown', handleDocumentPointerDown);
    window.addEventListener('keydown', handleDocumentKeyDown);
});

onBeforeUnmount(() => {
    window.removeEventListener('pointerdown', handleDocumentPointerDown);
    window.removeEventListener('keydown', handleDocumentKeyDown);
});
</script>

<template>
    <aside ref="rootElement" class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h2 class="text-base font-semibold text-gray-900">Your videos</h2>
        </div>

        <div class="max-h-[70vh] overflow-y-auto p-2">
            <Transition name="fade" mode="out-in">
                <ApiLoadingState
                    v-if="props.isVideoListLoading && props.videos.length === 0"
                    key="loading"
                    message="Loading videos..."
                />

                <div v-else-if="!props.hasAccessToken" key="no-token" class="px-5 py-8 text-center text-sm text-gray-500">
                    {{ props.noTokenMessage }}
                </div>

                <div v-else-if="props.videos.length === 0" key="empty" class="px-5 py-8 text-center text-sm text-gray-500">
                    {{ props.emptyVideosMessage }}
                </div>

                <ul v-else key="list" class="divide-y divide-gray-100">
                    <li v-for="video in props.videos" :key="video.id" class="mb-1 last:mb-0">
                        <div class="relative overflow-visible rounded-xl">
                            <button
                                type="button"
                                :aria-label="`Video ${video.title || video.id}`"
                                :disabled="!props.canPlayVideo(video) || props.isPlaybackLoading || props.isVideoDeleting(video) || props.isVideoRenaming(video)"
                                :class="[
                                    props.videoButtonClass(video),
                                    hasActions(video) ? 'pr-12' : '',
                                ]"
                                @click="handleVideoCardClick(video)"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="font-medium">{{ video.title || 'Untitled video' }}</span>
                                        <span
                                            class="text-xs text-gray-500"
                                            v-text="props.formatDate(video.created_at)"
                                        ></span>
                                    </div>
                                    <span :class="props.videoStatusBadgeClass(video)" v-text="props.videoStatusBadgeLabel(video)"></span>
                                </div>

                                <p
                                    v-if="!props.canPlayVideo(video) && props.videoUnavailableMessage(video) !== ''"
                                    class="mt-2 text-xs text-gray-500"
                                    v-text="props.videoUnavailableMessage(video)"
                                ></p>
                            </button>

                            <div v-if="hasActions(video)" class="absolute right-2 top-2 z-10">
                                <button
                                    type="button"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 transition-colors hover:bg-gray-200/70 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0D9488]/50 disabled:cursor-not-allowed disabled:text-gray-300"
                                    :aria-expanded="actionMenuVideoId === resolveVideoId(video)"
                                    aria-haspopup="menu"
                                    aria-label="Video actions"
                                    :disabled="props.isPlaybackLoading || props.isVideoDeleting(video) || props.isVideoRenaming(video)"
                                    @click.stop="toggleActionMenu(video)"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <circle cx="6" cy="12" r="1.5" />
                                        <circle cx="12" cy="12" r="1.5" />
                                        <circle cx="18" cy="12" r="1.5" />
                                    </svg>
                                </button>

                                <Transition name="menu-fade">
                                    <div
                                        v-if="actionMenuVideoId === resolveVideoId(video)"
                                        class="absolute right-0 mt-1 w-40 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg ring-1 ring-black/5"
                                        role="menu"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-start px-3 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:text-gray-400"
                                            :disabled="!canRenameAction(video)"
                                            role="menuitem"
                                            @click.stop="handleRenameAction(video)"
                                        >
                                            Rename
                                        </button>
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-start px-3 py-2 text-sm text-red-600 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:text-red-300"
                                            :disabled="!canDeleteAction(video)"
                                            role="menuitem"
                                            @click.stop="handleDeleteAction(video)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </Transition>
                            </div>
                        </div>
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

.menu-fade-enter-active,
.menu-fade-leave-active {
  transition: opacity 0.15s ease;
}

.menu-fade-enter-from,
.menu-fade-leave-to {
  opacity: 0;
}
</style>
