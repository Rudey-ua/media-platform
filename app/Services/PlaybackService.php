<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Services\HLS\ObjectKeyNormalizer;
use App\Services\HLS\PlaylistRewriter;
use App\Services\HLS\ReferenceResolver;
use App\Services\HLS\SignedUrlGenerator;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PlaybackService
{
    private const int PLAYBACK_SESSION_TTL_SECONDS = 1800;

    private const int PLAYBACK_ASSET_URL_TTL_SECONDS = 75;

    public function __construct(
        private readonly ObjectKeyNormalizer $objectKeyNormalizer,
        private readonly ReferenceResolver $referenceResolver,
        private readonly PlaylistRewriter $playlistRewriter,
        private readonly SignedUrlGenerator $signedUrlGenerator,
    ) {}

    /**
     * @return array{playlist: string, session_expires_at: string}
     */
    public function renderSignedPlaylistForViewer(string $storedPlaylistPath, string $videoId, int $viewerUserId, ?DateTimeInterface $sessionExpiresAt = null): array
    {
        $playlistObjectKey = $this->normalizeStoredPlaylistPath($storedPlaylistPath);

        if ($playlistObjectKey === '') {
            throw new ApiException(
                message: 'Playback is unavailable',
                status: Response::HTTP_CONFLICT,
            );
        }
        try {
            $playlistContent = Storage::disk($this->objectKeyNormalizer->diskName())->get($playlistObjectKey);
        } catch (Throwable) {
            throw new ApiException(
                message: 'Playback is unavailable',
                status: Response::HTTP_CONFLICT,
            );
        }
        if (! is_string($playlistContent) || trim($playlistContent) === '') {
            throw new ApiException(
                message: 'Playback is unavailable',
                status: Response::HTTP_CONFLICT,
            );
        }
        $playlistDirectory = trim(dirname($playlistObjectKey), '/');
        $resolvedSessionExpiresAt = $sessionExpiresAt ?? now()->addSeconds(self::PLAYBACK_SESSION_TTL_SECONDS);

        $playlist = $this->playlistRewriter->rewrite(
            playlistContent: $playlistContent,
            signReference: fn (string $reference): string => $this->signReference(
                reference: $reference,
                playlistDirectory: $playlistDirectory,
                sessionExpiresAt: $resolvedSessionExpiresAt,
                videoId: $videoId,
                viewerUserId: $viewerUserId,
            ),
        );
        return [
            'playlist' => $playlist,
            'session_expires_at' => Carbon::instance($resolvedSessionExpiresAt)->toIso8601String(),
        ];
    }

    public function normalizeStoredPlaylistPath(string $storedPlaylistPath): string
    {
        return $this->objectKeyNormalizer->normalize($storedPlaylistPath);
    }

    public function issueTemporaryAssetUrl(string $objectKey): string
    {
        $normalizedObjectKey = $this->normalizeStoredPlaylistPath($objectKey);

        if ($normalizedObjectKey === '') {
            throw new ApiException(
                message: 'Playback asset is unavailable',
                status: Response::HTTP_CONFLICT,
            );
        }
        return $this->signedUrlGenerator->sign(
            objectKey: $normalizedObjectKey,
            expiresAt: now()->addSeconds(self::PLAYBACK_ASSET_URL_TTL_SECONDS),
        );
    }

    private function signReference(string $reference, string $playlistDirectory, DateTimeInterface $sessionExpiresAt, string $videoId, int $viewerUserId): string
    {
        $trimmedReference = trim($reference);

        if ($trimmedReference === '' || str_starts_with($trimmedReference, 'data:')) {
            return $trimmedReference;
        }
        $objectKey = $this->referenceResolver->resolve($trimmedReference, $playlistDirectory);

        if ($objectKey === '') {
            throw new ApiException(
                message: 'Playback is unavailable',
                status: Response::HTTP_CONFLICT,
            );
        }
        return $this->buildPlaybackAssetUrl(
            videoId: $videoId,
            viewerUserId: $viewerUserId,
            objectKey: $objectKey,
            sessionExpiresAt: $sessionExpiresAt,
        );
    }

    private function buildPlaybackAssetUrl(string $videoId, int $viewerUserId, string $objectKey, DateTimeInterface $sessionExpiresAt): string
    {
        return URL::temporarySignedRoute(
            name: 'videos.playback.asset',
            expiration: $sessionExpiresAt,
            parameters: [
                'videoId' => $videoId,
                'viewer_id' => $viewerUserId,
                'path' => $objectKey,
            ]
        );
    }
}
