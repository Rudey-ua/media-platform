<?php

namespace App\Services;

use App\Enums\VideoStatus;
use App\Exceptions\ApiException;
use App\Jobs\PublishVideoMessage;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

readonly class VideoService
{
    private const int TEMPORARY_UPLOAD_URL_TTL_MINUTES = 20;

    private const int SOURCE_DOWNLOAD_URL_TTL_MINUTES = 180;

    public function __construct(private PlaybackService $playbackService) {}

    /**
     * @return array{video: Video, upload: array{url: string, headers: array<string, string>, method: string, expires_at: string}}
     */
    public function initializeDirectUploadForUser(User $user, string $fileName, int $fileSize, string $contentType, ?string $title = null): array
    {
        if ($fileSize < 1) {
            throw new ApiException(
                message: 'Invalid file size.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $video = Video::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'original_path' => '',
            'status' => VideoStatus::Uploading,
        ]);

        $relativePath = $this->buildDirectUploadPath(
            videoIdentifier: $this->videoIdentifier($video),
            fileName: $fileName,
            contentType: $contentType,
        );

        $video->update([
            'original_path' => $this->qualifyStoragePath(relativePath: $relativePath),
        ]);

        $expiresAt = now()->addMinutes(self::TEMPORARY_UPLOAD_URL_TTL_MINUTES);
        $disk = $this->storageDisk();

        try {
            ['url' => $url, 'headers' => $headers] = Storage::disk($disk)->temporaryUploadUrl(
                $relativePath,
                $expiresAt,
                [
                    'ContentType' => $contentType,
                ],
            );
        } catch (Throwable $throwable) {
            Log::error('Unable to generate temporary upload URL', [
                'video_id' => $this->videoIdentifier($video),
                'user_id' => $user->id,
                'file_size' => $fileSize,
                'message' => $throwable->getMessage(),
                'exception_class' => $throwable::class,
            ]);

            $video->update([
                'status' => VideoStatus::Failed,
                'error_message' => 'Unable to generate upload URL.',
            ]);

            throw new ApiException(
                message: 'Unable to initialize upload',
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        if (! is_string($url) || $url === '') {
            $video->update([
                'status' => VideoStatus::Failed,
                'error_message' => 'Upload URL is empty.',
            ]);

            throw new ApiException(
                message: 'Unable to initialize upload',
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return [
            'video' => $video->fresh() ?? $video,
            'upload' => [
                'url' => $url,
                'headers' => $this->normalizeUploadHeaders($headers),
                'method' => 'PUT',
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ];
    }

    public function completeDirectUploadForUser(User $user, string $videoId, ?int $expectedFileSize = null): Video
    {
        $video = $this->findForUser(user: $user, videoId: $videoId);

        if (! in_array($video->status, [VideoStatus::Uploading, VideoStatus::Uploaded], true)) {
            throw new ApiException(
                message: 'Video upload has already been finalized.',
                status: Response::HTTP_CONFLICT,
            );
        }

        $disk = $this->storageDisk();
        $relativePath = $this->resolveRelativeStoragePath(
            storedPath: $video->original_path,
            disk: $disk,
        );

        if ($relativePath === '' || ! Storage::disk($disk)->exists($relativePath)) {
            throw new ApiException(
                message: 'Uploaded file was not found in storage.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if (is_int($expectedFileSize) && $expectedFileSize > 0) {
            $actualFileSize = Storage::disk($disk)->size($relativePath);

            if (! is_int($actualFileSize) || $actualFileSize !== $expectedFileSize) {
                throw new ApiException(
                    message: 'Uploaded file size does not match expected size.',
                    status: Response::HTTP_CONFLICT,
                );
            }
        }

        $video->update([
            'status' => VideoStatus::Uploaded,
            'error_message' => null,
        ]);

        return $this->dispatchVideoForEncoding($video->fresh() ?? $video);
    }

    public function uploadForUser(User $user, UploadedFile $videoFile): Video
    {
        $relativePath = $this->storeOriginalVideoFile(videoFile: $videoFile);

        $video = Video::query()->create([
            'user_id' => $user->id,
            'original_path' => $this->qualifyStoragePath(relativePath: $relativePath),
            'status' => VideoStatus::Uploaded,
        ]);

        return $this->dispatchVideoForEncoding($video);
    }

    public function listForUser(User $user): Collection
    {
        return $user->videos()->latest()->get();
    }

    public function findForUser(User $user, string $videoId): Video
    {
        $video = $user->videos()->where('uuid', $videoId)->first();

        if (! $video instanceof Video) {
            throw new ApiException(
                message: 'Video not found',
                status: Response::HTTP_NOT_FOUND,
            );
        }

        return $video;
    }

    public function getPlaybackPlaylistForUser(User $user, string $videoId): string
    {
        $video = $this->findForUser(user: $user, videoId: $videoId);

        if ($video->status !== VideoStatus::Ready) {
            throw new ApiException(
                message: 'Video is not ready for playback',
                status: Response::HTTP_CONFLICT,
            );
        }
        if (! is_string($video->hls_path) || $video->hls_path === '') {
            throw new ApiException(
                message: 'Playback is unavailable',
                status: Response::HTTP_CONFLICT,
            );
        }

        return $this->playbackService->renderSignedPlaylist($video->hls_path);
    }

    public function handleEncodingWebhook(array $payload): Video
    {
        $videoIdentifier = (string) ($payload['video_id'] ?? '');

        $video = Video::query()
            ->where('uuid', $videoIdentifier)
            ->first();

        if (! $video instanceof Video) {
            throw new ApiException(
                message: 'Video not found',
                status: Response::HTTP_NOT_FOUND,
            );
        }

        return match ($payload['event']) {
            'video.encoding.completed' => $this->markAsReady(video: $video, payload: $payload),
            'video.encoding.failed' => $this->markAsFailed(video: $video, payload: $payload),
            default => $video,
        };
    }

    private function markAsReady(Video $video, array $payload): Video
    {
        $rawPlaylistPath = (string) ($payload['hls']['playlist'] ?? '');
        $normalizedPlaylistPath = $this->playbackService->normalizeStoredPlaylistPath($rawPlaylistPath);

        $video->update([
            'status' => VideoStatus::Ready,
            'hls_path' => $normalizedPlaylistPath !== '' ? $normalizedPlaylistPath : $rawPlaylistPath,
            'error_message' => null,
        ]);

        return $video->fresh() ?? $video;
    }

    private function markAsFailed(Video $video, array $payload): Video
    {
        $video->update([
            'status' => VideoStatus::Failed,
            'hls_path' => null,
            'error_message' => (string) ($payload['error']['message'] ?? 'Encoding failed.'),
        ]);

        return $video->fresh() ?? $video;
    }

    private function dispatchVideoForEncoding(Video $video): Video
    {
        try {
            PublishVideoMessage::dispatch($this->buildEncodingPayload($video));

            $video->update([
                'status' => VideoStatus::Processing,
            ]);
        } catch (Throwable $throwable) {
            Log::error('Unable to dispatch video encoding job', [
                'video_id' => $this->videoIdentifier($video),
                'message' => $throwable->getMessage(),
                'exception_class' => $throwable::class,
            ]);

            $video->update([
                'status' => VideoStatus::Failed,
                'error_message' => 'Unable to dispatch video for encoding.',
            ]);
        }

        return $video->fresh() ?? $video;
    }

    private function storeOriginalVideoFile(UploadedFile $videoFile): string
    {
        $basePath = trim((string) config('filesystems.paths.source_videos', 'source_videos'), '/');
        $disk = $this->storageDisk();
        $relativePath = Storage::disk($disk)->putFile($basePath, $videoFile);

        if (! is_string($relativePath) || $relativePath === '') {
            throw new ApiException(
                message: 'Unable to upload video',
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $relativePath;
    }

    private function qualifyStoragePath(string $relativePath): string
    {
        $disk = $this->storageDisk();

        if ($disk !== 's3') {
            return $relativePath;
        }
        $bucket = trim((string) config('filesystems.disks.s3.bucket', ''));

        if ($bucket === '') {
            return $relativePath;
        }
        $root = trim((string) config('filesystems.disks.s3.root', ''), '/');
        $normalizedRelativePath = trim($relativePath, '/');
        $fullPath = $root !== '' ? $root.'/'.$normalizedRelativePath : $normalizedRelativePath;

        return 's3://'.$bucket.'/'.$fullPath;
    }

    /**
     * @return array<string, string>
     */
    private function buildEncodingPayload(Video $video): array
    {
        $videoId = $this->videoIdentifier($video);
        $sourceUrl = $this->buildSourceVideoUrl($video->original_path);

        return [
            'video_id' => $videoId,
            'source' => $sourceUrl,
        ];
    }

    private function buildSourceVideoUrl(string $storedPath): string
    {
        $disk = $this->storageDisk();
        $relativePath = $this->resolveRelativeStoragePath(
            storedPath: $storedPath,
            disk: $disk,
        );

        if ($relativePath !== '') {
            try {
                return Storage::disk($disk)->temporaryUrl(
                    $relativePath,
                    now()->addMinutes(self::SOURCE_DOWNLOAD_URL_TTL_MINUTES),
                );
            } catch (Throwable $throwable) {
                Log::warning('Unable to generate temporary download URL for source video. Falling back to regular URL.', [
                    'path' => $relativePath,
                    'disk' => $disk,
                    'message' => $throwable->getMessage(),
                    'exception_class' => $throwable::class,
                ]);
            }
        }

        $storageUrl = Storage::disk($disk)->url($relativePath);

        if (str_starts_with($storageUrl, 'http://') || str_starts_with($storageUrl, 'https://')) {
            return $storageUrl;
        }

        return url($storageUrl);
    }

    private function resolveRelativeStoragePath(string $storedPath, string $disk): string
    {
        $normalizedPath = ltrim($storedPath, '/');

        if ($disk !== 's3') {
            return $normalizedPath;
        }
        if (Str::startsWith($normalizedPath, ['http://', 'https://'])) {
            $path = parse_url($normalizedPath, PHP_URL_PATH);
            $normalizedPath = is_string($path) ? ltrim($path, '/') : $normalizedPath;
        }
        if (str_starts_with($normalizedPath, 's3://')) {
            $pathWithoutScheme = substr($normalizedPath, 5);
            $firstSlashPosition = strpos($pathWithoutScheme, '/');

            if (! is_int($firstSlashPosition)) {
                return $normalizedPath;
            }
            $normalizedPath = substr($pathWithoutScheme, $firstSlashPosition + 1);
        }
        $bucket = trim((string) config('filesystems.disks.s3.bucket', ''), '/');

        if ($bucket !== '') {
            $bucketWithSlash = $bucket.'/';

            if (str_starts_with($normalizedPath, $bucketWithSlash)) {
                $normalizedPath = substr($normalizedPath, strlen($bucketWithSlash));
            }
        }
        $root = trim((string) config('filesystems.disks.s3.root', ''), '/');

        if ($root === '') {
            return ltrim($normalizedPath, '/');
        }
        $rootWithSlash = $root.'/';

        if (str_starts_with($normalizedPath, $rootWithSlash)) {
            return substr($normalizedPath, strlen($rootWithSlash));
        }

        return ltrim($normalizedPath, '/');
    }

    private function storageDisk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    private function videoIdentifier(Video $video): string
    {
        $videoUuid = $video->uuid;

        return is_string($videoUuid) && $videoUuid !== '' ? $videoUuid : (string) $video->id;
    }

    private function buildDirectUploadPath(string $videoIdentifier, string $fileName, string $contentType): string
    {
        $basePath = trim((string) config('filesystems.paths.source_videos', 'source_videos'), '/');
        $extension = $this->resolveVideoExtension($fileName, $contentType);
        $path = $basePath.'/'.$videoIdentifier;

        if ($extension === '') {
            return $path;
        }

        return $path.'.'.$extension;
    }

    private function resolveVideoExtension(string $fileName, string $contentType): string
    {
        $extensionFromFileName = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $normalizedExtensionFromFileName = preg_replace('/[^a-z0-9]/', '', $extensionFromFileName);

        if (is_string($normalizedExtensionFromFileName) && $normalizedExtensionFromFileName !== '') {
            return $normalizedExtensionFromFileName;
        }

        $contentTypeToExtension = [
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-matroska' => 'mkv',
            'video/mp2t' => 'ts',
        ];

        $normalizedContentType = strtolower(trim($contentType));

        if (array_key_exists($normalizedContentType, $contentTypeToExtension)) {
            return $contentTypeToExtension[$normalizedContentType];
        }

        $suffix = strtolower(trim(Str::after($normalizedContentType, '/')));
        $normalizedSuffix = preg_replace('/[^a-z0-9]/', '', $suffix);

        return is_string($normalizedSuffix) ? $normalizedSuffix : '';
    }

    /**
     * @return array<string, string>
     */
    private function normalizeUploadHeaders(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $normalizedHeaders = [];

        foreach ($headers as $headerName => $headerValue) {
            if (! is_string($headerName) || trim($headerName) === '') {
                continue;
            }

            if (is_array($headerValue)) {
                $normalizedHeaderValues = array_filter($headerValue, static fn (mixed $value): bool => is_string($value) || is_numeric($value));

                if ($normalizedHeaderValues === []) {
                    continue;
                }

                $normalizedHeaders[$headerName] = implode(', ', array_map(static fn (mixed $value): string => (string) $value, $normalizedHeaderValues));

                continue;
            }

            if (! is_string($headerValue) && ! is_numeric($headerValue)) {
                continue;
            }

            $normalizedHeaders[$headerName] = (string) $headerValue;
        }

        return $normalizedHeaders;
    }
}
