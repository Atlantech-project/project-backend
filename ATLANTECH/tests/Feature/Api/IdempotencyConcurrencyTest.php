<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\PortSetting;
use App\Modules\Shared\Support\IdempotencyStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * IDEMP-01 hard case 2: two requests with the same key at the same time. The array cache store keeps atomic locks in memory;
 * "the first request is still running" is a lock the test holds, and "the first one finished while this one waited" is a store that
 * saves the first response at the moment the lock is taken.
 */

const IDEM_LOCK_KEY = '0f8b3c2a-6d1e-4a77-8c55-3b9d2e4f1a10';

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    PortSetting::factory()->create(['setting_key' => 'min_app_version', 'setting_value' => '1.0.0', 'value_type' => PortSettingValueType::String]);
    $GLOBALS['idem_runs'] = 0;

    Route::middleware(['api', 'port.api'])->prefix('api/v1')
        ->post('test/idem-lock', fn (): JsonResponse => response()->json(['run' => ++$GLOBALS['idem_runs']]));
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

/**
 * @return array{string, Lock}
 */
function idemLockedToken(): array
{
    app('auth')->forgetGuards();
    $user = makeUserWithRole(Role::PortGm);
    $token = test()->flushHeaders()->withHeaders(['X-App-Version' => '1.0.0', 'X-Device-Id' => 'tablet-a'])
        ->postJson('/api/v1/auth/mobile/login', ['username' => $user->username, 'password' => 'password', 'platform' => 'IOS'])
        ->json('token');

    return [$token, (new IdempotencyStore)->lock(IdempotencyStore::scopeOf($token), IDEM_LOCK_KEY)];
}

function idemLockRequest(string $token): TestResponse
{
    app('auth')->forgetGuards();

    return test()->flushHeaders()
        ->withHeaders(['Authorization' => 'Bearer '.$token, 'X-App-Version' => '1.0.0', 'X-Device-Id' => 'tablet-a', 'Idempotency-Key' => IDEM_LOCK_KEY])
        ->postJson('/api/v1/test/idem-lock');
}

it('answers 503 with Retry-After while the first request with the key still runs, and never runs the write', function () {
    config(['idempotency.lock_wait_seconds' => 0]);
    [$token, $lock] = idemLockedToken();
    expect($lock->get())->toBeTrue();

    idemLockRequest($token)
        ->assertStatus(503)
        ->assertJsonPath('code', 'SERVICE_UNAVAILABLE')
        ->assertHeader('Retry-After', '1');

    expect($GLOBALS['idem_runs'])->toBe(0);

    $lock->release();
    idemLockRequest($token)->assertOk()->assertJson(['run' => 1]);
});

it('replays the first response when it was stored while this request waited for the lock', function () {
    [$token] = idemLockedToken();

    // The winner finishes between this request's first look and its lock.
    app()->instance(IdempotencyStore::class, new class extends IdempotencyStore
    {
        public function lock(string $scope, string $key): Lock
        {
            $this->put($scope, $key, IdempotencyStore::requestHash(request()), new Response('{"run":99}', 200, ['Content-Type' => 'application/json']));

            return parent::lock($scope, $key);
        }
    });

    idemLockRequest($token)->assertOk()->assertJson(['run' => 99])->assertHeader('Idempotent-Replayed', 'true');

    expect($GLOBALS['idem_runs'])->toBe(0);
});

it('runs the write once for two sequential requests with the same key', function () {
    [$token] = idemLockedToken();

    idemLockRequest($token)->assertOk()->assertJson(['run' => 1]);
    idemLockRequest($token)->assertOk()->assertJson(['run' => 1])->assertHeader('Idempotent-Replayed', 'true');

    expect($GLOBALS['idem_runs'])->toBe(1);
});
