export const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024 * 1024;

const VIDEO_EXTENSIONS = new Set(['mp4', 'mov', 'avi', 'mkv', 'ts']);
const VIDEO_EXTENSION_TO_CONTENT_TYPE = {
    mp4: 'video/mp4',
    mov: 'video/quicktime',
    avi: 'video/x-msvideo',
    mkv: 'video/x-matroska',
    ts: 'video/mp2t',
};
const VIDEO_CONTENT_TYPE_ALIASES = new Map([
    ['video/matroska', 'video/x-matroska'],
    ['video/mkv', 'video/x-matroska'],
]);
const SUPPORTED_VIDEO_CONTENT_TYPES = new Set([
    ...Object.values(VIDEO_EXTENSION_TO_CONTENT_TYPE),
    ...VIDEO_CONTENT_TYPE_ALIASES.keys(),
]);

function isFileLike(value) {
    return value !== null
        && typeof value === 'object'
        && typeof value.size === 'number'
        && typeof value.name === 'string';
}

function resolveFileConstructor(options = {}) {
    if (typeof options.FileCtor !== 'undefined') {
        return options.FileCtor;
    }

    if (typeof File !== 'undefined') {
        return File;
    }

    return null;
}

export function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    return `${value.toFixed(value >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

export function formatTransferRate(bytesPerSecond) {
    if (!Number.isFinite(bytesPerSecond) || bytesPerSecond <= 0) {
        return '0 KB/s';
    }

    const kilobytesPerSecond = bytesPerSecond / 1024;

    if (kilobytesPerSecond < 1024) {
        const roundedKilobytesPerSecond = kilobytesPerSecond >= 10
            ? kilobytesPerSecond.toFixed(0)
            : kilobytesPerSecond.toFixed(1);

        return `${roundedKilobytesPerSecond} KB/s`;
    }

    const megabytesPerSecond = kilobytesPerSecond / 1024;
    const roundedMegabytesPerSecond = megabytesPerSecond >= 10
        ? megabytesPerSecond.toFixed(0)
        : megabytesPerSecond.toFixed(1);

    return `${roundedMegabytesPerSecond} MB/s`;
}

export function formatTimeRemaining(totalSeconds) {
    if (!Number.isFinite(totalSeconds) || totalSeconds <= 0) {
        return '0s left';
    }

    const roundedSeconds = Math.ceil(totalSeconds);
    const hours = Math.floor(roundedSeconds / 3600);
    const minutes = Math.floor((roundedSeconds % 3600) / 60);
    const seconds = roundedSeconds % 60;

    if (hours > 0) {
        return `${hours}h ${minutes}m left`;
    }

    if (minutes > 0) {
        return `${minutes}m ${seconds}s left`;
    }

    return `${seconds}s left`;
}

export function guessContentType(file) {
    const extension = file?.name?.split('.').pop()?.toLowerCase();
    if (extension && extension in VIDEO_EXTENSION_TO_CONTENT_TYPE) {
        return VIDEO_EXTENSION_TO_CONTENT_TYPE[extension];
    }

    if (typeof file?.type === 'string') {
        const normalizedContentType = file.type.trim().toLowerCase();

        if (VIDEO_CONTENT_TYPE_ALIASES.has(normalizedContentType)) {
            return VIDEO_CONTENT_TYPE_ALIASES.get(normalizedContentType) ?? 'video/mp4';
        }

        if (SUPPORTED_VIDEO_CONTENT_TYPES.has(normalizedContentType)) {
            return normalizedContentType;
        }
    }

    return 'video/mp4';
}

export function extractApiErrorMessage(payload, fallbackMessage) {
    if (payload && typeof payload.message === 'string' && payload.message !== '') {
        return payload.message;
    }

    return fallbackMessage;
}

export function validateVideoFile(file, options = {}) {
    const fileConstructor = resolveFileConstructor(options);

    if (fileConstructor) {
        if (!(file instanceof fileConstructor)) {
            throw new Error('Choose a file first.');
        }
    } else if (!isFileLike(file)) {
        throw new Error('Choose a file first.');
    }

    if (file.size <= 0) {
        throw new Error('Selected file is empty.');
    }

    const maxFileSizeBytes = typeof options.maxFileSizeBytes === 'number'
        ? options.maxFileSizeBytes
        : MAX_FILE_SIZE_BYTES;

    if (file.size > maxFileSizeBytes) {
        throw new Error('Selected file is larger than 20 GB limit.');
    }

    const extension = file.name.split('.').pop()?.toLowerCase();

    if (extension && VIDEO_EXTENSIONS.has(extension)) {
        return;
    }

    const fileType = typeof file.type === 'string' ? file.type.trim().toLowerCase() : '';

    if (SUPPORTED_VIDEO_CONTENT_TYPES.has(fileType)) {
        return;
    }

    throw new Error('Only MP4, MOV, AVI, MKV, and TS files are allowed.');
}
