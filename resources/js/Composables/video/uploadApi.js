import { extractApiErrorMessage, guessContentType } from './uploadFileUtils';

const FORBIDDEN_UPLOAD_HEADERS = new Set(['host', 'content-length']);

export async function initializeVideoUpload({
    fetchWithAuthorization,
    apiBase,
    file,
    title,
}) {
    const response = await fetchWithAuthorization(`${apiBase}/api/v1/videos/uploads`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            file_name: file.name,
            file_size: file.size,
            content_type: guessContentType(file),
            title: title && title.trim() !== '' ? title.trim() : null,
        }),
    });

    const payload = await response.json().catch(() => {
        return null;
    });

    if (response.status === 401) {
        throw new Error('Unauthorized. API token is expired/invalid and could not be refreshed.');
    }

    if (!response.ok) {
        throw new Error(extractApiErrorMessage(payload, `Unable to initialize upload (${response.status}).`));
    }

    const videoId = payload?.data?.video?.id;
    const uploadData = payload?.data?.upload;

    if (typeof videoId !== 'string' || videoId === '') {
        throw new Error('Upload initialized, but video id is missing in API response.');
    }

    if (!uploadData || typeof uploadData !== 'object') {
        throw new Error('Upload initialized, but signed upload payload is missing.');
    }

    const uploadUrl = typeof uploadData.url === 'string' ? uploadData.url : '';

    if (uploadUrl === '') {
        throw new Error('Upload URL is missing from API response.');
    }

    const uploadMethod = typeof uploadData.method === 'string' && uploadData.method !== ''
        ? uploadData.method.toUpperCase()
        : 'PUT';
    const uploadHeaders = uploadData.headers && typeof uploadData.headers === 'object'
        ? uploadData.headers
        : {};

    return {
        videoId,
        uploadUrl,
        uploadMethod,
        uploadHeaders,
    };
}

function applySignedUploadHeaders(xhr, headers, file) {
    let hasContentTypeHeader = false;

    for (const [headerName, rawHeaderValue] of Object.entries(headers)) {
        if (typeof headerName !== 'string' || headerName.trim() === '') {
            continue;
        }

        const normalizedHeader = headerName.trim();
        const lowerHeader = normalizedHeader.toLowerCase();

        if (FORBIDDEN_UPLOAD_HEADERS.has(lowerHeader)) {
            continue;
        }

        if (lowerHeader === 'content-type') {
            hasContentTypeHeader = true;
        }

        const headerValue = Array.isArray(rawHeaderValue)
            ? rawHeaderValue.join(', ')
            : String(rawHeaderValue);

        xhr.setRequestHeader(normalizedHeader, headerValue);
    }

    if (!hasContentTypeHeader) {
        xhr.setRequestHeader('Content-Type', guessContentType(file));
    }
}

export async function uploadFileToSignedUrl({ file, uploadPayload, onProgress }) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();

        xhr.open(uploadPayload.uploadMethod, uploadPayload.uploadUrl, true);
        applySignedUploadHeaders(xhr, uploadPayload.uploadHeaders, file);

        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable || event.total <= 0) {
                return;
            }

            const progress = Math.min(99, Math.round((event.loaded / event.total) * 100));
            onProgress(progress);
        });

        xhr.addEventListener('error', () => {
            reject(new Error('Network error while uploading file to storage.'));
        });

        xhr.addEventListener('abort', () => {
            reject(new Error('Upload was aborted.'));
        });

        xhr.addEventListener('load', () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                const etagHeader = xhr.getResponseHeader('ETag') ?? xhr.getResponseHeader('etag') ?? '';
                const normalizedEtag = etagHeader.replaceAll('"', '').trim();
                resolve(normalizedEtag);
                return;
            }

            const responseBody = typeof xhr.responseText === 'string' ? xhr.responseText.trim() : '';
            const shortError = responseBody !== '' ? responseBody.slice(0, 180) : '';
            reject(new Error(shortError !== '' ? `S3 upload failed (${xhr.status}): ${shortError}` : `S3 upload failed (${xhr.status}).`));
        });

        xhr.send(file);
    });
}

export async function completeVideoUpload({
    fetchWithAuthorization,
    apiBase,
    videoId,
    fileSize,
    etag,
}) {
    const response = await fetchWithAuthorization(`${apiBase}/api/v1/videos/${encodeURIComponent(videoId)}/uploads/complete`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            uploaded_size: fileSize,
            etag,
        }),
    });

    const payload = await response.json().catch(() => {
        return null;
    });

    if (response.status === 401) {
        throw new Error('Unauthorized. API token is expired/invalid and could not be refreshed.');
    }

    if (!response.ok) {
        throw new Error(extractApiErrorMessage(payload, `Unable to finalize upload (${response.status}).`));
    }

    return payload;
}
