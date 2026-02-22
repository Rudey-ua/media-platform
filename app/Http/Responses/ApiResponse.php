<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    public static function success(Request $request, mixed $data = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return self::json([
            'type' => 'data',
            'data' => self::normalize($data, $request),
        ], $status);
    }

    public static function created(Request $request, mixed $data = null): JsonResponse
    {
        return self::success($request, $data, Response::HTTP_CREATED);
    }

    public static function info(string $message, int $status = Response::HTTP_OK): JsonResponse
    {
        return self::json([
            'type' => 'info',
            'message' => $message,
        ], $status);
    }

    public static function error(string $message, int $status): JsonResponse
    {
        return self::json([
            'type' => 'error',
            'message' => $message,
        ], $status);
    }

    public static function domainError(string $message, int $status = Response::HTTP_CONFLICT): JsonResponse
    {
        return self::json([
            'type' => 'domain_error',
            'message' => $message,
        ], $status);
    }

    public static function validation(array $errors): JsonResponse
    {
        return self::json([
            'type' => 'validation_error',
            'message' => self::firstValidationMessage($errors),
            'details' => self::validationDetails($errors),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private static function json(array $payload, int $status): JsonResponse
    {
        return response()->json($payload, $status);
    }

    private static function validationDetails(array $errors): array
    {
        $details = [];

        foreach ($errors as $field => $messages) {
            $normalizedMessages = [];

            if (is_array($messages)) {
                foreach ($messages as $message) {
                    if (is_string($message) && $message !== '') {
                        $normalizedMessages[] = $message;
                    }
                }
            } elseif (is_string($messages) && $messages !== '') {
                $normalizedMessages[] = $messages;
            }
            if ($normalizedMessages === []) {
                continue;
            }
            $details[] = [
                'field' => (string) $field,
                'messages' => $normalizedMessages,
            ];
        }

        return $details;
    }

    private static function firstValidationMessage(array $errors): string
    {
        foreach ($errors as $messages) {
            if (! is_array($messages)) {
                if (is_string($messages) && $messages !== '') {
                    return $messages;
                }
                continue;
            }
            foreach ($messages as $message) {
                if (is_string($message) && $message !== '') {
                    return $message;
                }
            }
        }
        return 'Validation failed';
    }

    private static function normalize(mixed $data, Request $request): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve($request);
        }
        if ($data instanceof Arrayable) {
            return $data->toArray();
        }
        if ($data instanceof JsonSerializable) {
            return $data->jsonSerialize();
        }
        if (! is_array($data)) {
            return $data;
        }
        return array_map(function ($value) use ($request) {
            return self::normalize($value, $request);
        }, $data);
    }
}
