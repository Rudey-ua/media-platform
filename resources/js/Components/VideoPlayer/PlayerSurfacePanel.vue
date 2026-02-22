<script setup>
import ApiLoadingState from '../ApiLoadingState.vue';

defineProps({
    surfaceMode: {
        type: String,
        required: true,
    },
    surfaceTitle: {
        type: String,
        required: true,
    },
    surfaceDescription: {
        type: String,
        required: true,
    },
    surfaceBorderClass: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['video-element']);

function assignVideoElement(element) {
    emit('video-element', element instanceof HTMLVideoElement ? element : null);
}
</script>

<template>
    <section
        class="relative overflow-hidden rounded-2xl border bg-white shadow-sm"
        :class="surfaceBorderClass"
    >
        <div
            v-if="surfaceMode === 'idle' || surfaceMode === 'message'"
            class="flex min-h-[420px] flex-col items-center justify-center px-6 text-center"
        >
            <div class="flex h-14 w-14 items-center justify-center rounded-full border border-slate-200 bg-slate-50">
                <svg
                    class="h-7 w-7 text-teal-600"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                    <path d="M10 9.5L15.5 12L10 14.5V9.5Z"></path>
                </svg>
            </div>
            <h2 class="mt-4 text-lg font-semibold text-slate-900" v-text="surfaceTitle"></h2>
            <p class="mt-2 max-w-md text-sm text-slate-600" v-text="surfaceDescription"></p>
        </div>

        <div
            v-if="surfaceMode === 'loading'"
            class="flex min-h-[420px] flex-col items-center justify-center px-6 text-center"
        >
            <ApiLoadingState message="" container-class="flex flex-col items-center justify-center space-y-3" />
            <p class="text-base font-semibold text-slate-900" v-text="surfaceTitle"></p>
            <p class="max-w-md text-sm text-slate-600" v-text="surfaceDescription"></p>
        </div>

        <div v-show="surfaceMode === 'playing'">
            <video
                :ref="assignVideoElement"
                controls
                playsinline
                preload="metadata"
                class="h-full max-h-[70vh] min-h-[420px] w-full bg-black"
            ></video>
        </div>
    </section>
</template>
