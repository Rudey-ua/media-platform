<?php

namespace App\Services;

use App\DataTransferObjects\User\ProfileAvatarData;
use App\Models\User;
use App\Traits\FileTrait;

class UserService
{
    use FileTrait;

    public function updateProfileAvatar(User $user, ProfileAvatarData $data): User
    {
        if ($user->profile_image) {
            $this->deleteFile($user->profile_image, config('filesystems.paths.profile_images'));
        }
        $user->update(['profile_image' => $this->uploadFile($data->file, config('filesystems.paths.profile_images'))]);

        return $user;
    }

    public function deleteProfileAvatar(User $user): User
    {
        if ($user->profile_image) {
            $this->deleteFile($user->profile_image, config('filesystems.paths.profile_images'));
        }
        $user->update(['profile_image' => null]);

        return $user;
    }
}
