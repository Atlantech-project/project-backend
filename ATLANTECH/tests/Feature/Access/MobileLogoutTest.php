<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\PersonalAccessToken;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserDevice;
use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\PortSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
 * P1-C4 mobile logout (CT-04, FR-AC-08): the Bearer token is revoked, never deleted, and refused everywhere afterwards; the other
 * devices keep their tokens. The web logout is tested in PasswordChangeTest (P1-C2). Every test is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    PortSetting::factory()->create(['setting_key' => 'min_app_version', 'setting_value' => '1.0.0', 'value_type' => PortSettingValueType::String]);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

/**
 * Sign in on a tablet through the endpoint and return the plain-text token.
 */
function logoutTestToken(User $user, string $deviceId): string
{
    app('auth')->forgetGuards();

    return test()->flushHeaders()->withHeaders(['X-App-Version' => '1.0.0', 'X-Device-Id' => $deviceId])
        ->postJson('/api/v1/auth/mobile/login', ['username' => $user->username, 'password' => 'password', 'platform' => 'IOS'])
        ->assertOk()
        ->json('token');
}

function logoutTestRequest(string $token, string $deviceId, string $method, string $uri, ?string $idempotencyKey = null): TestResponse
{
    app('auth')->forgetGuards();

    return test()->flushHeaders()
        ->withHeaders(['Authorization' => 'Bearer '.$token, 'X-App-Version' => '1.0.0', 'X-Device-Id' => $deviceId, 'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid()])
        ->json($method, $uri);
}

it('revokes the token on logout, keeps the row, and refuses the token everywhere afterwards', function () {
    $user = makeUserWithRole(Role::PortGm, ['username' => 'pilot.k']);
    $token = logoutTestToken($user, 'tablet-a');
    $deviceBefore = UserDevice::query()->sole()->getAttributes();

    logoutTestRequest($token, 'tablet-a', 'POST', '/api/v1/auth/logout')->assertNoContent();

    expect(PersonalAccessToken::query()->sole()->revoked_at)->not->toBeNull()
        ->and(UserDevice::query()->sole()->getAttributes())->toBe($deviceBefore);

    foreach ([['GET', '/api/v1/auth/me'], ['GET', '/api/v1/audit-logs'], ['POST', '/api/v1/auth/logout']] as [$method, $uri]) {
        logoutTestRequest($token, 'tablet-a', $method, $uri)->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }
});

it('leaves the user signed in on the other devices', function () {
    $user = makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);
    $first = logoutTestToken($user, 'tablet-a');
    $second = logoutTestToken($user, 'tablet-b');

    logoutTestRequest($first, 'tablet-a', 'POST', '/api/v1/auth/logout')->assertNoContent();

    logoutTestRequest($second, 'tablet-b', 'GET', '/api/v1/auth/me')->assertOk();
});

it('lets a user who must change the password log out with the token', function () {
    $token = logoutTestToken(makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k', 'must_change_password' => true]), 'tablet-a');

    logoutTestRequest($token, 'tablet-a', 'POST', '/api/v1/auth/logout')->assertNoContent();

    expect(PersonalAccessToken::query()->sole()->revoked_at)->not->toBeNull();
});

it('replays 204 for a retried logout with the same key, although the token is revoked now (IDEMP-01)', function () {
    $token = logoutTestToken(makeUserWithRole(Role::PortGm, ['username' => 'pilot.k']), 'tablet-a');
    $key = (string) Str::uuid();

    logoutTestRequest($token, 'tablet-a', 'POST', '/api/v1/auth/logout', $key)->assertNoContent()->assertHeaderMissing('Idempotent-Replayed');
    logoutTestRequest($token, 'tablet-a', 'POST', '/api/v1/auth/logout', $key)->assertNoContent()->assertHeader('Idempotent-Replayed', 'true');

    logoutTestRequest($token, 'tablet-a', 'POST', '/api/v1/auth/logout', (string) Str::uuid())->assertUnauthorized();
    logoutTestRequest($token, 'tablet-a', 'GET', '/api/v1/auth/me', $key)->assertUnauthorized();
});

it('does not answer a stolen revoked token with the key of somebody else', function () {
    $first = logoutTestToken(makeUserWithRole(Role::PortGm, ['username' => 'pilot.k']), 'tablet-a');
    $second = logoutTestToken(makeUserWithRole(Role::PortGm, ['username' => 'pilot.l']), 'tablet-b');
    $key = (string) Str::uuid();

    logoutTestRequest($first, 'tablet-a', 'POST', '/api/v1/auth/logout', $key)->assertNoContent();

    logoutTestRequest($second, 'tablet-b', 'POST', '/api/v1/auth/logout', $key)->assertNoContent()->assertHeaderMissing('Idempotent-Replayed');
    expect(PersonalAccessToken::query()->whereNull('revoked_at')->count())->toBe(0);
});

it('refuses a logout without a key with 422 and leaves the token alive', function () {
    $token = logoutTestToken(makeUserWithRole(Role::PortGm, ['username' => 'pilot.k']), 'tablet-a');

    app('auth')->forgetGuards();
    test()->flushHeaders()
        ->withHeaders(['Authorization' => 'Bearer '.$token, 'X-App-Version' => '1.0.0', 'X-Device-Id' => 'tablet-a'])
        ->postJson('/api/v1/auth/logout')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors('Idempotency-Key');

    expect(PersonalAccessToken::query()->sole()->revoked_at)->toBeNull();
});

it('refuses a logout retry that reuses the key for another request, before authentication', function () {
    $token = logoutTestToken(makeUserWithRole(Role::PortGm, ['username' => 'pilot.k']), 'tablet-a');
    $key = (string) Str::uuid();

    logoutTestRequest($token, 'tablet-a', 'POST', '/api/v1/auth/logout', $key)->assertNoContent();

    app('auth')->forgetGuards();
    test()->flushHeaders()
        ->withHeaders(['Authorization' => 'Bearer '.$token, 'X-App-Version' => '1.0.0', 'X-Device-Id' => 'tablet-a', 'Idempotency-Key' => $key])
        ->postJson('/api/v1/auth/logout', ['other' => 'body'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
});
