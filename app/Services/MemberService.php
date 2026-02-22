<?php

namespace App\Services;

use App\Enums\MemberVideoAccessMode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MemberService
{
    public function listForOwner(User $owner): Collection
    {
        $this->assertOwner($owner);

        return User::query()
            ->role('member', 'web')
            ->where('owner_id', $owner->id)
            ->with(['videoAccesses.video:id,uuid'])
            ->orderBy('email')
            ->get();
    }

    /**
     * @return array{member: User, generated_password: string}
     */
    public function createForOwner(User $owner, string $email, MemberVideoAccessMode $accessMode = MemberVideoAccessMode::All): array
    {
        $this->assertOwner($owner);

        $generatedPassword = Str::random(14);
        $memberName = $this->memberNameFromEmail($email);

        $member = DB::transaction(function () use ($owner, $email, $memberName, $generatedPassword, $accessMode): User {
            $member = User::query()->create([
                'owner_id' => $owner->id,
                'access_mode' => $accessMode->value,
                'name' => $memberName,
                'email' => $email,
                'password' => $generatedPassword,
                'email_verified_at' => now(),
            ]);

            $member->assignRole('member');

            return $member;
        });

        Log::channel('member_credentials')->info('Owner created member account', [
            'owner_id' => $owner->id,
            'owner_email' => $owner->email,
            'member_id' => $member->id,
            'member_email' => $member->email,
            'generated_password' => $generatedPassword,
            'created_at' => now()->toIso8601String(),
        ]);

        return [
            'member' => $member->fresh(['videoAccesses.video']) ?? $member,
            'generated_password' => $generatedPassword,
        ];
    }

    public function updateAccessModeForOwner(User $owner, int $memberId, MemberVideoAccessMode $accessMode): User
    {
        $this->assertOwner($owner);

        $member = $this->ownedMember($owner, $memberId);
        $member->update([
            'access_mode' => $accessMode->value,
        ]);

        return $member->fresh(['videoAccesses.video']) ?? $member;
    }

    /**
     * @param  array<int, string>  $videoUuids
     */
    public function syncVideoAccessForOwner(User $owner, int $memberId, array $videoUuids): User
    {
        $this->assertOwner($owner);

        $member = $this->ownedMember($owner, $memberId);
        $normalizedVideoUuids = array_values(array_unique(array_filter($videoUuids, static fn (string $uuid): bool => $uuid !== '')));

        $videos = Video::query()
            ->where('user_id', $owner->id)
            ->whereIn('uuid', $normalizedVideoUuids)
            ->get(['id', 'uuid']);

        if (count($normalizedVideoUuids) !== $videos->count()) {
            throw new ApiException(
                message: 'One or more videos are unavailable for access assignment.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        DB::transaction(function () use ($owner, $member, $videos): void {
            VideoAccess::query()
                ->where('member_id', $member->id)
                ->where('owner_id', $owner->id)
                ->delete();

            if ($videos->isEmpty()) {
                return;
            }

            VideoAccess::query()->insert(
                $videos
                    ->map(function (Video $video) use ($owner, $member): array {
                        return [
                            'video_id' => $video->id,
                            'member_id' => $member->id,
                            'owner_id' => $owner->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })
                    ->all(),
            );
        });

        return $member->fresh(['videoAccesses.video']) ?? $member;
    }

    private function assertOwner(User $user): void
    {
        if ($user->isOwner()) {
            return;
        }

        throw new ApiException(
            message: 'Forbidden',
            status: Response::HTTP_FORBIDDEN,
        );
    }

    private function ownedMember(User $owner, int $memberId): User
    {
        $member = User::query()
            ->role('member', 'web')
            ->where('owner_id', $owner->id)
            ->whereKey($memberId)
            ->first();

        if ($member instanceof User) {
            return $member;
        }

        throw new ApiException(
            message: 'Member not found',
            status: Response::HTTP_NOT_FOUND,
        );
    }

    private function memberNameFromEmail(string $email): string
    {
        $localPart = Str::before($email, '@');
        $normalized = trim((string) preg_replace('/[^a-zA-Z0-9._-]/', '', $localPart));

        if ($normalized === '') {
            return 'Member';
        }

        return Str::title(str_replace(['.', '_', '-'], ' ', $normalized));
    }
}
