<?php

namespace App\Modules\License\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write chain step "licence mode and port status" (FR-GL-01, alias `license.write`, group `port.api`): after the app
 * version step, before the record version. A write the current licence mode does not allow answers 423 LICENSE_READ_ONLY.
 *
 * Hook point only (P2-D2). P2-D5 fills the body (writes only; reads always pass; the route tags give the READ_ONLY and
 * SETUP allow-lists). Its position in the order is fixed by the priority list in `bootstrap/app.php`; do not move it.
 */
class EnsureLicenseAllowsWrite
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
