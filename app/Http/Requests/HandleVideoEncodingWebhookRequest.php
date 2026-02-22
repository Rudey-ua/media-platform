<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HandleVideoEncodingWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'in:video.encoding.completed,video.encoding.failed'],
            'video_id' => ['required', 'uuid'],
            'status' => ['required', 'string', 'in:completed,failed'],
            'hls' => ['required_if:event,video.encoding.completed', 'array'],
            'hls.playlist' => ['required_if:event,video.encoding.completed', 'string'],
            'error' => ['required_if:event,video.encoding.failed', 'array'],
            'error.message' => ['required_if:event,video.encoding.failed', 'string'],
        ];
    }
}
