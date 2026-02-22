<?php

namespace App\Http\Controllers\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'Authentication API documentation',
    title: 'Sandbox API',
)]
#[OA\Server(
    url: '/',
    description: 'Current application host',
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'Paste access_token here',
    bearerFormat: 'JWT',
    scheme: 'bearer',
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'Authentication endpoints',
)]
#[OA\Tag(
    name: 'Users',
    description: 'User profile endpoints',
)]
final class OpenApi {}
