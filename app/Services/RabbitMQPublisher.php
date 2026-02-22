<?php

namespace App\Services;

use Exception;
use JsonException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    /**
     * @throws JsonException
     * @throws Exception
     */
    public function publish(string $queue, array $payload): void
    {
        $connection = new AMQPStreamConnection(
            config('queue.connections.rabbitmq.hosts.0.host'),
            config('queue.connections.rabbitmq.hosts.0.port'),
            config('queue.connections.rabbitmq.hosts.0.user'),
            config('queue.connections.rabbitmq.hosts.0.password'),
            config('queue.connections.rabbitmq.hosts.0.vhost'),
        );

        $channel = $connection->channel();

        $channel->queue_declare(
            $queue,
            false, // passive
            true,  // durable
            false, // exclusive
            false  // auto-delete
        );
        $message = new AMQPMessage(
            json_encode($payload, JSON_THROW_ON_ERROR),
            [
                'content_type'  => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]
        );
        $channel->basic_publish($message, '', $queue);

        $channel->close();
        $connection->close();
    }
}
