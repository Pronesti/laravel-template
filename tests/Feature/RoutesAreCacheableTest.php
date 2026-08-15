<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;

it('registers no closure routes under the api prefix', function (): void {
    // `php artisan route:cache` — the standard production optimize step — throws for
    // any closure route. Framework routes outside api/ (e.g. the /up health check) are
    // the framework's problem; every route this template owns must be serializable.
    $closureRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RegisteredRoute $route): bool => str_starts_with($route->uri(), 'api/'))
        ->filter(fn (RegisteredRoute $route): bool => $route->getActionName() === 'Closure')
        ->map(fn (RegisteredRoute $route): string => $route->uri())
        ->values();

    expect($closureRoutes->all())->toBe([]);
});
