const READY_STATUS = 'ready';

export function normalizeStatus(status) {
    return typeof status === 'string' ? status.toLowerCase() : '';
}

export function canPlayVideo(video) {
    return normalizeStatus(video?.status) === READY_STATUS;
}

export function formattedVideoStatus(status) {
    const normalized = normalizeStatus(status);

    if (normalized === 'uploading') {
        return 'Uploading';
    }

    if (normalized === 'uploaded') {
        return 'Uploaded';
    }

    if (normalized === 'processing') {
        return 'Processing';
    }

    if (normalized === 'ready') {
        return 'Ready';
    }

    if (normalized === 'failed') {
        return 'Failed';
    }

    return 'Unknown';
}

export function statusBadgeClass(status) {
    const normalized = normalizeStatus(status);

    if (normalized === 'ready') {
        return 'bg-[#E6F4F1] text-[#0D9488]';
    }

    if (normalized === 'uploading' || normalized === 'processing' || normalized === 'uploaded') {
        return 'bg-blue-100 text-blue-800';
    }

    if (normalized === 'failed') {
        return 'bg-red-100 text-red-800';
    }

    return 'bg-gray-100 text-gray-700';
}

export function formatDate(rawDate) {
    const date = new Date(rawDate);

    if (Number.isNaN(date.getTime())) {
        return 'Unknown date';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

export function videoButtonClass({ selected, playing }) {
    const baseClass = 'w-full px-4 py-3 text-left transition focus:outline-none rounded-xl';

    if (playing) {
        return `${baseClass} bg-[#E6F4F1] text-teal-900 ring-1 ring-inset ring-[#CFEAE4]`;
    }

    if (selected) {
        return `${baseClass} bg-gray-100 text-gray-900 ring-1 ring-inset ring-gray-200`;
    }

    return `${baseClass} bg-gray-50 text-gray-900 hover:bg-gray-100`;
}

export function videoStatusBadgeClass({ playing, status }) {
    if (playing) {
        return 'rounded-md bg-[#E6F4F1] px-2 py-0.5 text-xs font-semibold text-[#0D9488]';
    }

    return `rounded-md px-2 py-0.5 text-xs font-semibold ${statusBadgeClass(status)}`;
}

export function videoStatusBadgeLabel({ playing, status }) {
    if (playing) {
        return 'Playing';
    }

    return formattedVideoStatus(status);
}

export function videoUnavailableMessage(status) {
    if (normalizeStatus(status) === 'failed') {
        return 'Playback unavailable: video failed.';
    }

    return '';
}
