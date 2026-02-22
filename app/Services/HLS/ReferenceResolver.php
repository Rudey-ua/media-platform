<?php

namespace App\Services\HLS;

class ReferenceResolver
{
    public function __construct(private ObjectKeyNormalizer $objectKeyNormalizer) {}

    public function resolve(string $reference, string $playlistDirectory): string
    {
        if ($this->isAbsoluteReference($reference) || str_starts_with($reference, '/')) {
            return $this->objectKeyNormalizer->normalize($reference);
        }

        $candidatePath = $playlistDirectory === ''
            ? $reference
            : $playlistDirectory.'/'.$reference;

        return $this->objectKeyNormalizer->normalize($candidatePath);
    }

    private function isAbsoluteReference(string $reference): bool
    {
        return str_starts_with($reference, 'http://')
            || str_starts_with($reference, 'https://')
            || str_starts_with($reference, 's3://');
    }
}
