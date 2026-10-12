<?php

use App\Exceptions\BusinessRuleException;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('The given data was invalid.', 422, $e->errors());
            }
        });

        $exceptions->render(function (BusinessRuleException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error($e->getMessage(), $e->status(), $e->errors() ?: null);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Unauthenticated.', 401);
            }
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('You are not authorized to perform this action.', 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Resource not found.', 404);
            }
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('CSRF token mismatch. Refresh and try again.', 419);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                $message = $e->getMessage() ?: 'Request failed.';

                return ApiResponse::error($message, $e->getStatusCode())->withHeaders($e->getHeaders());
            }
        });

        // Unexpected errors keep the envelope; the real message is only exposed in debug mode.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if ($isApi($request) && ! $e instanceof HttpResponseException) {
                return ApiResponse::error(config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.', 500);
            }
        });
    })->create();
