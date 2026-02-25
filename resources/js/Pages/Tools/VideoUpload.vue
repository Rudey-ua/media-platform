<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppHeader from '../../Components/AppHeader.vue';
import { useUploadPage } from '../../Composables/useUploadPage';

const props = defineProps({
    videoPlayerUrl: {
        type: String,
        required: true,
    },
    profileUrl: {
        type: String,
        required: true,
    },
});

const {
    selectedFileName,
    selectedFileSize,
    canStartUpload,
    isUploading,
    uploadStage,
    uploadStatus,
    uploadError,
    uploadProgress,
    uploadTransferDetails,
    videoTitle,
    setSelectedFile,
    startUpload,
} = useUploadPage();

const isDropzoneDragging = ref(false);
const fileInputElement = ref(null);

const TITLE_ADJECTIVES = [
    'Cosmic', 'Silent', 'Golden', 'Electric', 'Vivid',
    'Frozen', 'Ancient', 'Neon', 'Velvet', 'Crystal',
    'Wild', 'Radiant', 'Misty', 'Blazing', 'Serene',
    'Lunar', 'Savage', 'Gentle', 'Broken', 'Infinite',
];

const TITLE_NOUNS = [
    'Echo', 'Storm', 'Horizon', 'Wave', 'Signal',
    'Dream', 'Vision', 'Current', 'Pulse', 'Shadow',
    'Ember', 'Motion', 'Spark', 'Tide', 'Drift',
    'Flare', 'Vortex', 'Realm', 'Journey', 'Moment',
];

const TITLE_VERBS = [
    'Rising', 'Falling', 'Chasing', 'Breaking', 'Flowing',
    'Burning', 'Fading', 'Glowing', 'Shifting', 'Vanishing',
    'Soaring', 'Drifting', 'Turning', 'Spinning', 'Crashing',
];

