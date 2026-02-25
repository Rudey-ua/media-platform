<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppHeader from '../../Components/AppHeader.vue';
import DeleteVideoModal from '../../Components/VideoPlayer/DeleteVideoModal.vue';
import PlayerSurfacePanel from '../../Components/VideoPlayer/PlayerSurfacePanel.vue';
import RenameVideoModal from '../../Components/VideoPlayer/RenameVideoModal.vue';
import VideoListPanel from '../../Components/VideoPlayer/VideoListPanel.vue';
import { useVideoPlayer } from '../../Composables/useVideoPlayer';

const props = defineProps({
    videoUploadUrl: {
        type: String,
        required: true,
    },
    profileUrl: {
        type: String,
        required: true,
    },
    canUploadVideo: {
        type: Boolean,
        required: true,
    },
});

const {
    videos,
    isVideoListLoading,
    hasAccessToken,
    noTokenMessage,
    emptyVideosMessage,
    isPlaybackLoading,
    surfaceMode,
    surfaceTitle,
    surfaceDescription,
    surfaceBorderClass,
    canPlayVideo,
    canDeleteVideo,
    canRenameVideo,
    videoButtonClass,
    videoStatusBadgeClass,
    videoStatusBadgeLabel,
    formatDate,
    isVideoPlaying,
    isVideoDeleting,
    isVideoRenaming,
    isDeleteModalOpen,
    isDeleteInProgress,
    pendingDeletionVideoTitle,
    isRenameModalOpen,
    isRenameInProgress,
    pendingRenameVideoTitle,
    videoRenameError,
    videoUnavailableMessage,
    handleVideoClick,
    requestVideoDeletion,
    requestVideoRename,
    cancelVideoDeletion,
    confirmVideoDeletion,
    cancelVideoRename,
    confirmVideoRename,
    setVideoElement,
} = useVideoPlayer();
</script>

<template>
    <Head title="HLS Player" />

    <main class="min-h-screen bg-gray-50">
        <AppHeader :profile-url="props.profileUrl" />

        <section class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold text-gray-900">Player</h1>
                <Link
                    v-if="props.canUploadVideo"
                    :href="props.videoUploadUrl"
                    class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#0F766E] active:bg-[#115E59] focus:outline focus:outline-[3px] focus:outline-[rgba(13,148,136,0.35)] focus:outline-offset-2"
                >
                    Upload Video
                </Link>
            </div>

            <div class="flex flex-col gap-8 lg:flex-row">
                <div class="flex flex-1 flex-col gap-4">
                    <PlayerSurfacePanel
                        :surface-mode="surfaceMode"
                        :surface-title="surfaceTitle"
                        :surface-description="surfaceDescription"
                        :surface-border-class="surfaceBorderClass"
                        @video-element="setVideoElement"
                    />
                </div>

                <div class="w-full lg:w-[400px]">
                    <VideoListPanel
                        :videos="videos"
                        :is-video-list-loading="isVideoListLoading"
                        :has-access-token="hasAccessToken"
                        :no-token-message="noTokenMessage"
                        :empty-videos-message="emptyVideosMessage"
                        :is-playback-loading="isPlaybackLoading"
                        :can-manage-videos="props.canUploadVideo"
                        :can-play-video="canPlayVideo"
                        :can-delete-video="canDeleteVideo"
                        :can-rename-video="canRenameVideo"
                        :is-video-deleting="isVideoDeleting"
                        :is-video-renaming="isVideoRenaming"
                        :video-button-class="videoButtonClass"
                        :video-status-badge-class="videoStatusBadgeClass"
                        :video-status-badge-label="videoStatusBadgeLabel"
                        :format-date="formatDate"
                        :is-video-playing="isVideoPlaying"
                        :video-unavailable-message="videoUnavailableMessage"
                        @select-video="handleVideoClick"
                        @rename-video="requestVideoRename"
                        @delete-video="requestVideoDeletion"
                    />
                </div>
            </div>
        </section>

        <DeleteVideoModal
            :is-open="isDeleteModalOpen"
            :video-title="pendingDeletionVideoTitle"
            :is-deleting="isDeleteInProgress"
            @cancel="cancelVideoDeletion"
            @confirm="confirmVideoDeletion"
        />

        <RenameVideoModal
            :is-open="isRenameModalOpen"
            :initial-title="pendingRenameVideoTitle"
            :error-message="videoRenameError"
            :is-renaming="isRenameInProgress"
            @cancel="cancelVideoRename"
            @confirm="confirmVideoRename"
        />
    </main>
</template>
