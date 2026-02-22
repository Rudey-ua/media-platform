<?php

namespace App\Http\Controllers\API;

use App\DataTransferObjects\User\VideoData;
use App\Http\Controllers\Controller;
use App\Http\Requests\HandleVideoEncodingWebhookRequest;
use App\Http\Responses\ApiResponse;
use App\Services\VideoService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    public function handle(HandleVideoEncodingWebhookRequest $request, VideoService $videoService): JsonResponse
    {
        $token = (string) $request->header('X-Webhook-Token', '');
        $expectedToken = (string) config('services.video_encoder.webhook_token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $token)) {
            return ApiResponse::error(
                message: 'Unauthorized',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        $video = $videoService->handleEncodingWebhook($request->validated());

        return ApiResponse::success(
            request: $request,
            data: [
                'video' => VideoData::fromModel($video),
            ],
        );
    }
}
