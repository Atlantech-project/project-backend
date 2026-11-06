<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserDevice;
use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\PortSetting;
use App\Modules\Shared\Support\IdempotencyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * IDEMP-01 (CT-01 section 4): the idempotency rule for every write made with a mobile Bearer token. The probe routes below sit in the
 * `port.api` group and count how many times their body really ran, so "the write does not run again" is proven by the counter.
 * Every test is rolled back.
 */

const IDEM_KEY = '5d6f3b6e-8a4c-4f0e-9b1d-2c7a1e9f0a11';

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    PortSetting::factory()->create(['setting_key' => 'min_app_version', 'setting_value' => '1.0.0', 'value_type' => PortSettingValueType::String]);
    $GLOBALS['idem_runs'] = 0;

    $probe = fn (): JsonResponse => response()->json(['run' => ++$GLOBALS['idem_runs']]);

    Route::middleware(['api', 'port.api'])->prefix('api/v1')->group(function () use ($probe) {
        Route::post('test/idem', $probe);
        Route::put('test/idem', $probe);
        Route::delete('test/idem', $probe);
        Route::get('test/idem', $probe);
        Route::post('test/idem-status/{status}', fn (int $status): JsonResponse => response()->json(['run' => ++$GLOBALS['idem_runs']], $status));
        Route::post('test/idem-boom', function (): never {
            throw new RuntimeException('boom');
        });
        Route::post('test/idem-exempt', $probe)->name('sync.events.store');
    });

    Route::middleware(['api', 'port.api', 'ability:users.manage'])->prefix('api/v1')->post('test/idem-ability', $probe);

    // The steps listed the wrong way round: the priority list must still order them.
    Route::middleware(['api', 'ability:users.manage', 'idempotency.key', 'port.api', 'idempotency'])->prefix('api/v1')->post('test/idem-reversed', $probe);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

/**
 * Sign in on a tablet through the endpoint and return the plain-text token.
 */
function idemToken(User $user, string $deviceId = 'tablet-a'): string
{
    app('auth')->forgetGuards();

    return test()->flushHeaders()->withHeaders(['X-App-Version' => '1.0.0', 'X-Device-Id' => $deviceId])
        ->postJson('/api/v1/auth/mobile/login', ['username' => $user->username, 'password' => 'password', 'platform' => 'IOS'])
        ->assertOk()
        ->json('token');
}

/**
 * A Bearer request; `$key` null sends no key, a string sends it as is.
 *
 * @param  array<string, mixed>  $body
 */
function idemRequest(string $token, string $method, string $uri, ?string $key, array $body = [], string $deviceId = 'tablet-a'): TestResponse
{
    app('auth')->forgetGuards();

    $headers = ['Authorization' => 'Bearer '.$token, 'X-App-Version' => '1.0.0', 'X-Device-Id' => $deviceId];

    if ($key !== null) {
        $headers['Idempotency-Key'] = $key;
    }

    return test()->flushHeaders()->withHeaders($headers)->json($method, $uri, $body);
}

it('refuses a Bearer write without a well-formed key with 422 on the Idempotency-Key field', function (?string $key) {
    $token = idemToken(makeUserWithRole(Role::PortGm));

    idemRequest($token, 'POST', '/api/v1/test/idem', $key)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors('Idempotency-Key');

    expect($GLOBALS['idem_runs'])->toBe(0);
})->with([[null], ['not-a-uuid'], ['6ba7b810-9dad-11d1-80b4-00c04fd430c8']]);

