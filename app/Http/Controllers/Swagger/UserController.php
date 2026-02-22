<?php

namespace App\Http\Controllers\Swagger;

use OpenApi\Attributes as OA;

final class UserController
{
    #[OA\Get(
        path: '/api/v1/profile',
        operationId: 'authProfile',
        summary: 'Get current user profile',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current user profile',
                content: new OA\JsonContent(
                    required: ['type', 'data'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'data'),
                        new OA\Property(
                            property: 'data',
                            required: ['user'],
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    required: ['id', 'name', 'email', 'profile_image'],
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 6),
                                        new OA\Property(property: 'name', type: 'string', example: 'Maksym'),
                                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'koctenko525@gmail.com'),
                                        new OA\Property(property: 'profile_image', type: 'string', example: 'http://localhost/storage/profile_images/65f8f6f9c3_avatar.jpg', nullable: true),
                                    ],
                                    type: 'object',
                                ),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
                    ],
                ),
            ),
        ],
    )]
    public function profile(): void {}

    #[OA\Post(
        path: '/api/v1/profile/avatar',
        operationId: 'authUpdateAvatar',
        summary: 'Upload or replace current user profile avatar',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['profile_image'],
                    properties: [
                        new OA\Property(property: 'profile_image', type: 'string', format: 'binary'),
                    ],
                    type: 'object',
                ),
            ),
        ),
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avatar uploaded',
                content: new OA\JsonContent(
                    required: ['type', 'data'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'data'),
                        new OA\Property(
                            property: 'data',
                            required: ['user'],
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    required: ['id', 'name', 'email', 'profile_image'],
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 6),
                                        new OA\Property(property: 'name', type: 'string', example: 'Maksym'),
                                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'koctenko525@gmail.com'),
                                        new OA\Property(property: 'profile_image', type: 'string', example: 'http://localhost/storage/profile_images/65f8f6f9c3_avatar.jpg', nullable: true),
                                    ],
                                    type: 'object',
                                ),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    required: ['type', 'message', 'details'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'validation_error'),
                        new OA\Property(property: 'message', type: 'string', example: 'The profile image field is required.'),
                        new OA\Property(
                            property: 'details',
                            type: 'array',
                            items: new OA\Items(
                                required: ['field', 'messages'],
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'profile_image'),
                                    new OA\Property(
                                        property: 'messages',
                                        type: 'array',
                                        items: new OA\Items(type: 'string', example: 'The profile image field is required.'),
                                    ),
                                ],
                                type: 'object',
                            ),
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function updateAvatar(): void {}

    #[OA\Delete(
        path: '/api/v1/profile/avatar',
        operationId: 'authDeleteAvatar',
        summary: 'Delete current user profile avatar',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avatar deleted',
                content: new OA\JsonContent(
                    required: ['type', 'data'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'data'),
                        new OA\Property(
                            property: 'data',
                            required: ['user'],
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    required: ['id', 'name', 'email', 'profile_image'],
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 6),
                                        new OA\Property(property: 'name', type: 'string', example: 'Maksym'),
                                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'koctenko525@gmail.com'),
                                        new OA\Property(property: 'profile_image', type: 'string', example: null, nullable: true),
                                    ],
                                    type: 'object',
                                ),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
                    ],
                ),
            ),
        ],
    )]
    public function deleteAvatar(): void {}
}
