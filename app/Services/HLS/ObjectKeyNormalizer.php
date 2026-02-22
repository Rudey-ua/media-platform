<?php

namespace App\Services\HLS;

class ObjectKeyNormalizer
{
    private string $diskName;

    private string $bucketPrefix;

    private string $rootPrefix;

    public function __construct()
    {
        $this->diskName = (string) config('services.video_encoder.playback_disk', 's3');
        $this->bucketPrefix = trim((string) config('filesystems.disks.'.$this->diskName.'.bucket', ''), '/');
        $this->rootPrefix = trim((string) config('filesystems.disks.'.$this->diskName.'.root', ''), '/');
    }

    public function diskName(): string
    {
        return $this->diskName;
    }

    public function normalize(string $path): string
    {
        $normalizedPath = trim($path);

        if ($normalizedPath === '') {
            return '';
        }
        if ($this->isHttpUrl($normalizedPath)) {
            $parsedPath = parse_url($normalizedPath, PHP_URL_PATH);
            $normalizedPath = is_string($parsedPath) ? $parsedPath : $normalizedPath;
        } elseif (str_starts_with($normalizedPath, 's3://')) {
            $normalizedPath = $this->stripS3SchemeAndBucket($normalizedPath);
        }
        $normalizedPath = ltrim($normalizedPath, '/');
        $normalizedPath = $this->stripConfiguredPrefix($normalizedPath, $this->bucketPrefix);
        $normalizedPath = $this->stripConfiguredPrefix($normalizedPath, $this->rootPrefix);

        return $this->normalizeDotSegments($normalizedPath);
    }

    private function stripConfiguredPrefix(string $path, string $prefix): string
    {
        if ($prefix === '') {
            return $path;
        }
        $prefixWithSlash = $prefix.'/';

        if (! str_starts_with($path, $prefixWithSlash)) {
            return $path;
        }
        return substr($path, strlen($prefixWithSlash));
    }

    private function stripS3SchemeAndBucket(string $path): string
    {
        $pathWithoutScheme = substr($path, 5);
        $firstSlashPosition = strpos($pathWithoutScheme, '/');

        if (! is_int($firstSlashPosition)) {
            return $pathWithoutScheme;
        }
        return substr($pathWithoutScheme, $firstSlashPosition + 1);
    }

    private function normalizeDotSegments(string $path): string
    {
        $segments = explode('/', str_replace('\\', '/', $path));
        $normalizedSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($normalizedSegments);
                continue;
            }
            $normalizedSegments[] = $segment;
        }
        return implode('/', $normalizedSegments);
    }

    private function isHttpUrl(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }
}
