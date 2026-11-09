<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$isApi = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

/**
 * Every API error has the same shape: {message, code, errors}.
 */
$apiError = fn (string $message, string $code, int $status, array $errors = [], array $headers = []) => response()->json([
    'message' => $message,
    'code' => $code,
    'errors' => (object) $errors,
], $status, $headers);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'feature' => EnsureFeatureEnabled::class,
            'password.changed' => EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isApi, $apiError): void {
        $exceptions->dontReport(ApiException::class);

        $exceptions->shouldRenderJsonWhen($isApi);

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi, $apiError) {
            if ($isApi($request)) {
                return $apiError($e->getMessage(), 'VALIDATION_FAILED', $e->status, $e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi, $apiError) {
            if ($isApi($request)) {
                return $apiError('Unauthenticated.', 'UNAUTHENTICATED', 401);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi, $apiError) {
            if (! $isApi($request)) {
                return null;
            }

            $status = $e->getStatusCode();

            [$code, $defaultMessage] = match ($status) {
                403 => ['FORBIDDEN', 'This action is unauthorized.'],
                404 => ['NOT_FOUND', 'Resource not found.'],
                405 => ['METHOD_NOT_ALLOWED', 'Method not allowed.'],
                429 => ['TOO_MANY_ATTEMPTS', 'Too many attempts. Please try again later.'],
                default => ['HTTP_ERROR', 'Request failed.'],
            };

            $message = $status === 404 || $e->getMessage() === '' ? $defaultMessage : $e->getMessage();

            return $apiError($message, $code, $status, headers: $e->getHeaders());
        });
    })->create();
