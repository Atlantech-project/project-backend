<?php

use App\Modules\Access\Enums\Ability;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/*
 * P2-D1 / NFR-SEC-2: a route in routes/api.php must declare `ability:<name>` or be on this allow-list, so a later endpoint
 * cannot be added unprotected. Adding a name here is a security decision: say why in the pull request.
 */

const ABILITY_FREE_ROUTES = [
    'auth.login',
    'auth.me',
    'auth.change-password',
    'auth.logout',
];

/**
 * @return list<Route>
 */
function apiRoutes(): array
{
    return array_values(array_filter(
        RouteFacade::getRoutes()->getRoutes(),
        fn (Route $route) => str_starts_with($route->uri(), 'api/v1/'),
    ));
}

it('has an ability middleware or an allow-list entry on every API route', function () {
    $unprotected = [];

    foreach (apiRoutes() as $route) {
        $declaresAbility = collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'ability:'));

        if (! $declaresAbility && ! in_array($route->getName(), ABILITY_FREE_ROUTES, true)) {
            $unprotected[] = implode('|', $route->methods()).' /'.$route->uri();
        }
    }

    expect($unprotected)->toBe([]);
});

it('keeps the allow-list free of names that no route has', function () {
    $names = array_map(fn (Route $route) => $route->getName(), apiRoutes());

    expect(array_diff(ABILITY_FREE_ROUTES, $names))->toBe([]);
});

it('names only abilities that exist, so a typo is caught here and not as a 500', function () {
    $unknown = [];

    foreach (apiRoutes() as $route) {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'ability:') && Ability::tryFrom(substr($middleware, 8)) === null) {
                $unknown[] = '/'.$route->uri().' '.$middleware;
            }
        }
    }

    expect($unknown)->toBe([]);
});

/*
 * P2-D2 owns the order: the priority list in bootstrap/app.php sorts auth, password gate, ability, terminal scope and the
 * later write-chain steps whatever order a route lists them in (proven in WriteChainOrderTest). What a route must not do
 * is skip the chain, so every route with an ability runs inside the `port.api` group.
 */
it('runs every route with an ability inside the port.api group, so it gets the whole write chain', function () {
    $outsideChain = [];

    foreach (apiRoutes() as $route) {
        $middleware = $route->gatherMiddleware();
        $declaresAbility = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'ability:'));

        if ($declaresAbility && ! in_array('port.api', $middleware, true)) {
            $outsideChain[] = '/'.$route->uri();
        }
    }

    expect($outsideChain)->toBe([]);
});

it('puts terminal.scope on every route that has a {terminal} parameter', function () {
    $unscoped = [];

    foreach (apiRoutes() as $route) {
        if (in_array('terminal', $route->parameterNames(), true) && ! in_array('terminal.scope', $route->gatherMiddleware(), true)) {
            $unscoped[] = '/'.$route->uri();
        }
    }

    expect($unscoped)->toBe([]);
});
