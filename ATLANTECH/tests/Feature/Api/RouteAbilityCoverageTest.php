<?php

use App\Modules\Access\Http\Middleware\EnsureAccountUsable;
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

it('runs the ability check after the password gate and the terminal check after the ability check', function () {
    foreach (apiRoutes() as $route) {
        $middleware = array_values($route->gatherMiddleware());
        $abilityAt = collect($middleware)->search(fn ($m) => is_string($m) && str_starts_with($m, 'ability:'));

        if ($abilityAt === false) {
            continue;
        }

        $gateAt = array_search(EnsureAccountUsable::class, $middleware, true);
        $terminalAt = array_search('terminal.scope', $middleware, true);

        expect($gateAt)->not->toBeFalse()->and($gateAt)->toBeLessThan($abilityAt);

        if ($terminalAt !== false) {
            expect($terminalAt)->toBeGreaterThan($abilityAt);
        }
    }
});
