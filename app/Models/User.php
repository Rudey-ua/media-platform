<?php

namespace App\Models;

use App\Enums\MemberVideoAccessMode;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'owner_id',
        'access_mode',
        'email_verified_at',
        'profile_image',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'owner_id' => 'integer',
            'access_mode' => MemberVideoAccessMode::class,
            'password' => 'hashed',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(self::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(self::class, 'owner_id');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class, 'user_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'user_id');
    }

    public function videoAccesses(): HasMany
    {
        return $this->hasMany(VideoAccess::class, 'member_id');
    }

    public function grantedVideoAccesses(): HasMany
    {
        return $this->hasMany(VideoAccess::class, 'owner_id');
    }

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    public function isMember(): bool
    {
        return $this->hasRole('member');
    }

    public function resolvedMemberVideoAccessMode(): MemberVideoAccessMode
    {
        $mode = $this->access_mode;

        return $mode instanceof MemberVideoAccessMode ? $mode : MemberVideoAccessMode::All;
    }

    public function getJWTIdentifier(): int
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
