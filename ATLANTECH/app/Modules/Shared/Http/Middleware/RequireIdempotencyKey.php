<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Support\IdempotencyStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write chain step "idempotency key" (IDEMP-01, alias `idempotency.key`), right after `EnsureTokenDevice`: the caller is authenticated by
 * a mobile token, so a write without a UUID v4 `Idempotency-Key` is 422 VALIDATION_FAILED (a bad token was already 401, as in CT-01).
 * It also marks the request as storable for `IdempotentWrite`. A web session request, a read and an exempt route pass untouched.
 */
class RequireIdempotencyKey
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws ValidationException 422 VALIDATION_FAILED on the field `Idempotency-Key`.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usesRule = $request->user()?->currentAccessToken() instanceof PersonalAccessToken
            && IdempotencyStore::isWriteMethod($request->method())
            && ! IdempotencyStore::isExemptRoute($request->route()?->getName());

        if ($usesRule) {
            $name = IdempotencyStore::HEADER;

            Validator::make([$name => $request->header($name)], [$name => IdempotencyStore::keyRules()], [], [$name => $name])->validate();

            $request->attributes->set(IdempotencyStore::STORABLE_ATTRIBUTE, true);
        }

        return $next($request);
    }
}
