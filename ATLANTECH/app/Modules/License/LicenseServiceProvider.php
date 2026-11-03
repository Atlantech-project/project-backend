<?php

namespace App\Modules\License;

use App\Modules\License\Http\Middleware\EnsureLicenseAllowsWrite;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Licence: SETUP, FULL and READ_ONLY modes, the check-in client and plan limits (P2, P6).
 */
class LicenseServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $router->aliasMiddleware('license.write', EnsureLicenseAllowsWrite::class);
    }
}
