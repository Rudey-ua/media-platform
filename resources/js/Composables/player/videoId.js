export function normalizeVideoId(rawValue) {
    if (typeof rawValue === 'string') {
        const trimmedValue = rawValue.trim();

        return trimmedValue !== '' ? trimmedValue : null;
    }

    if (typeof rawValue === 'number' && Number.isFinite(rawValue)) {
        return String(rawValue);
    }

    return null;
}
