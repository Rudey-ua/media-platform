<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncMemberVideoAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'video_ids' => ['required', 'array'],
            'video_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function videoUuids(): array
    {
        /** @var array<int, string> $validated */
        $validated = $this->validated('video_ids', []);

        return $validated;
    }
}
