<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Services\HLS\ObjectKeyNormalizer;
use App\Services\HLS\PlaylistRewriter;
use App\Services\HLS\ReferenceResolver;
use App\Services\HLS\SignedUrlGenerator;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PlaybackService
{
    private const int PLAYBACK_TTL_SECONDS = 1200;

    public function __construct(
        private readonly ObjectKeyNormalizer $objectKeyNormalizer,
        private readonly ReferenceResolver $referenceResolver,
        private readonly PlaylistRewriter $playlistRewriter,
        private readonly SignedUrlGenerator $signedUrlGenerator,
    ) {}

    public function renderSignedPlaylist(string $storedPlaylistPath): string
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
        $expiresAt = now()->addSeconds(self::PLAYBACK_TTL_SECONDS);

        return $this->playlistRewriter->rewrite(
            playlistContent: $playlistContent,
            signReference: fn (string $reference): string => $this->signReference(
                reference: $reference,
                playlistDirectory: $playlistDirectory,
                expiresAt: $expiresAt,
            ),
        );
    }

    public function normalizeStoredPlaylistPath(string $storedPlaylistPath): string
    {
        return $this->objectKeyNormalizer->normalize($storedPlaylistPath);
    }

    private function signReference(string $reference, string $playlistDirectory, DateTimeInterface $expiresAt): string
    {
        $trimmedReference = trim($reference);

        if ($trimmedReference === '' || str_starts_with($trimmedReference, 'data:')) {
            return $trimmedReference;
        }
        $objectKey = $this->referenceResolver->resolve(
            reference: $trimmedReference,
            playlistDirectory: $playlistDirectory,
        );
        if ($objectKey === '') {
            throw new ApiException(
                message: 'Playback is unavailable',
                status: Response::HTTP_CONFLICT,
            );
        }
        return $this->signedUrlGenerator->sign(
            objectKey: $objectKey,
            expiresAt: $expiresAt,
        );
    }
}
