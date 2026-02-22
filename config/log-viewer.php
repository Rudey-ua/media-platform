<?php

return [
    'middleware' => [
        'web',
        \App\Http\Middleware\RedirectGuestsToLogin::class,
        'role:admin',
        \Opcodes\LogViewer\Http\Middleware\AuthorizeLogViewer::class,
    ],
    'api_middleware' => [
        \Opcodes\LogViewer\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        \App\Http\Middleware\RedirectGuestsToLogin::class,
        'role:admin',
        \Opcodes\LogViewer\Http\Middleware\AuthorizeLogViewer::class,
    ],
];
