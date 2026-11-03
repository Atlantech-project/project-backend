<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/*
 * P2-D4: every PUT or PATCH route of the port API declares `version.token:<param>`, so no update endpoint can skip the
 * stale-write check (FR-GL-05). Routes that really have no record to version go on the allow-list, with the reason.
 */

it('declares version.token on every PUT and PATCH route of routes/api.php', function () {
    $allowList = [];

    $missing = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/v1/'))
        ->filter(fn (Route $route) => array_intersect($route->methods(), ['PUT', 'PATCH']) !== [])
        ->reject(fn (Route $route) => in_array($route->uri(), $allowList, true))
        ->reject(fn (Route $route) => collect($route->gatherMiddleware())->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'version.token:')))
        ->map(fn (Route $route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
