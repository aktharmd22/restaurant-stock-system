<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'admin' => \App\Http\Middleware\EnsureAdminSide::class,
            'branch' => \App\Http\Middleware\EnsureBranchUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /*
         * Errors get the same treatment as everything else: plain words and a
         * way forward. Nobody in a kitchen should ever see a stack trace or
         * the words "Error 419".
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            // A timed-out form is not really an error - send them back to the
            // screen they were on with an explanation.
            if ($response->getStatusCode() === 419) {
                return back()->with('error', 'You were away too long. Please try that again.');
            }

            // Keep Laravel's own error screen while developing.
            if (app()->hasDebugModeEnabled() && $response->getStatusCode() === 500) {
                return $response;
            }

            if (in_array($response->getStatusCode(), [403, 404, 429, 500, 503], true)) {
                return Inertia::render('Error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
