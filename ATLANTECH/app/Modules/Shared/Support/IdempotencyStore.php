<?php

namespace App\Modules\Shared\Support;

use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
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
class IdempotencyStore
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
     * Covers the method, the path with its query string and the body: the raw content, or for a multipart request (PHP leaves the raw
     * content empty) the sorted fields and the name, size and SHA-256 of every uploaded file.
     */
    public static function requestHash(Request $request): string
    {
        $body = $request->getContent();

        if ($body === '') {
            $files = array_map(
                fn (UploadedFile $file): array => [$file->getClientOriginalName(), $file->getSize(), hash_file('sha256', $file->getPathname())],
                Arr::flatten($request->allFiles()),
            );
            $body = json_encode([Arr::sortRecursive($request->post()), $files], JSON_THROW_ON_ERROR);
        }

        return hash('sha256', implode("\n", [$request->method(), $request->getRequestUri(), $body]));
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
     * @return array{request_hash: string, status: int, content_type: ?string, body: string, preauth: bool}|null
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

        return is_array($entry) ? $entry + ['preauth' => false] : null;
    }

    /**
     * @param  bool  $preauth  The request revoked its own token (logout): a retry can only be answered before authentication.
     */
    public function put(string $scope, string $key, string $requestHash, Response $response, bool $preauth = false): void
    {
        $entry = json_encode([
            'request_hash' => $requestHash,
            'status' => $response->getStatusCode(),
            'content_type' => $response->headers->get('Content-Type'),
            'preauth' => $preauth,
            'body' => (string) $response->getContent(),
        ], JSON_THROW_ON_ERROR);

        $this->cache()->put($this->entryKey($scope, $key), Crypt::encryptString($entry), now()->addHours((int) config('idempotency.ttl_hours', 24)));
    }

    /**
     * The stored response again, marked `Idempotent-Replayed: true`.
     *
     * @param  array{request_hash: string, status: int, content_type: ?string, body: string, preauth: bool}  $entry
     *
     * @throws ApiProblemException 422 IDEMPOTENCY_KEY_REUSED when the stored request differs from this one.
     */
    public function replayFor(array $entry, string $requestHash): Response
    {
        if (! hash_equals($entry['request_hash'], $requestHash)) {
            throw new ApiProblemException(ErrorCode::IdempotencyKeyReused);
        }

        $response = new HttpResponse($entry['body'], $entry['status']);

        if ($entry['content_type'] !== null) {
            $response->headers->set('Content-Type', $entry['content_type']);
        }

        $response->headers->set(self::REPLAY_HEADER, 'true');

        return $response;
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
