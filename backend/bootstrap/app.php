<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API clients get a 401, never a redirect to a login page this API does not have.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A missing record (often one deleted after a notification pointed to it) gets a plain message,
        // never "No query results for model [App\Models\Event] 12".
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/*') && $exception->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => 'This was removed or no longer exists.'], 404);
            }
        });
    })->create();
