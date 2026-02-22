<?php

namespace App\Http\Controllers\API;

use App\DataTransferObjects\User\VideoData;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteVideoUploadRequest;
use App\Http\Requests\InitiateUploadRequest;
use App\Http\Requests\StoreVideoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Http\JsonResponse;
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

    public function playback(Request $request, string $videoId, VideoService $videoService): Response
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        return response(
            content: $videoService->getPlaybackPlaylistForUser($user, $videoId),
            status: Response::HTTP_OK,
            headers: [
                'Content-Type' => 'application/vnd.apple.mpegurl',
            ],
        );
    }
}
