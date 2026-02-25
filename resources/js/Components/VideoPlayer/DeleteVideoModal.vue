<script setup>
import { ref } from 'vue';
import { useDialogFocusTrap } from '../../Composables/player/useDialogFocusTrap';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    videoTitle: {
        type: String,
        required: true,
    },
    isDeleting: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['cancel', 'confirm']);
const dialogElement = ref(null);

function cancel() {
    emit('cancel');
}

function confirm() {
    emit('confirm');
}

function canCloseDialog() {
    return !props.isDeleting;
}

useDialogFocusTrap({
    isOpen() {
        return props.isOpen;
    },
    dialogElement,
    canClose: canCloseDialog,
    onClose: cancel,
    onOpen() {
        requestAnimationFrame(() => {
            if (dialogElement.value instanceof HTMLElement) {
                dialogElement.value.focus();
            }
        });
    },
    onClosed() {
        return null;
    },
});

function handleOverlayClick(event) {
    if (!canCloseDialog()) {
        return;
    }

    if (event.target === event.currentTarget) {
        cancel();
    }
}
</script>

<template>
    <Transition name="fade">
        <div
            v-if="props.isOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 backdrop-blur-[1px]"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-video-title"
            @click="handleOverlayClick"
        >
            <div
                ref="dialogElement"
                class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6 focus:outline-none"
                tabindex="-1"
            >
                <h2 id="delete-video-title" class="text-lg font-semibold text-slate-900">Delete video?</h2>
                <p class="mt-2 text-sm text-slate-600">
                    You are about to delete
                    <span class="font-medium text-slate-900">"{{ props.videoTitle }}"</span>.
                    This action cannot be undone.
                </p>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 active:bg-slate-100 disabled:cursor-not-allowed disabled:text-slate-400"
                        :disabled="props.isDeleting"
                        @click="cancel"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700 active:bg-red-800 disabled:cursor-not-allowed disabled:bg-red-300"
                        :disabled="props.isDeleting"
                        @click="confirm"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 6H5H21"></path>
                            <path d="M8 6V4C8 3.44772 8.44772 3 9 3H15C15.5523 3 16 3.44772 16 4V6"></path>
                            <path d="M19 6L18.1 19.5C18.0622 20.0677 17.5908 20.5 17.022 20.5H6.978C6.40919 20.5 5.93779 20.0677 5.9 19.5L5 6"></path>
                            <path d="M10 10.5V16.5"></path>
                            <path d="M14 10.5V16.5"></path>
                        </svg>
                        {{ props.isDeleting ? 'Deleting...' : 'Delete video' }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
