<?php

namespace App\Http\Controllers\API;

use App\DataTransferObjects\User\VideoData;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteVideoUploadRequest;
use App\Http\Requests\InitiateUploadRequest;
use App\Http\Requests\StoreVideoRequest;
use App\Http\Requests\UpdateVideoTitleRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\Video;
use App\Services\VideoService;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VideoController extends Controller
{
    public function initiateUpload(InitiateUploadRequest $request, VideoService $videoService): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $uploadAttributes = $request->uploadAttributes();

        $initializedUpload = $videoService->initializeDirectUploadForUser(
            user: $user,
            fileName: $uploadAttributes['file_name'],
            fileSize: $uploadAttributes['file_size'],
            contentType: $uploadAttributes['content_type'],
            title: $uploadAttributes['title'] ?? null,
        );

        return ApiResponse::created(
            request: $request,
            data: [
                'video' => VideoData::fromModel($initializedUpload['video']),
                'upload' => $initializedUpload['upload'],
            ],
        );
    }

    public function completeUpload(CompleteVideoUploadRequest $request, string $videoId, VideoService $videoService): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $video = $videoService->completeDirectUploadForUser(
            user: $user,
            videoId: $videoId,
            expectedFileSize: $request->uploadedSize(),
        );

        return ApiResponse::success(
            request: $request,
            data: [
                'video' => VideoData::fromModel($video),
            ],
        );
    }

    public function store(StoreVideoRequest $request, VideoService $videoService): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $video = $videoService->uploadForUser($user, $request->videoFile());

        return ApiResponse::created(
            request: $request,
            data: [
                'video' => VideoData::fromModel($video),
            ],
        );
    }

    public function index(Request $request, VideoService $videoService): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $videos = $videoService->listForUser($user);

        return ApiResponse::success(
            request: $request,
            data: [
                'videos' => $videos->map(static fn (Video $video) => VideoData::fromModel($video))->all(),
            ],
        );
    }

    public function show(Request $request, string $videoId, VideoService $videoService): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $video = $videoService->findForUser(user: $user, videoId: $videoId);

        return ApiResponse::success(
            request: $request,
            data: [
                'video' => VideoData::fromModel($video),
            ],
        );
    }

    public function destroy(Request $request, string $videoId, VideoService $videoService): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        $videoService->deleteForUser($user, $videoId);

        return ApiResponse::info('Video deleted');
    }

    public function update(UpdateVideoTitleRequest $request, string $videoId, VideoService $videoService): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $video = $videoService->updateTitleForUser(
            user: $user,
            videoId: $videoId,
            title: $request->title(),
        );

        return ApiResponse::success(
            request: $request,
            data: [
                'video' => VideoData::fromModel($video),
            ],
        );
    }

    public function playback(Request $request, string $videoId, VideoService $videoService): Response
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $playbackPayload = $videoService->getPlaybackPlaylistForUser($user, $videoId);

        return response(
            content: $playbackPayload['playlist'],
            status: Response::HTTP_OK,
            headers: $this->playbackHeaders($playbackPayload['session_expires_at']),
        );
    }

    public function playbackAsset(Request $request, string $videoId, VideoService $videoService): Response|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return ApiResponse::error(
                message: 'Invalid playback signature',
                status: Response::HTTP_FORBIDDEN,
            );
        }
        $viewerId = $request->query('viewer_id');
        $requestedAssetPath = $request->query('path');

        if (! is_scalar($viewerId) || (int) $viewerId < 1) {
            return ApiResponse::error(
                message: 'Invalid playback signature',
                status: Response::HTTP_FORBIDDEN,
            );
        }
        if (! is_string($requestedAssetPath) || trim($requestedAssetPath) === '') {
            return ApiResponse::error(
                message: 'Playback asset path is missing',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
        $expiresAtUnixTimestamp = $request->query('expires');

        $sessionExpiresAt = is_scalar($expiresAtUnixTimestamp) && is_numeric((string) $expiresAtUnixTimestamp)
            ? (new DateTimeImmutable)->setTimestamp((int) $expiresAtUnixTimestamp)
            : null;

        $playbackAssetPayload = $videoService->resolvePlaybackAssetForViewer(
            videoId: $videoId,
            viewerUserId: (int) $viewerId,
            assetPath: $requestedAssetPath,
            sessionExpiresAt: $sessionExpiresAt,
        );

        if ($playbackAssetPayload['type'] === 'playlist') {
            return response(
                content: $playbackAssetPayload['content'],
                status: Response::HTTP_OK,
                headers: $this->playbackHeaders($playbackAssetPayload['session_expires_at']),
            );
        }

        return redirect()
            ->away($playbackAssetPayload['url'], Response::HTTP_TEMPORARY_REDIRECT)
            ->withHeaders($this->playbackCacheHeaders());
    }

    /**
     * @return array<string, string>
     */
    private function playbackHeaders(string $sessionExpiresAt): array
    {
        return array_merge(
            [
                'Content-Type' => 'application/vnd.apple.mpegurl',
                'X-Playback-Session-Expires-At' => $sessionExpiresAt,
            ],
            $this->playbackCacheHeaders(),
        );
    }

    /**
     * @return array<string, string>
     */
    private function playbackCacheHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}
