import { computed, onMounted, ref } from 'vue';
import { useApiAuth } from './auth/useApiAuth';
import { formatBytes, validateVideoFile } from './video/uploadFileUtils';
import {
    completeVideoUpload,
    initializeVideoUpload,
    uploadFileToSignedUrl,
} from './video/uploadApi';

const API_BASE = window.location.origin;

export function useVideoUpload() {
    const {
        authStatus,
        hasAccessToken,
        tokenSource,
        fetchWithAuthorization,
        bootstrapAuth,
    } = useApiAuth();

    const selectedFile = ref(null);
    const videoTitle = ref('');
    const uploadStage = ref('idle');
    const uploadStatus = ref('Select a video file to upload.');
    const uploadError = ref('');
    const uploadProgress = ref(0);
    const uploadedVideoId = ref(null);

    const isUploading = computed(() => {
        return ['initializing', 'uploading', 'finalizing'].includes(uploadStage.value);
    });

    const selectedFileName = computed(() => {
        if (!(selectedFile.value instanceof File)) {
            return 'No file selected';
        }

        return selectedFile.value.name;
    });

    const selectedFileSize = computed(() => {
        if (!(selectedFile.value instanceof File)) {
            return '—';
        }

        return formatBytes(selectedFile.value.size);
    });

    const canStartUpload = computed(() => {
        return hasAccessToken.value && selectedFile.value instanceof File && !isUploading.value;
    });

    function setSelectedFile(file) {
        uploadError.value = '';
        uploadProgress.value = 0;
        uploadedVideoId.value = null;

        if (!(file instanceof File)) {
            selectedFile.value = null;
            uploadStage.value = 'idle';
            uploadStatus.value = 'Select a video file to upload.';
            return;
        }

        try {
            validateVideoFile(file);
        } catch (error) {
            selectedFile.value = null;
            uploadStage.value = 'failed';
            uploadStatus.value = 'File validation failed.';
            uploadError.value = error instanceof Error ? error.message : 'Invalid file selected.';
            return;
        }

        selectedFile.value = file;
        uploadStage.value = 'idle';
        uploadStatus.value = 'Ready to upload.';
    }

    async function startUpload() {
        const file = selectedFile.value;

        if (!(file instanceof File)) {
            uploadError.value = 'Choose a file first.';
            uploadStage.value = 'failed';
            return;
        }

        if (!hasAccessToken.value) {
            uploadError.value = 'No API token found.';
            uploadStage.value = 'failed';
            return;
        }

        uploadStage.value = 'initializing';
        uploadStatus.value = 'Requesting signed upload URL from Laravel...';
        uploadError.value = '';
        uploadProgress.value = 0;
        uploadedVideoId.value = null;

        try {
            validateVideoFile(file);
            const uploadPayload = await initializeVideoUpload({
                fetchWithAuthorization,
                apiBase: API_BASE,
                file,
                title: videoTitle.value,
            });

            uploadStage.value = 'uploading';
            uploadStatus.value = '';

            const etag = await uploadFileToSignedUrl({
                file,
                uploadPayload,
                onProgress(progress) {
                    uploadProgress.value = progress;
                },
            });

            uploadStage.value = 'finalizing';
            uploadStatus.value = 'Finalizing upload and dispatching encoding...';
            uploadProgress.value = 100;

            await completeVideoUpload({
                fetchWithAuthorization,
                apiBase: API_BASE,
                videoId: uploadPayload.videoId,
                fileSize: file.size,
                etag,
            });

            uploadStage.value = 'completed';
            uploadStatus.value = '';
            uploadedVideoId.value = uploadPayload.videoId;
            uploadError.value = '';
        } catch (error) {
            uploadStage.value = 'failed';
            uploadStatus.value = 'Upload failed.';
            uploadError.value = error instanceof Error ? error.message : 'Unexpected upload error.';
        }
    }

    async function bootstrap() {
        await bootstrapAuth();
    }

    onMounted(() => {
        bootstrap();
    });

    return {
        authStatus,
        hasAccessToken,
        tokenSource,
        selectedFileName,
        selectedFileSize,
        canStartUpload,
        isUploading,
        uploadStage,
        uploadStatus,
        uploadError,
        uploadProgress,
        uploadedVideoId,
        videoTitle,
        setSelectedFile,
        startUpload,
    };
}
