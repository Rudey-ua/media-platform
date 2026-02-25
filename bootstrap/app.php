<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Responses\ApiResponse;
use App\Services\RefreshTokenCookieService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->encryptCookies(except: [
            RefreshTokenCookieService::COOKIE_NAME,
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(static function (Request $request): string {
            $user = $request->user();

            if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                $configuredPath = trim((string) config('telescope.path', 'telescope'));

                if ($configuredPath === '') {
                    return '/telescope';
                }
                return '/'.ltrim($configuredPath, '/');
            }
            return '/';
        });
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $isApiRequest = static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->report(function (Throwable $exception): ?bool {

            if ($exception instanceof DomainException) {
                return false;
            }
            $status = match (true) {
                $exception instanceof ApiException => $exception->status,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => Response::HTTP_INTERNAL_SERVER_ERROR,
            };

            if ($status < Response::HTTP_INTERNAL_SERVER_ERROR) {
                return false;
            }
            $request = app()->bound('request') ? request() : null;
            $user = $request?->user();
            $emailInput = $request?->input('email');

            Log::error('Server error captured', [
                'status' => $status,
                'user_id' => $user?->getAuthIdentifier(),
                'email' => $user?->email ?? (is_string($emailInput) && $emailInput !== '' ? $emailInput : null),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'exception_line' => $exception->getLine(),
                'path' => $request?->path(),
                'method' => $request?->method(),
            ]);

            return false;
        });

        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) use ($isApiRequest): bool {
            return $isApiRequest($request);
        });
        $exceptions->render(function (ApiException $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error(
                message: $exception->getMessage(),
                status: $exception->status,
            );
        });
        $exceptions->render(function (\DomainException $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::domainError(
                message: $exception->getMessage(),
            );
        });
        $exceptions->render(function (ValidationException $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::validation(
                errors: $exception->errors(),
            );
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        });
        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error(
                message: 'Forbidden',
                status: Response::HTTP_FORBIDDEN,
            );
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request)) {
                return null;
            }
            $status = $exception->getStatusCode();
            $message = $exception->getMessage();

            if ($message === '') {
                $message = Response::$statusTexts[$status] ?? 'HTTP error.';
            }

            return ApiResponse::error(
                message: $message,
                status: $status,
            );
        });
        $exceptions->render(function (Throwable $exception, Request $request) use ($isApiRequest): ?JsonResponse {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error(
                message: 'Internal server error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        });
    })->create();
