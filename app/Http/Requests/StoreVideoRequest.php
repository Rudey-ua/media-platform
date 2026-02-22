<?php

namespace App\Http\Requests;

use App\Exceptions\ApiException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'video' => ['required', 'file', 'mimes:mp4,mov,avi,mkv,ts', 'max:51200'],
        ];
    }

    public function videoFile(): UploadedFile
    {
        $videoFile = $this->file('video');

        if ($videoFile instanceof UploadedFile) {
            return $videoFile;
        }

        throw new ApiException(
            message: 'The video field is required.',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
