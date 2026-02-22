<?php

namespace App\Services\HLS;

use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class SignedUrlGenerator
{
    private ?FilesystemAdapter $filesystem = null;

    public function __construct(private readonly ObjectKeyNormalizer $objectKeyNormalizer) {}

    public function sign(string $objectKey, DateTimeInterface $expiresAt): string
    {
        $signedUrl = $this->filesystem()->temporaryUrl($objectKey, $expiresAt);

        if (! str_starts_with($signedUrl, 'http://')) {
            return $signedUrl;
        }
        return 'https://'.substr($signedUrl, 7);
    }

    private function filesystem(): FilesystemAdapter
    {
        if ($this->filesystem instanceof FilesystemAdapter) {
            return $this->filesystem;
        }
        /** @var FilesystemAdapter $filesystem */
        $filesystem = Storage::disk($this->objectKeyNormalizer->diskName());
        $this->filesystem = $filesystem;

        return $this->filesystem;
    }
}
