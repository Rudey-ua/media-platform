export function safeWindow() {
    if (typeof window === 'undefined') {
        return null;
    }

    return window;
}
