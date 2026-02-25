<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    initialTitle: {
        type: String,
        required: true,
    },
    errorMessage: {
        type: String,
        default: '',
    },
    isRenaming: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['cancel', 'confirm']);

const titleDraft = ref('');
const inputElement = ref(null);
const dialogElement = ref(null);
const lastFocusedElement = ref(null);
const FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

const canConfirm = computed(() => {
    if (props.isRenaming) {
        return false;
    }

    return titleDraft.value.trim() !== props.initialTitle.trim();
});

function trapTabFocus(event) {
    if (!(dialogElement.value instanceof HTMLElement)) {
        return;
    }

    const focusableElements = Array.from(dialogElement.value.querySelectorAll(FOCUSABLE_SELECTOR))
        .filter((element) => element instanceof HTMLElement);

    if (focusableElements.length === 0) {
        event.preventDefault();
        dialogElement.value.focus();
        return;
    }

    const firstFocusableElement = focusableElements[0];
    const lastFocusableElement = focusableElements[focusableElements.length - 1];
    const activeElement = document.activeElement;

    if (event.shiftKey && activeElement === firstFocusableElement) {
        event.preventDefault();
        lastFocusableElement.focus();
        return;
    }

    if (!event.shiftKey && activeElement === lastFocusableElement) {
        event.preventDefault();
        firstFocusableElement.focus();
    }
}

function handleDialogKeyDown(event) {
    if (!props.isOpen) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        handleEscape();
        return;
    }

    if (event.key === 'Tab') {
        trapTabFocus(event);
    }
}

watch(
    () => [props.isOpen, props.initialTitle],
    async ([isOpen], [wasOpen] = []) => {
        if (isOpen && !wasOpen && document.activeElement instanceof HTMLElement) {
            lastFocusedElement.value = document.activeElement;
        }

        if (!isOpen && wasOpen) {
            window.removeEventListener('keydown', handleDialogKeyDown);

            if (lastFocusedElement.value instanceof HTMLElement) {
                lastFocusedElement.value.focus();
            }

            lastFocusedElement.value = null;
            return;
        }

        if (!isOpen) {
            return;
        }

        window.addEventListener('keydown', handleDialogKeyDown);
        titleDraft.value = props.initialTitle;
        await nextTick();

        if (inputElement.value instanceof HTMLInputElement) {
            inputElement.value.focus();
            inputElement.value.select();
            return;
        }
        if (dialogElement.value instanceof HTMLElement) {
            dialogElement.value.focus();
        }
    },
    { immediate: true },
);

function cancel() {
    emit('cancel');
}

function confirm() {
    if (!canConfirm.value) {
        return;
    }

    emit('confirm', titleDraft.value);
}

function handleOverlayClick(event) {
    if (props.isRenaming) {
        return;
    }

    if (event.target === event.currentTarget) {
        cancel();
    }
}

function handleEscape() {
    if (!props.isRenaming) {
        cancel();
    }
}

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleDialogKeyDown);
});
</script>

<template>
    <Transition name="fade">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 backdrop-blur-[1px]"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rename-video-title"
            @click="handleOverlayClick"
        >
            <div
                ref="dialogElement"
                class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6 focus:outline-none"
                tabindex="-1"
            >
                <h2 id="rename-video-title" class="text-lg font-semibold text-slate-900">Rename video</h2>
                <p class="mt-2 text-sm text-slate-600">Update the title shown in your video list.</p>

                <div class="mt-4 space-y-2">
                    <label for="rename-video-input" class="block text-sm font-medium text-slate-700">Title</label>
                    <input
                        id="rename-video-input"
                        ref="inputElement"
                        v-model="titleDraft"
                        type="text"
                        maxlength="255"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
                        placeholder="Untitled video"
                        :disabled="isRenaming"
                        @keydown.enter.prevent="confirm"
                    >
                    <p class="text-xs text-slate-500">Leave empty to set it as Untitled video.</p>
                </div>

                <div
                    v-if="errorMessage !== ''"
                    class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ errorMessage }}
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 active:bg-slate-100 disabled:cursor-not-allowed disabled:text-slate-400"
                        :disabled="isRenaming"
                        @click="cancel"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59] disabled:cursor-not-allowed disabled:bg-slate-300"
                        :disabled="!canConfirm"
                        @click="confirm"
                    >
                        {{ isRenaming ? 'Saving...' : 'Save title' }}
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
