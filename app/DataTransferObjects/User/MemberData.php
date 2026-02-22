<?php

namespace App\DataTransferObjects\User;

use App\Enums\MemberVideoAccessMode;
use App\Models\User;
use App\Models\VideoAccess;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class MemberData extends Data
{
    /**
     * @param  array<int, string>  $grantedVideoIds
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $accessMode,
        public array $grantedVideoIds,
        public string $createdAt,
    ) {}

    public static function fromModel(User $member): self
    {
        $accessMode = $member->resolvedMemberVideoAccessMode();
        $videoAccesses = $member->relationLoaded('videoAccesses')
            ? $member->videoAccesses
            : $member->videoAccesses()->with('video:id,uuid')->get();

        return new self(
            id: $member->id,
            name: $member->name,
            email: $member->email,
            accessMode: $accessMode instanceof MemberVideoAccessMode ? $accessMode->value : MemberVideoAccessMode::All->value,
            grantedVideoIds: $videoAccesses
                ->map(static fn (VideoAccess $videoAccess): ?string => $videoAccess->video?->uuid)
                ->filter(static fn (?string $uuid): bool => is_string($uuid) && $uuid !== '')
                ->values()
                ->all(),
            createdAt: $member->created_at?->toIso8601String() ?? now()->toIso8601String(),
        );
    }
}
