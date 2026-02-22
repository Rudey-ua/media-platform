<?php

namespace App\DataTransferObjects\User;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProfileAvatarData
{
    public function __construct(
        public UploadedFile $file,
    ){}

    public static function fromArray(array $data): self
    {
        return new self(file: $data['profile_image']);
    }
}
