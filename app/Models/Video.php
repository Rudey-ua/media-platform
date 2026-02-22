<?php

namespace App\Models;

use App\Enums\VideoStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'original_path',
        'hls_path',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => VideoStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
