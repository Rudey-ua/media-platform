<script setup>
const props = defineProps({
    video: {
        type: Object,
        required: true,
    },
    isActionMenuOpen: {
        type: Boolean,
        default: false,
    },
    actionMenuPlacement: {
        type: String,
        default: 'down',
    },
    canManageVideos: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'select-video',
    'rename-video',
    'delete-video',
    'toggle-action-menu',
]);

function hasActions() {
    return props.canManageVideos && (props.video.canRename || props.video.canDelete);
}

function canRenameAction() {
    return props.video.canRename && !props.video.isBusy;
}

function canDeleteAction() {
    return props.video.canDelete && !props.video.isBusy;
}

function handleVideoCardClick() {
    if (!props.video.canPlay || props.video.isBusy) {
        return;
    }

    emit('select-video', props.video.id);
}

function handleRenameAction() {
    if (!canRenameAction()) {
        return;
    }

    emit('rename-video', props.video.id);
}

function handleDeleteAction() {
    if (!canDeleteAction()) {
        return;
    }

    emit('delete-video', props.video.id);
}

function handleToggleActionMenu(event) {
    emit('toggle-action-menu', props.video.id, event);
}
</script>

<template>
    <li class="mb-1 last:mb-0">
        <div class="relative overflow-visible rounded-xl">
            <button
                type="button"
                :aria-label="`Video ${props.video.title || props.video.id}`"
                :disabled="!props.video.canPlay || props.video.isBusy"
                :class="[
                    props.video.buttonClass,
                    hasActions() ? 'pr-12' : '',
                ]"
                @click="handleVideoCardClick"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="flex flex-col items-start gap-1">
                        <span class="font-medium">{{ props.video.title }}</span>
                        <span class="text-xs text-gray-500" v-text="props.video.createdAtLabel"></span>
                    </div>
                    <span :class="props.video.statusBadgeClass" v-text="props.video.statusBadgeLabel"></span>
                </div>

                <p
                    v-if="!props.video.canPlay && props.video.unavailableMessage !== ''"
                    class="mt-2 text-xs text-gray-500"
                    v-text="props.video.unavailableMessage"
                ></p>
            </button>

            <div v-if="hasActions()" class="absolute right-2 top-1/2 z-10 -translate-y-1/2">
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 transition-colors hover:bg-gray-200/70 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0D9488]/50 disabled:cursor-not-allowed disabled:text-gray-300"
                    :aria-expanded="props.isActionMenuOpen"
                    aria-haspopup="menu"
                    aria-label="Video actions"
                    :disabled="props.video.isBusy"
                    @click.stop="handleToggleActionMenu"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <circle cx="6" cy="12" r="1.5" />
                        <circle cx="12" cy="12" r="1.5" />
                        <circle cx="18" cy="12" r="1.5" />
                    </svg>
                </button>

                <Transition name="menu-fade">
                    <div
                        v-if="props.isActionMenuOpen"
                        :class="[
                            'absolute right-0 w-40 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg ring-1 ring-black/5',
                            props.actionMenuPlacement === 'up'
                                ? 'bottom-full mb-1 origin-bottom-right'
                                : 'top-full mt-1 origin-top-right',
                        ]"
                        role="menu"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-start px-3 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:text-gray-400"
                            :disabled="!canRenameAction()"
                            role="menuitem"
                            @click.stop="handleRenameAction"
                        >
                            Rename
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center justify-start px-3 py-2 text-sm text-red-600 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:text-red-300"
                            :disabled="!canDeleteAction()"
                            role="menuitem"
                            @click.stop="handleDeleteAction"
                        >
                            Delete
                        </button>
                    </div>
                </Transition>
            </div>
        </div>
    </li>
</template>

<style scoped>
.menu-fade-enter-active,
.menu-fade-leave-active {
  transition: opacity 0.15s ease;
}

.menu-fade-enter-from,
.menu-fade-leave-to {
  opacity: 0;
}
</style>
