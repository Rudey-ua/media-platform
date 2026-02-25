<?php

namespace App\Http\Controllers\Swagger;

use OpenApi\Attributes as OA;

final class VideoController
{
    #[OA\Post(
        path: '/api/v1/videos/uploads',
        operationId: 'videosInitiateUpload',
        summary: 'Create video upload session and get signed S3 URL',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['file_name', 'file_size', 'content_type'],
                properties: [
                    new OA\Property(property: 'file_name', type: 'string', example: 'big-movie.mp4'),
                    new OA\Property(property: 'file_size', type: 'integer', example: 1073741824),
                    new OA\Property(property: 'content_type', type: 'string', example: 'video/mp4'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Videos'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Upload initialized',
                content: new OA\JsonContent(
                    required: ['type', 'data'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'data'),
                        new OA\Property(
                            property: 'data',
                            required: ['video', 'upload'],
                            properties: [
                                new OA\Property(
                                    property: 'video',
                                    required: ['id', 'status', 'created_at', 'updated_at'],
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '31fb6a85-ef37-4a13-8ed2-9f2e28e455a1'),
                                        new OA\Property(property: 'status', type: 'string', example: 'uploading'),
                                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                                    ],
                                    type: 'object',
                                ),
                                new OA\Property(
                                    property: 'upload',
                                    required: ['url', 'headers', 'method', 'expires_at'],
                                    properties: [
                                        new OA\Property(property: 'url', type: 'string', format: 'uri'),
                                        new OA\Property(property: 'method', type: 'string', example: 'PUT'),
                                        new OA\Property(property: 'headers', type: 'object', example: ['Content-Type' => 'video/mp4']),
                                        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
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
                        new OA\Property(property: 'message', type: 'string', example: 'The content type must be a valid video mime type.'),
                    ],
                ),
            ),
        ],
    )]
    public function initiateUpload(): void {}

    #[OA\Post(
        path: '/api/v1/videos/{videoId}/uploads/complete',
        operationId: 'videosCompleteUpload',
        summary: 'Finalize direct upload and dispatch encoding',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'uploaded_size', type: 'integer', example: 1073741824),
                    new OA\Property(property: 'etag', type: 'string', example: '81f4fca8ce86ad73b2e57f8a1f4623f6'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Videos'],
        parameters: [
            new OA\Parameter(
                name: 'videoId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Upload finalized',
                content: new OA\JsonContent(
                    required: ['type', 'data'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'data'),
                        new OA\Property(
                            property: 'data',
                            required: ['video'],
                            properties: [
                                new OA\Property(
                                    property: 'video',
                                    required: ['id', 'status', 'created_at', 'updated_at'],
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '31fb6a85-ef37-4a13-8ed2-9f2e28e455a1'),
                                        new OA\Property(property: 'status', type: 'string', example: 'processing'),
                                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
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
                response: 404,
                description: 'Video not found',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Video not found'),
                    ],
                ),
            ),
            new OA\Response(
                response: 409,
                description: 'Domain conflict',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Uploaded file was not found in storage.'),
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'validation_error'),
                        new OA\Property(property: 'message', type: 'string', example: 'The uploaded size field must be at least 1.'),
                    ],
                ),
            ),
        ],
    )]
    public function completeUpload(): void {}

    #[OA\Patch(
        path: '/api/v1/videos/{videoId}',
        operationId: 'videosUpdate',
        summary: 'Update video metadata',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Updated title', nullable: true),
                ],
                type: 'object',
            ),
        ),
        tags: ['Videos'],
        parameters: [
            new OA\Parameter(
                name: 'videoId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Video updated',
                content: new OA\JsonContent(
                    required: ['type', 'data'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'data'),
                        new OA\Property(
                            property: 'data',
                            required: ['video'],
                            properties: [
                                new OA\Property(
                                    property: 'video',
                                    required: ['id', 'status', 'created_at', 'updated_at'],
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '31fb6a85-ef37-4a13-8ed2-9f2e28e455a1'),
                                        new OA\Property(property: 'title', type: 'string', example: 'Updated title', nullable: true),
                                        new OA\Property(property: 'status', type: 'string', example: 'ready'),
                                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
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
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Only owners can rename videos'),
                    ],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Video not found',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Video not found'),
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'validation_error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Title may not be greater than 255 characters.'),
                    ],
                ),
            ),
        ],
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/api/v1/videos/{videoId}',
        operationId: 'videosDelete',
        summary: 'Delete video and its HLS output assets',
        security: [['bearerAuth' => []]],
        tags: ['Videos'],
        parameters: [
            new OA\Parameter(
                name: 'videoId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Video deleted',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'info'),
                        new OA\Property(property: 'message', type: 'string', example: 'Video deleted'),
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
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Only owners can delete videos'),
                    ],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Video not found',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Video not found'),
                    ],
                ),
            ),
            new OA\Response(
                response: 409,
                description: 'Domain conflict',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Video is processing and cannot be deleted yet.'),
                    ],
                ),
            ),
            new OA\Response(
                response: 500,
                description: 'Storage delete failure',
                content: new OA\JsonContent(
                    required: ['type', 'message'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Unable to delete video from storage.'),
                    ],
                ),
            ),
        ],
    )]
    public function destroy(): void {}
}
