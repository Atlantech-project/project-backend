<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Support\IdempotencyStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write chain step "idempotency replay" (IDEMP-01, alias `idempotency.replay`), after the licence step and before route binding and
 * the version token: every check that depends on who the caller is now (authentication, device, password gate, account state, ability,
 * terminal scope, app version, licence) has passed, so a stored response is only given to a caller who may make this write today.
 * It must come before the version token, because the first write moved the record's version and the retry carries the old one.
 *
 * A stored response for the same token and key is replayed (`Idempotent-Replayed: true`) and the endpoint does not run; another
 * request under the same key is 422 IDEMPOTENCY_KEY_REUSED. Only requests that `RequireIdempotencyKey` marked as mobile writes are
 * looked up. (A write that revoked its own token, logout, is answered earlier by `IdempotentWrite`, which is the only way to reach it.)
 */
class ReplayIdempotentWrite
{
    public function __construct(private readonly IdempotencyStore $store) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws ApiProblemException 422 IDEMPOTENCY_KEY_REUSED.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->attributes->get(IdempotencyStore::KEY_ATTRIBUTE);

        if (! is_string($key)) {
            return $next($request);
        }

        $entry = $this->store->find(IdempotencyStore::scopeOf((string) $request->bearerToken()), $key);

        if ($entry === null) {
            $request->attributes->set(IdempotencyStore::STORABLE_ATTRIBUTE, true);

            return $next($request);
        }

        return $this->store->replayFor($entry, IdempotencyStore::requestHash($request));
    }
}
