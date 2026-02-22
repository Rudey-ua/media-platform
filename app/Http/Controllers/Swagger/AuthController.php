<?php

namespace App\Http\Controllers\Swagger;

use OpenApi\Attributes as OA;

final class AuthController
{
    #[OA\Post(
        path: '/api/v1/register',
        operationId: 'authRegister',
        summary: 'Register a user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Maksym'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'koctenko525@gmail.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'RootRoot123'),
                ],
            ),
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registered',
                content: new OA\JsonContent(
                    required: ['type', 'data'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'data'),
                        new OA\Property(
                            property: 'data',
                            required: ['user', 'access_token', 'refresh_token', 'token_type', 'expires_in'],
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
                                new OA\Property(property: 'access_token', type: 'string', example: 'xxx'),
                                new OA\Property(property: 'refresh_token', type: 'string', example: 'xxx'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                            ],
                            type: 'object',
                        ),
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
                        new OA\Property(property: 'message', type: 'string', example: 'Email is taken'),
                        new OA\Property(
                            property: 'details',
                            type: 'array',
                            items: new OA\Items(
                                required: ['field', 'messages'],
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'email'),
                                    new OA\Property(
                                        property: 'messages',
                                        type: 'array',
                                        items: new OA\Items(type: 'string', example: 'Email is taken'),
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
    public function register(): void {}

    #[OA\Post(
        path: '/api/v1/login',
        operationId: 'authLogin',
        summary: 'Login user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'koctenko525@gmail.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'RootRoot123'),
                ],
            ),
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged in',
                content: new OA\JsonContent(
                    required: ['type', 'data'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'data'),
                        new OA\Property(
                            property: 'data',
                            required: ['user', 'access_token', 'refresh_token', 'token_type', 'expires_in'],
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
                                new OA\Property(property: 'access_token', type: 'string', example: 'xxx'),
                                new OA\Property(property: 'refresh_token', type: 'string', example: 'xxx'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication failed',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials'),
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
                        new OA\Property(property: 'message', type: 'string', example: 'The email field is required.'),
                        new OA\Property(
                            property: 'details',
                            type: 'array',
                            items: new OA\Items(
                                required: ['field', 'messages'],
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'email'),
                                    new OA\Property(
                                        property: 'messages',
                                        type: 'array',
                                        items: new OA\Items(type: 'string', example: 'The email field is required.'),
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
    public function login(): void {}

    #[OA\Post(
        path: '/api/v1/refresh',
        operationId: 'authRefresh',
        summary: 'Refresh access token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['refresh_token'],
                properties: [
                    new OA\Property(property: 'refresh_token', type: 'string', example: 'xxx'),
                ],
            ),
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Access token refreshed',
                content: new OA\JsonContent(
                    required: ['type', 'data'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'data'),
                        new OA\Property(
                            property: 'data',
                            required: ['access_token', 'token_type', 'expires_in'],
                            properties: [
                                new OA\Property(property: 'access_token', type: 'string', example: 'xxx'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Refresh token invalid',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid or expired refresh token'),
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
                        new OA\Property(property: 'message', type: 'string', example: 'The refresh token field is required.'),
                        new OA\Property(
                            property: 'details',
                            type: 'array',
                            items: new OA\Items(
                                required: ['field', 'messages'],
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'refresh_token'),
                                    new OA\Property(
                                        property: 'messages',
                                        type: 'array',
                                        items: new OA\Items(type: 'string', example: 'The refresh token field is required.'),
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
    public function refreshToken(): void {}

    #[OA\Post(
        path: '/api/v1/logout',
        operationId: 'authLogout',
        summary: 'Logout current user',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'info'),
                        new OA\Property(property: 'message', type: 'string', example: 'Successfully logged out'),
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
    public function logout(): void {}
}
