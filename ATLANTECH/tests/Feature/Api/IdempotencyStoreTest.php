<?php

use App\Modules\Shared\Support\IdempotencyStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/*
 * IDEMP-01 step 2: the keys, hashes, encryption and lifetime of the idempotency store. The cache store is `array` in tests
 * (phpunit.xml), which keeps entries and atomic locks in memory.
 */

const IDEMPOTENCY_TEST_KEY = '5d6f3b6e-8a4c-4f0e-9b1d-2c7a1e9f0a11';

it('keeps the first response under the token hash and never under the token', function () {
    $store = new IdempotencyStore;
    $token = '7|plain-text-token-value';
    $scope = IdempotencyStore::scopeOf($token);

    $store->put($scope, IDEMPOTENCY_TEST_KEY, 'hash-a', new Response('{"secret":"one-time"}', 201, ['Content-Type' => 'application/json']));

    expect($scope)->toBe(hash('sha256', $token))->not->toContain('plain-text-token-value')
        ->and($store->find($scope, IDEMPOTENCY_TEST_KEY))->toMatchArray([
            'request_hash' => 'hash-a',
            'status' => 201,
            'content_type' => 'application/json',
            'body' => '{"secret":"one-time"}',
        ]);

    $raw = Cache::get('idempotency:'.$scope.':'.IDEMPOTENCY_TEST_KEY);
    expect($raw)->toBeString()->not->toContain('one-time')->not->toContain('plain-text-token-value');
});

it('never finds an entry under another token or another key', function () {
    $store = new IdempotencyStore;
    $store->put(IdempotencyStore::scopeOf('7|first'), IDEMPOTENCY_TEST_KEY, 'hash-a', new Response('x', 200));

    expect($store->find(IdempotencyStore::scopeOf('8|second'), IDEMPOTENCY_TEST_KEY))->toBeNull()
        ->and($store->find(IdempotencyStore::scopeOf('7|first'), '11111111-1111-4111-8111-111111111111'))->toBeNull();
});

it('keeps an entry for 24 hours and no longer', function () {
    $store = new IdempotencyStore;
    $scope = IdempotencyStore::scopeOf('7|token');
    $store->put($scope, IDEMPOTENCY_TEST_KEY, 'hash-a', new Response('x', 200));

    $this->travel(24)->hours();
    $this->travel(-1)->seconds();
    expect($store->find($scope, IDEMPOTENCY_TEST_KEY))->not->toBeNull();

    $this->travel(2)->seconds();
    expect($store->find($scope, IDEMPOTENCY_TEST_KEY))->toBeNull();
});

it('treats a value it cannot decrypt as absent', function () {
    Cache::put('idempotency:s:'.IDEMPOTENCY_TEST_KEY, 'not an encrypted value');

    expect((new IdempotencyStore)->find('s', IDEMPOTENCY_TEST_KEY))->toBeNull();
});

it('hashes the method, the path with its query and the body', function () {
    $hash = fn (string $method, string $uri, string $body) => IdempotencyStore::requestHash(Request::create($uri, $method, content: $body));

    $base = $hash('POST', '/api/v1/auth/logout', '{"a":1}');

    expect($hash('POST', '/api/v1/auth/logout', '{"a":1}'))->toBe($base)
        ->and($hash('PUT', '/api/v1/auth/logout', '{"a":1}'))->not->toBe($base)
        ->and($hash('POST', '/api/v1/auth/me', '{"a":1}'))->not->toBe($base)
        ->and($hash('POST', '/api/v1/auth/logout?x=1', '{"a":1}'))->not->toBe($base)
        ->and($hash('POST', '/api/v1/auth/logout', '{"a":2}'))->not->toBe($base);
});

it('stores every non-5xx response except the ones that pass with time', function () {
    $stored = [200, 201, 204, 403, 404, 409, 422];
    $unstored = [401, 408, 423, 426, 429, 500, 503];

    foreach ($stored as $status) {
        expect(IdempotencyStore::isStorable(new Response('', $status)))->toBeTrue("status {$status}");
    }

    foreach ($unstored as $status) {
        expect(IdempotencyStore::isStorable(new Response('', $status)))->toBeFalse("status {$status}");
    }
});

it('knows the exempt route names', function () {
    expect(IdempotencyStore::isExemptRoute('sync.events.store'))->toBeTrue()
        ->and(IdempotencyStore::isExemptRoute('auth.logout'))->toBeFalse()
        ->and(IdempotencyStore::isExemptRoute(null))->toBeFalse();

    config(['idempotency.exempt_routes' => ['reports.*']]);

    expect(IdempotencyStore::isExemptRoute('reports.store'))->toBeTrue()
        ->and(IdempotencyStore::isExemptRoute('sync.events.store'))->toBeFalse();
});

it('accepts a UUID v4 in either case and refuses anything else', function () {
    $valid = [IDEMPOTENCY_TEST_KEY, '5D6F3B6E-8A4C-4F0E-9B1D-2C7A1E9F0A11'];
    $invalid = ['6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'not-a-uuid', '', null];

    foreach ([...$valid, ...$invalid] as $value) {
        $passes = Validator::make(['k' => $value], ['k' => IdempotencyStore::keyRules()])->passes();

        expect($passes)->toBe(in_array($value, $valid, true), (string) $value);
    }
});

it('hashes a multipart request by its fields, since PHP leaves the raw content empty', function () {
    $hash = fn (array $fields) => IdempotencyStore::requestHash(Request::create('/api/v1/x', 'POST', $fields, server: ['CONTENT_TYPE' => 'multipart/form-data']));

    expect($hash(['a' => '1', 'b' => ['x' => '2']]))->toBe($hash(['b' => ['x' => '2'], 'a' => '1']))
        ->and($hash(['a' => '1']))->not->toBe($hash(['a' => '2']))
        ->and($hash(['a' => '1']))->not->toBe($hash([]));
});

it('lets a second holder wait for the lock and then take it', function () {
    $store = new IdempotencyStore;
    $first = $store->lock('s', IDEMPOTENCY_TEST_KEY);

    expect($first->get())->toBeTrue()
        ->and($store->lock('s', IDEMPOTENCY_TEST_KEY)->get())->toBeFalse();

    $first->release();

    expect($store->lock('s', IDEMPOTENCY_TEST_KEY)->get())->toBeTrue();
});
