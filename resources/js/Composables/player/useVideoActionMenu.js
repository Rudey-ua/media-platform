import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const ACTION_MENU_ESTIMATED_HEIGHT_PX = 96;

export function useVideoActionMenu({
    videoItems,
    rootElement,
    scrollContainerElement,
}) {
    const actionMenuVideoId = ref(null);
    const actionMenuPlacement = ref('down');

    watch(
        () => videoItems().map((video) => String(video.id)),
        (videoIds) => {
            if (actionMenuVideoId.value !== null && !videoIds.includes(actionMenuVideoId.value)) {
                actionMenuVideoId.value = null;
            }
        },
        { immediate: true },
    );

    function closeActionMenu() {
        actionMenuVideoId.value = null;
    }

    function resolveActionMenuPlacement(triggerElement) {
        if (!(triggerElement instanceof HTMLElement)) {
            return 'down';
        }

        if (!(scrollContainerElement.value instanceof HTMLElement)) {
            return 'down';
        }

        const triggerRect = triggerElement.getBoundingClientRect();
        const containerRect = scrollContainerElement.value.getBoundingClientRect();
        const availableSpaceBelow = containerRect.bottom - triggerRect.bottom;
        const availableSpaceAbove = triggerRect.top - containerRect.top;

        if (availableSpaceBelow < ACTION_MENU_ESTIMATED_HEIGHT_PX && availableSpaceAbove > availableSpaceBelow) {
            return 'up';
        }

        return 'down';
    }

    function toggleActionMenu(videoId, triggerElement) {
        if (typeof videoId !== 'string' || videoId === '') {
            return;
        }

        if (actionMenuVideoId.value === videoId) {
            closeActionMenu();
            return;
        }

        actionMenuPlacement.value = resolveActionMenuPlacement(triggerElement);
        actionMenuVideoId.value = videoId;
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

    return {
        actionMenuVideoId,
        actionMenuPlacement,
        closeActionMenu,
        toggleActionMenu,
    };
}
