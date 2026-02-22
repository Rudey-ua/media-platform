<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait FileTrait
{
    public function getProfileImageUrl(?string $fileName): ?string
    {
        $filePath = $this->getImagePath($fileName, config('filesystems.paths.profile_images'));
        return $filePath ? url(Storage::url($filePath)) : null;
    }

    public function uploadFile($file, string $path): string|false
    {
        $filename = uniqid('', true).'_'.str_replace(' ', '_', ($file->getClientOriginalName() ?? 'image'));

        $path = Storage::disk()->putFileAs($path, $file, $filename);

        return $path ? $filename : false;
    }

    public function getImagePath(?string $fileName, string $path): ?string
    {
        return $fileName ? trim($path, '/') . '/' . $fileName : null;
    }

    public function deleteFile(string $imageUrl, string $path): void
    {
        $path = $this->getImagePath($imageUrl, $path);

        if(Storage::exists($path)) {
            Storage::disk()->delete($path);
        }
    }
}
