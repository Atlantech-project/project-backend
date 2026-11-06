<?php

namespace App\Modules\Shared\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storage and keys of the mobile write idempotency rule (IDEMP-01, CT-01 section 4).
 *
 * An entry is found by `sha256(Bearer token)` plus the client's key, so a key never replays to another token, and the token itself is
 * never stored. The value is encrypted with the application key: a response body may hold a one-time secret. Entries and locks live in
 * the cache store of `config('idempotency.store')`.
 */
final class IdempotencyStore
{
    public const string HEADER = 'Idempotency-Key';

    public const string REPLAY_HEADER = 'Idempotent-Replayed';

    /** Request attribute set once the caller is known to be a mobile Bearer caller: only then may the response be stored. */
    public const string STORABLE_ATTRIBUTE = 'idempotency.storable';

    /** Request attribute holding the validated, lower-case key. */
    public const string KEY_ATTRIBUTE = 'idempotency.key';

    /**
     * @return list<string>
     */
    public static function keyRules(): array
    {
        return ['required', 'string', 'uuid:4'];
    }

    public static function scopeOf(string $bearerToken): string
    {
        return hash('sha256', $bearerToken);
    }

    /**
     * Covers the method, the path with its query string and the raw body (JSON for the mobile app; an upload is not covered by it).
     */
    public static function requestHash(Request $request): string
    {
        return hash('sha256', implode("\n", [$request->method(), $request->getRequestUri(), $request->getContent()]));
    }

    public static function isWriteMethod(string $method): bool
    {
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public static function isExemptRoute(?string $routeName): bool
    {
        return $routeName !== null && Str::is(config('idempotency.exempt_routes', []), $routeName);
    }

    /**
     * Whether the response of a write that ran may be kept: not a 5xx and not a refusal that passes with time.
     */
    public static function isStorable(Response $response): bool
    {
        return $response->getStatusCode() < 500
            && ! in_array($response->getStatusCode(), config('idempotency.unstored_statuses', []), true);
    }

    /**
     * @return array{request_hash: string, status: int, content_type: ?string, body: string}|null
     */
    public function find(string $scope, string $key): ?array
    {
        $sealed = $this->cache()->get($this->entryKey($scope, $key));

        if (! is_string($sealed)) {
            return null;
        }

        try {
            $entry = json_decode(Crypt::decryptString($sealed), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        return is_array($entry) ? $entry : null;
    }

    public function put(string $scope, string $key, string $requestHash, Response $response): void
    {
        $entry = json_encode([
            'request_hash' => $requestHash,
            'status' => $response->getStatusCode(),
            'content_type' => $response->headers->get('Content-Type'),
            'body' => (string) $response->getContent(),
        ], JSON_THROW_ON_ERROR);

        $this->cache()->put($this->entryKey($scope, $key), Crypt::encryptString($entry), now()->addHours((int) config('idempotency.ttl_hours', 24)));
    }

    public function lock(string $scope, string $key): Lock
    {
        return $this->cache()->lock('idempotency-lock:'.$scope.':'.$key, (int) config('idempotency.lock_seconds', 30));
    }

    private function entryKey(string $scope, string $key): string
    {
        return 'idempotency:'.$scope.':'.$key;
    }

    private function cache(): Repository
    {
        return Cache::store(config('idempotency.store'));
    }
}
