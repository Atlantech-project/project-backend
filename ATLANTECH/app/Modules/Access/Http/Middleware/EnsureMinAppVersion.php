<?php

namespace App\Modules\Access\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write chain step "minimum app version" (FR-GL-01, alias `app.version`, group `port.api`): after the role and terminal
 * checks, before the licence step. A mobile request below the port's `min_app_version` answers 426 APP_UPDATE_REQUIRED.
 *
 * Hook point only (P2-D2). P1-C3 fills the body: read `X-App-Version`, compare, throw `ApiProblemException::appUpdateRequired`.
 * Its position in the order is fixed by the priority list in `bootstrap/app.php`; do not move it.
 */
class EnsureMinAppVersion
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
