export const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024 * 1024;

const VIDEO_EXTENSIONS = new Set(['mp4', 'mov', 'avi', 'mkv', 'ts']);

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

export function guessContentType(file) {
    if (typeof file?.type === 'string' && file.type.startsWith('video/')) {
        return file.type;
    }

    const extension = file?.name?.split('.').pop()?.toLowerCase();

    return {
        mp4: 'video/mp4',
        mov: 'video/quicktime',
        avi: 'video/x-msvideo',
        mkv: 'video/x-matroska',
        ts: 'video/mp2t',
    }[extension] ?? 'video/mp4';
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

    const fileType = typeof file.type === 'string' ? file.type.trim() : '';

    if (fileType.startsWith('video/')) {
        return;
    }

    const extension = file.name.split('.').pop()?.toLowerCase();

    if (extension && VIDEO_EXTENSIONS.has(extension)) {
        return;
    }

    throw new Error('Only video files are allowed.');
}