it('answers 401 before 422 when the token is not valid and the key is missing', function () {
    idemRequest('999|not-a-real-token', 'POST', '/api/v1/test/idem', null)
        ->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('asks for the key after authentication and before the password gate and the ability check', function (string $path) {
    $locked = idemToken(makeUserWithRole(Role::PortGm, ['must_change_password' => true]), 'tablet-a');
    $staff = idemToken(makeUserWithRole(Role::PortStaff), 'tablet-b');

    idemRequest($locked, 'POST', '/api/v1/test/idem', null)->assertUnprocessable();
    idemRequest($locked, 'POST', '/api/v1/test/idem', IDEM_KEY)->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

    idemRequest($staff, 'POST', $path, null, deviceId: 'tablet-b')->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
    idemRequest($staff, 'POST', $path, IDEM_KEY, deviceId: 'tablet-b')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
})->with(['/api/v1/test/idem-ability', '/api/v1/test/idem-reversed']);

it('runs the first request and replays status, body and content type for the same key and request', function (string $method) {
    $token = idemToken(makeUserWithRole(Role::PortGm));

    $first = idemRequest($token, $method, '/api/v1/test/idem', IDEM_KEY, ['a' => 1])->assertOk()->assertJson(['run' => 1]);
    $second = idemRequest($token, $method, '/api/v1/test/idem', strtoupper(IDEM_KEY), ['a' => 1])->assertOk()->assertJson(['run' => 1]);

    expect($first->headers->has('Idempotent-Replayed'))->toBeFalse()
        ->and($second->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and($second->headers->get('Content-Type'))->toBe($first->headers->get('Content-Type'))
        ->and($GLOBALS['idem_runs'])->toBe(1);
})->with(['POST', 'PUT', 'DELETE']);

it('refuses the same key with another body, path or method', function () {
    $token = idemToken(makeUserWithRole(Role::PortGm));
    idemRequest($token, 'POST', '/api/v1/test/idem', IDEM_KEY, ['a' => 1])->assertOk();

    foreach ([
        ['POST', '/api/v1/test/idem', ['a' => 2]],
        ['POST', '/api/v1/test/idem?x=1', ['a' => 1]],
        ['POST', '/api/v1/test/idem-status/201', ['a' => 1]],
        ['PUT', '/api/v1/test/idem', ['a' => 1]],
    ] as [$method, $uri, $body]) {
        idemRequest($token, $method, $uri, IDEM_KEY, $body)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
    }

    expect($GLOBALS['idem_runs'])->toBe(1);
});

it('never replays a key to another token, device or user', function () {
    $user = makeUserWithRole(Role::PortGm);
    $other = makeUserWithRole(Role::PortGm);
    $first = idemToken($user, 'tablet-a');
    $sameUserOtherDevice = idemToken($user, 'tablet-b');
    $otherUser = idemToken($other, 'tablet-c');

    idemRequest($first, 'POST', '/api/v1/test/idem', IDEM_KEY)->assertOk()->assertJson(['run' => 1]);
    idemRequest($sameUserOtherDevice, 'POST', '/api/v1/test/idem', IDEM_KEY, deviceId: 'tablet-b')->assertOk()->assertJson(['run' => 2]);
    idemRequest($otherUser, 'POST', '/api/v1/test/idem', IDEM_KEY, deviceId: 'tablet-c')->assertOk()->assertJson(['run' => 3]);
});

it('does not replay to a token that was never signed in, even with a stored key', function () {
    $token = idemToken(makeUserWithRole(Role::PortGm));
    idemRequest($token, 'POST', '/api/v1/test/idem', IDEM_KEY)->assertOk();

    idemRequest('999|guess', 'POST', '/api/v1/test/idem', IDEM_KEY)->assertUnauthorized();
});

it('keeps the response of the deterministic refusals and runs the write again after the transient ones', function (int $status, bool $replayed) {
    $token = idemToken(makeUserWithRole(Role::PortGm));
    $uri = '/api/v1/test/idem-status/'.$status;

    idemRequest($token, 'POST', $uri, IDEM_KEY)->assertStatus($status);
    idemRequest($token, 'POST', $uri, IDEM_KEY)->assertStatus($status);

    expect($GLOBALS['idem_runs'])->toBe($replayed ? 1 : 2);
})->with([
    [201, true], [403, true], [404, true], [409, true], [422, true],
    [408, false], [423, false], [426, false], [429, false], [500, false], [503, false],
]);

it('does not store a 401 and releases the lock when the request fails', function () {
    $token = idemToken(makeUserWithRole(Role::PortGm));

    idemRequest($token, 'POST', '/api/v1/test/idem-boom', IDEM_KEY)->assertStatus(500);
    idemRequest($token, 'POST', '/api/v1/test/idem-boom', IDEM_KEY)->assertStatus(500);

    $store = new IdempotencyStore;
    $scope = IdempotencyStore::scopeOf($token);
    $lock = $store->lock($scope, IDEM_KEY);

    expect($store->find($scope, IDEM_KEY))->toBeNull()
        ->and($lock->get())->toBeTrue();
});

it('leaves web session requests alone, with or without the header', function () {
    $user = makeUserWithRole(Role::PortGm);
    $headers = ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];

    $this->actingAs($user, 'web')->withHeaders($headers)->postJson('/api/v1/test/idem')->assertOk()->assertJson(['run' => 1]);
    $this->actingAs($user, 'web')->withHeaders($headers + ['Idempotency-Key' => IDEM_KEY])->postJson('/api/v1/test/idem')->assertOk()->assertJson(['run' => 2]);
    $second = $this->actingAs($user, 'web')->withHeaders($headers + ['Idempotency-Key' => IDEM_KEY])->postJson('/api/v1/test/idem')->assertOk()->assertJson(['run' => 3]);

    expect($second->headers->has('Idempotent-Replayed'))->toBeFalse();
});

it('does not apply to reads', function () {
    $token = idemToken(makeUserWithRole(Role::PortGm));

    idemRequest($token, 'GET', '/api/v1/test/idem', null)->assertOk()->assertJson(['run' => 1]);
    idemRequest($token, 'GET', '/api/v1/test/idem', IDEM_KEY)->assertOk()->assertJson(['run' => 2]);
    idemRequest($token, 'GET', '/api/v1/test/idem', IDEM_KEY)->assertOk()->assertJson(['run' => 3]);
});

it('exempts the route named sync.events.store: no key needed, nothing replayed', function () {
    $token = idemToken(makeUserWithRole(Role::PortGm));

    idemRequest($token, 'POST', '/api/v1/test/idem-exempt', null)->assertOk()->assertJson(['run' => 1]);
    idemRequest($token, 'POST', '/api/v1/test/idem-exempt', IDEM_KEY)->assertOk()->assertJson(['run' => 2]);
    idemRequest($token, 'POST', '/api/v1/test/idem-exempt', IDEM_KEY)->assertOk()->assertJson(['run' => 3]);
});

it('takes the exemption from the configured list', function () {
    config(['idempotency.exempt_routes' => []]);
    $token = idemToken(makeUserWithRole(Role::PortGm));

    idemRequest($token, 'POST', '/api/v1/test/idem-exempt', null)->assertUnprocessable();

    config(['idempotency.exempt_routes' => ['sync.*']]);

    idemRequest($token, 'POST', '/api/v1/test/idem-exempt', null)->assertOk();
});

it('does not require a key on mobile sign-in, which is outside port.api', function () {
    $user = makeUserWithRole(Role::PortGm);

    idemToken($user, 'tablet-a');
    idemToken($user, 'tablet-a');
});

it('does not replay to a token after its device was revoked, only the self-revoking logout is answered early', function () {
    $token = idemToken(makeUserWithRole(Role::PortGm));

    idemRequest($token, 'POST', '/api/v1/test/idem', IDEM_KEY)->assertOk();
    idemRequest($token, 'POST', '/api/v1/test/idem', IDEM_KEY)->assertOk()->assertHeader('Idempotent-Replayed', 'true');

    UserDevice::query()->update(['revoked_at' => now()]);

    idemRequest($token, 'POST', '/api/v1/test/idem', IDEM_KEY)->assertUnauthorized()->assertHeaderMissing('Idempotent-Replayed');
    expect($GLOBALS['idem_runs'])->toBe(1);
});

it('does not replay to another device that holds the token', function () {
    $token = idemToken(makeUserWithRole(Role::PortGm), 'tablet-a');
    idemRequest($token, 'POST', '/api/v1/test/idem', IDEM_KEY)->assertOk();

    idemRequest($token, 'POST', '/api/v1/test/idem', IDEM_KEY, deviceId: 'tablet-z')->assertUnauthorized();
});

it('does not replay to a caller who may no longer make the write: deactivated, password change required, ability lost', function () {
    $user = makeUserWithRole(Role::PortGm);
    $token = idemToken($user);
    idemRequest($token, 'POST', '/api/v1/test/idem-ability', IDEM_KEY)->assertOk();
    idemRequest($token, 'POST', '/api/v1/test/idem-ability', IDEM_KEY)->assertOk()->assertHeader('Idempotent-Replayed', 'true');

    $user->forceFill(['must_change_password' => true])->save();
    idemRequest($token, 'POST', '/api/v1/test/idem-ability', IDEM_KEY)->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

    $user->forceFill(['must_change_password' => false])->save();
    $user->activeRoleAssignment()->update(['active_to' => now()->addMinute()]);
    idemRequest($token, 'POST', '/api/v1/test/idem-ability', IDEM_KEY)->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

    $user->forceFill(['is_active' => false])->save();
    idemRequest($token, 'POST', '/api/v1/test/idem-ability', IDEM_KEY)->assertForbidden()->assertJsonPath('code', 'ACCOUNT_DISABLED');

    expect($GLOBALS['idem_runs'])->toBe(1);
});