function randomItem(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

function generateRandomTitle() {
    const patterns = [
        () => `${randomItem(TITLE_ADJECTIVES)} ${randomItem(TITLE_NOUNS)}`,
        () => `${randomItem(TITLE_ADJECTIVES)} ${randomItem(TITLE_NOUNS)} ${randomItem(TITLE_VERBS)}`,
        () => `${randomItem(TITLE_VERBS)} ${randomItem(TITLE_NOUNS)}`,
        () => `${randomItem(TITLE_ADJECTIVES)} ${randomItem(TITLE_VERBS)} ${randomItem(TITLE_NOUNS)}`,
        () => `The ${randomItem(TITLE_ADJECTIVES)} ${randomItem(TITLE_NOUNS)}`,
    ];

    return randomItem(patterns)();
}

function regenerateTitle() {
    videoTitle.value = generateRandomTitle();
}

const uploadUiStatus = computed(() => {
    if (uploadStage.value === 'completed') {
        return 'completed';
    }

    if (uploadStage.value === 'finalizing') {
        return 'processing';
    }

    if (uploadStage.value === 'initializing' || uploadStage.value === 'uploading') {
        return 'uploading';
    }

    return 'idle';
});

const showUploadZone = computed(() => {
    return uploadUiStatus.value === 'idle';
});

const showProgressSection = computed(() => {
    return uploadUiStatus.value !== 'idle';
});

const hasSelectedFile = computed(() => {
    return selectedFileName.value !== 'No file selected';
});

const workflowStepClass = computed(() => {
    return {
        upload: uploadUiStatus.value === 'idle' && hasSelectedFile.value ? 'font-medium text-[#0D9488]' : 'text-gray-400',
        processing: ['uploading', 'processing'].includes(uploadUiStatus.value) ? 'font-medium text-[#0D9488]' : 'text-gray-400',
        ready: uploadUiStatus.value === 'completed' ? 'font-medium text-[#0D9488]' : 'text-gray-400',
    };
});

const progressLabel = computed(() => {
    if (uploadUiStatus.value === 'uploading') {
        return `Uploading... ${uploadProgress.value}%`;
    }

    if (uploadUiStatus.value === 'processing') {
        return 'Processing video...';
    }

    if (uploadUiStatus.value === 'completed') {
        return 'Upload completed';
    }

    return '';
});

const progressMessage = computed(() => {
    if (uploadUiStatus.value === 'uploading') {
        return uploadTransferDetails.value;
    }

    if (uploadUiStatus.value === 'processing') {
        return '';
    }

    if (uploadUiStatus.value === 'completed') {
        return '';
    }

    return '';
});

const progressWidth = computed(() => {
    if (uploadUiStatus.value === 'processing' || uploadUiStatus.value === 'completed') {
        return 100;
    }

    return uploadProgress.value;
});

const isCompleted = computed(() => {
    return uploadUiStatus.value === 'completed';
});

function handleFileChange(event) {
    const input = event.target;

    if (!(input instanceof HTMLInputElement)) {
        setSelectedFile(null);
        return;
    }

    const file = input.files instanceof FileList ? input.files.item(0) : null;

    setSelectedFile(file);
}

function triggerFilePicker() {
    if (isUploading.value) {
        return;
    }

    if (fileInputElement.value instanceof HTMLInputElement) {
        fileInputElement.value.click();
    }
}

function handleDragOver(event) {
    event.preventDefault();

    if (isUploading.value) {
        return;
    }

    isDropzoneDragging.value = true;
}

function handleDragLeave(event) {
    event.preventDefault();
    isDropzoneDragging.value = false;
}

function handleDrop(event) {
    event.preventDefault();
    isDropzoneDragging.value = false;

    if (isUploading.value || !(event.dataTransfer instanceof DataTransfer)) {
        return;
    }

    const droppedFile = event.dataTransfer.files instanceof FileList ? event.dataTransfer.files.item(0) : null;

    setSelectedFile(droppedFile);
}
</script>

<template>
    <Head title="Video Upload" />

    <main class="min-h-screen bg-gray-50">
        <AppHeader :profile-url="props.profileUrl" />

        <section class="mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-2">
                    <h1 class="text-2xl font-semibold text-gray-900">Upload video</h1>
                </div>
                <Link
                    :href="props.videoPlayerUrl"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-[#0D9488] px-[18px] text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59] focus:outline focus:outline-[2px] focus:outline-[rgba(13,148,136,0.35)] focus:outline-offset-2"
                >
                    Back to Player
                </Link>
            </div>

            <div class="mb-4 inline-flex items-center gap-3 rounded-lg border border-gray-200/60 bg-white px-4 py-2.5 text-sm">
                <span :class="workflowStepClass.upload">Upload</span>
                <span class="text-gray-300">→</span>
                <span :class="workflowStepClass.processing">Processing</span>
                <span class="text-gray-300">→</span>
                <span :class="workflowStepClass.ready">Ready</span>
            </div>

            <div class="space-y-4 rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm sm:p-8">
                <div v-if="showUploadZone" class="space-y-2">
                    <label for="video-title" class="block text-sm font-medium text-gray-700">Title</label>
                    <div class="flex gap-2">
                        <input
                            id="video-title"
                            v-model="videoTitle"
                            type="text"
                            maxlength="255"
                            placeholder="Enter a title or generate one"
                            class="flex-1 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400"
                            :disabled="isUploading"
                        >
                        <button
                            type="button"
                            title="Generate random title"
                            class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-500 shadow-sm transition-colors hover:border-gray-400 hover:bg-gray-50 hover:text-gray-700 disabled:cursor-not-allowed disabled:text-gray-300"
                            :disabled="isUploading"
                            @click="regenerateTitle"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" aria-hidden="true">
                                <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                                <path d="M21 3v5h-5" />
                                <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
                                <path d="M8 16H3v5" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div
                    v-if="hasSelectedFile && showUploadZone"
                    class="rounded-xl border border-gray-200 bg-gray-50 p-4"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-gray-900">{{ selectedFileName }}</p>
                            <p class="mt-0.5 text-sm text-gray-500">{{ selectedFileSize }}</p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-sm font-medium text-gray-500 transition-colors hover:text-gray-700 disabled:cursor-not-allowed disabled:text-gray-300"
                            :disabled="isUploading"
                            @click="setSelectedFile(null)"
                        >
                            Remove
                        </button>
                    </div>
                </div>

                <div
                    v-if="showUploadZone"
                    class="relative cursor-pointer rounded-xl border-2 border-dashed p-10 transition-all"
                    :class="isDropzoneDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 bg-gray-50/50 hover:border-gray-400 hover:bg-gray-50'"
                    @click="triggerFilePicker"
                    @dragover="handleDragOver"
                    @dragleave="handleDragLeave"
                    @drop="handleDrop"
                >
                    <input
                        id="video-file"
                        ref="fileInputElement"
                        type="file"
                        accept="video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/mp2t,.ts"
                        class="hidden"
                        :disabled="isUploading"
                        @change="handleFileChange"
                    >

                    <div class="flex flex-col items-center gap-4 text-center">

                        <div
                            class="flex items-center justify-center rounded-full"
                            style="width:64px;height:64px;background:#E6F4F1"
                        >
                            <svg
                                width="28"
                                height="28"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#0D9488"
                                stroke-width="1.75"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <!-- cloud -->
                                <path d="M20 17.5a4.5 4.5 0 0 0-1-8.9 6 6 0 0 0-11.8 1A4 4 0 0 0 6 17.5h14Z" />
                                <!-- arrow -->
                                <path d="M12 16V10" />
                                <path d="m9 13 3-3 3 3" />
                            </svg>
                        </div>

                        <div class="space-y-1.5">
                            <p class="font-medium text-gray-900">Drag & drop your video here</p>
                            <p class="text-gray-500">or click to browse</p>
                        </div>

                        <div class="mt-1 space-y-0.5 text-sm text-gray-500">
                            <p>Max file size: 20 GB</p>
                        </div>
                    </div>
                </div>


                <button
                    v-if="showUploadZone"
                    type="button"
                    class="inline-flex h-[52px] w-full items-center justify-center rounded-[14px] bg-[#0D9488] px-7 text-base font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59] focus:outline focus:outline-[3px] focus:outline-[rgba(13,148,136,0.35)] focus:outline-offset-2 disabled:pointer-events-none disabled:cursor-not-allowed disabled:border disabled:border-[#E2E8F0] disabled:bg-[#F1F5F9] disabled:text-[#94A3B8] disabled:opacity-100"
                    :disabled="!canStartUpload"
                    @click="startUpload"
                >
                    {{ isUploading ? 'Uploading...' : 'Upload video' }}
                </button>

                <div
                    v-if="showProgressSection"
                    :class="isCompleted ? 'space-y-6' : 'space-y-8'"
                >
                    <div
                        v-if="hasSelectedFile"
                        :class="isCompleted ? 'rounded-xl border border-[rgba(13,148,136,0.3)] bg-[rgba(13,148,136,0.05)] p-4' : 'rounded-xl border border-gray-200 bg-gray-50 p-4'"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-900">{{ selectedFileName }}</p>
                                <p class="mt-0.5 text-sm text-gray-500">{{ selectedFileSize }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg
                                        v-if="isCompleted"
                                        class="h-4 w-4 text-[#0D9488]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path d="M20 6 9 17l-5-5" />
                                    </svg>
                                    <span class="font-medium text-gray-900">{{ progressLabel }}</span>
                                </div>
                                <span
                                    v-if="uploadUiStatus === 'uploading'"
                                    class="text-sm text-gray-500"
                                >
                                    {{ uploadProgress }}%
                                </span>
                            </div>
                            <div
                                class="relative h-2.5 w-full overflow-hidden rounded-full"
                                :class="isCompleted ? 'bg-[rgba(13,148,136,0.2)]' : 'bg-gray-200'"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-300"
                                    :class="isCompleted ? 'bg-[#0D9488]' : 'bg-gray-900'"
                                    :style="{ width: `${progressWidth}%` }"
                                />
                            </div>
                        </div>

                        <p class="text-sm leading-relaxed text-gray-600">{{ progressMessage }}</p>
                        <p class="text-xs leading-relaxed text-gray-500">{{ uploadStatus }}</p>
                    </div>

                    <div
                        v-if="uploadStage === 'completed'"
                        class="flex flex-col gap-3 pt-2 sm:flex-row"
                    >
                        <Link
                            :href="props.videoPlayerUrl"
                            class="inline-flex h-12 flex-1 items-center justify-center rounded-lg bg-[#0D9488] px-7 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59] focus:outline focus:outline-[3px] focus:outline-[rgba(13,148,136,0.35)] focus:outline-offset-2"
                        >
                            Open player
                        </Link>

                        <button
                            type="button"
                            class="inline-flex h-12 flex-1 items-center justify-center rounded-md border border-gray-300 bg-white px-5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 hover:text-gray-900"
                            @click="setSelectedFile(null)"
                        >
                            Upload another
                        </button>
                    </div>

                </div>

                <div
                    v-if="uploadError"
                    class="rounded-xl border border-red-200 bg-red-50 p-4"
                >
                    <p class="text-sm text-red-800">{{ uploadError }}</p>
                </div>
            </div>
        </section>
    </main>
</template>
