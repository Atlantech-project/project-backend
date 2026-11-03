<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Support\VersionToken;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write chain step "record version" (FR-GL-01, alias `version.token:<routeParam>`, opt-in per route): after the licence
 * step and route binding, before the business rules inside the endpoint. The JSON body must carry the `version` the
 * client last read; a stale value answers 409 VERSION_CONFLICT with `current: { id, version }` (CT-01 section 5).
 * The endpoint repeats the comparison on the locked row with `VersionToken::lockAndAssert` (P2-D4).
 *
 * Reads (GET, HEAD, OPTIONS) pass. The position in the order is fixed by the priority list in `bootstrap/app.php`.
 */
class EnsureRecordVersion
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  $parameter  Name of the route parameter bound to the record, e.g. `version.token:user`.
     *
     * @throws ValidationException 422 when `version` is missing or malformed.
     * @throws ApiProblemException 409 VERSION_CONFLICT.
     * @throws LogicException When the route parameter is not a bound model.
     */
    public function handle(Request $request, Closure $next, string $parameter): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $record = $request->route($parameter);

        if (! $record instanceof Model) {
            throw new LogicException("version.token:{$parameter} needs the route parameter {{$parameter}} bound to a model.");
        }

        $version = $request->validate(['version' => VersionToken::rules()])['version'];

        VersionToken::assertCurrent($record, $version);

        return $next($request);
    }
}
