<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Support\IdempotencyStore;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Write chain step "idempotency" (IDEMP-01, CT-01 section 4, alias `idempotency`), the first step of the `port.api` group: it runs
 * before `auth:sanctum` because a retried logout carries a token the first request revoked, and the replay has to be found before the
 * token is refused. The entry is found by the hash of that exact token plus the secret key, so finding it proves the caller sent both.
 *
 * It applies to a POST, PUT, PATCH or DELETE that carries a Bearer token, except routes in `config('idempotency.exempt_routes')`.
 * A missing or malformed key passes through here and is refused with 422 by `RequireIdempotencyKey` once the caller is authenticated.
 *
 *  - same key, same request (method, path and query, body): the stored response is replayed with `Idempotent-Replayed: true`;
 *  - same key, another request: 422 IDEMPOTENCY_KEY_REUSED;
 *  - otherwise the request runs under a lock on the key, so a concurrent twin waits and then replays (503 + Retry-After if the first
 *    one is still running after `lock_wait_seconds`), and its response is stored when `RequireIdempotencyKey` marked the caller as a
 *    mobile one and the status may be kept (never a 5xx, never 401 or a refusal that passes with time).
 */
class IdempotentWrite
{
    public function __construct(private readonly IdempotencyStore $store) {}

    /**
     * @param  Closure(Request): SymfonyResponse  $next
     *
     * @throws ApiProblemException 422 IDEMPOTENCY_KEY_REUSED, 503 SERVICE_UNAVAILABLE while the first request with this key still runs.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $key = self::keyOf($request);

        if ($key === null) {
            return $next($request);
        }

        $scope = IdempotencyStore::scopeOf((string) $request->bearerToken());
        $requestHash = IdempotencyStore::requestHash($request);

        if (($replay = $this->replay($scope, $key, $requestHash)) !== null) {
            return $replay;
        }

        $lock = $this->store->lock($scope, $key);

        try {
            $lock->block((int) config('idempotency.lock_wait_seconds', 5));
        } catch (LockTimeoutException) {
            throw new ApiProblemException(ErrorCode::ServiceUnavailable, headers: ['Retry-After' => '1']);
        }

        try {
            // The request that held the lock may have stored its response while this one waited.
            if (($replay = $this->replay($scope, $key, $requestHash)) !== null) {
                return $replay;
            }

            $response = $next($request);

            if ($request->attributes->get(IdempotencyStore::STORABLE_ATTRIBUTE) === true && IdempotencyStore::isStorable($response)) {
                $this->store->put($scope, $key, $requestHash, $response);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * The lower-case key when this request is a Bearer write that uses the rule and carries a well-formed key; null otherwise.
     */
    private static function keyOf(Request $request): ?string
    {
        if ($request->bearerToken() === null
            || ! IdempotencyStore::isWriteMethod($request->method())
            || IdempotencyStore::isExemptRoute($request->route()?->getName())) {
            return null;
        }

        $header = $request->header(IdempotencyStore::HEADER);

        if (! is_string($header) || Validator::make(['key' => $header], ['key' => IdempotencyStore::keyRules()])->fails()) {
            return null;
        }

        return strtolower($header);
    }

    /**
     * @throws ApiProblemException 422 IDEMPOTENCY_KEY_REUSED when the stored request differs from this one.
     */
    private function replay(string $scope, string $key, string $requestHash): ?SymfonyResponse
    {
        $entry = $this->store->find($scope, $key);

        if ($entry === null) {
            return null;
        }

        if (! hash_equals($entry['request_hash'], $requestHash)) {
            throw new ApiProblemException(ErrorCode::IdempotencyKeyReused);
        }

        $response = new Response($entry['body'], $entry['status']);

        if ($entry['content_type'] !== null) {
            $response->headers->set('Content-Type', $entry['content_type']);
        }

        $response->headers->set(IdempotencyStore::REPLAY_HEADER, 'true');

        return $response;
    }
}
