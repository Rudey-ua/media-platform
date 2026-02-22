<?php

namespace App\DataTransferObjects\User;

use App\Models\User;
use App\Traits\FileTrait;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class UserData extends Data
{
    use FileTrait;

    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $profileImage
    ) {}

    public static function fromModel(User $user): self
    {
        $self = new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            profileImage: $user->profile_image
        );
        $self->profileImage = $self->getProfileImageUrl($user->profile_image);

        return $self;
    }
}
