<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write chain step "record version" (FR-GL-01, alias `version.token`, opt-in per route): after the licence step, before
 * the business rules inside the endpoint. A stale `version` answers 409 VERSION_CONFLICT with `current: { id, version }`.
 *
 * Hook point only (P2-D2). P2-D4 fills the body (the token is `updated_at`, CT-01 section 5). Its position in the order
 * is fixed by the priority list in `bootstrap/app.php`; do not move it.
 */
class EnsureRecordVersion
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
