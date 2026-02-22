<?php

namespace App\Jobs;

use App\Services\RabbitMQPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishVideoMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(RabbitMQPublisher $publisher): void
    {
        try {
            $publisher->publish('video.encode', $this->payload);
        } catch (\JsonException|\Exception $e) {
            Log::error("Error: {$e->getMessage()}");
        }
    }
}
