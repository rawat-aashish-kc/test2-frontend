<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Enforce CLAUDE.md's API response rules on every exception: never an empty body,
        // never a leaked class/internal name, always a human-readable `message`. Catches
        // anything unexpected and hides internals from the response, logging the real error.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            // Never leak an internal model/class name or return an empty body for a 404;
            // keep an explicit abort() message if one was given. Laravel converts a route
            // model binding miss (ModelNotFoundException) into a NotFoundHttpException
            // before render callbacks run, keeping the original "No query results for
            // model [...]" text as the message — check the wrapped exception too.
            if ($e instanceof ModelNotFoundException || $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Not found.'], 404);
            }

            if ($e instanceof NotFoundHttpException) {
                $message = $e->getMessage();

                return response()->json(['message' => $message !== '' ? $message : 'Not found.'], 404);
            }

            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                $message = $e->getMessage();

                return response()->json(['message' => $message !== '' ? $message : 'Something went wrong.'], $e->getStatusCode());
            }

            report($e);

            return response()->json(['message' => 'Server error'], 500);
        });
    })->create();
