import { onBeforeUnmount, ref, watch } from 'vue';

const FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function useDialogFocusTrap({
    isOpen,
    dialogElement,
    canClose,
    onClose,
    onOpen,
    onClosed,
}) {
    const lastFocusedElement = ref(null);

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
        const lastFocusableNode = focusableElements[focusableElements.length - 1];
        const activeElement = document.activeElement;

        if (event.shiftKey && activeElement === firstFocusableElement) {
            event.preventDefault();
            lastFocusableNode.focus();
            return;
        }

        if (!event.shiftKey && activeElement === lastFocusableNode) {
            event.preventDefault();
            firstFocusableElement.focus();
        }
    }

    function handleEscape() {
        if (canClose()) {
            onClose();
        }
    }

    function handleDialogKeyDown(event) {
        if (!isOpen()) {
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
        isOpen,
        (open, wasOpen) => {
            if (open && !wasOpen && document.activeElement instanceof HTMLElement) {
                lastFocusedElement.value = document.activeElement;
            }

            if (open) {
                window.addEventListener('keydown', handleDialogKeyDown);
                onOpen();
                return;
            }

            if (wasOpen) {
                window.removeEventListener('keydown', handleDialogKeyDown);

                if (lastFocusedElement.value instanceof HTMLElement) {
                    lastFocusedElement.value.focus();
                }

                lastFocusedElement.value = null;
                onClosed();
            }
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', handleDialogKeyDown);
    });
}
