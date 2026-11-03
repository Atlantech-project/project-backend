<?php

use App\Modules\Access\Http\Middleware\EnsureAbility;
use App\Modules\Access\Http\Middleware\EnsureAccountUsable;
use App\Modules\Access\Http\Middleware\EnsureMinAppVersion;
use App\Modules\Access\Http\Middleware\EnsureTerminalScope;
use App\Modules\License\Http\Middleware\EnsureLicenseAllowsWrite;
use App\Modules\Shared\Errors\ProblemRenderer;
use App\Modules\Shared\Http\Middleware\EnsureRecordVersion;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        /*
         * The write chain of FR-GL-01 (P2-D2), in this order whatever order a route lists its middleware in:
         * auth 401 · password gate 403 · ability 403 · terminal.scope 403 · app.version 426 · license.write 423 ·
         * (route binding) · version.token 409. Business rules (409 or 422) and plan limits (422) follow inside the
         * endpoint. Route binding runs after the scope checks so an unauthorised caller never gets a 404.
         * Signed-in routes use the `port.api` group and add `ability:<name>`, `terminal.scope`, `version.token`.
         */
        $middleware->group('port.api', [
            'auth:web',
            EnsureAccountUsable::class,
            EnsureMinAppVersion::class,
            EnsureLicenseAllowsWrite::class,
        ]);
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesSessions::class,
            EnsureAccountUsable::class,
            EnsureAbility::class,
            EnsureTerminalScope::class,
            EnsureMinAppVersion::class,
            EnsureLicenseAllowsWrite::class,
            SubstituteBindings::class,
            EnsureRecordVersion::class,
            Authorize::class,
        ]);
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : route('login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(
            fn (Throwable $exception, Request $request) => app(ProblemRenderer::class)->render($exception, $request),
        );
    })->create();
