<?php

declare(strict_types=1);

use App\Http\Problem\ProblemFactory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    // Adding /api/v2 later: `api:` takes ONE file, so a second version is registered through
    // `then:` rather than by editing the line below —
    //   ->withRouting(
    //       api: __DIR__.'/../routes/api/v1.php',
    //       apiPrefix: 'api/v1',
    //       then: function (): void {
    //           Route::middleware('api')->prefix('api/v2')->group(__DIR__.'/../routes/api/v2.php');
    //       },
    //   )
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api/v1.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $problem = ProblemFactory::make($e, debug: config('app.debug') === true);

            if ($problem === null) {
                return null;
            }

            // The exception's own headers come first and are then overridden by the
            // content type. Dropping them would lose Retry-After on a 429 and Allow on
            // a 405 — this template ships two rate limiters, so that matters.
            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return new JsonResponse(
                $problem,
                $problem['status'],
                [...$headers, 'Content-Type' => 'application/problem+json'],
            );
        });
    })->create();
