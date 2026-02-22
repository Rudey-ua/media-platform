<?php

namespace App\DataTransferObjects\User;

use App\Enums\VideoStatus;
use App\Models\Video;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class VideoData extends Data
{
    public function __construct(
        public string $id,
        public ?string $title,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromModel(Video $video): self
    {
        $status = $video->status;
        $uuid = $video->uuid;

        return new self(
            id: is_string($uuid) && $uuid !== '' ? $uuid : (string) $video->id,
            title: is_string($video->title) && $video->title !== '' ? $video->title : null,
            status: $status instanceof VideoStatus ? $status->value : (string) $status,
            createdAt: $video->created_at?->toIso8601String() ?? now()->toIso8601String(),
            updatedAt: $video->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        );
    }
}
