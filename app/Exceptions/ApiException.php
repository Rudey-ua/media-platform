<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ApiException extends RuntimeException
{
    public static function domain(string $message, int $status = Response::HTTP_CONFLICT): self
    {
        return new self(
            message: $message,
            status: $status,
        );
    }

    public function __construct(
        string $message,
        public readonly int $status = Response::HTTP_BAD_REQUEST,
    ) {
        parent::__construct($message);
    }
}
