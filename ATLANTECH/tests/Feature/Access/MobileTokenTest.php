<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\PersonalAccessToken;
use App\Modules\Access\Models\User;
use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\PortSetting;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * P1-C3: the mobile Bearer token on the signed-in routes (`port.api` with `auth:sanctum`): the device header, the 30-day slide,
 * expired, revoked and unknown tokens, the 426 check, the password gate and a deactivated user (CT-04, FR-AC-06, FR-AC-07, FR-AC-10).
 * MySQL schema database; every test is rolled back.
 */

const TOKEN_DEVICE_ID = 'tablet-b81c';

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    PortSetting::factory()->create(['setting_key' => 'min_app_version', 'setting_value' => '1.2.0', 'value_type' => PortSettingValueType::String]);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

/**
 * Sign in through the endpoint and return the plain-text token.
 */
function signInTablet(User $user, string $deviceId = TOKEN_DEVICE_ID): string
{
    app('auth')->forgetGuards();

    return test()->flushHeaders()->withHeaders(['X-App-Version' => '1.2.0', 'X-Device-Id' => $deviceId])
        ->postJson('/api/v1/auth/mobile/login', ['username' => $user->username, 'password' => 'password', 'platform' => 'ANDROID'])
        ->assertOk()
        ->json('token');
}

/**
 * A request with the Bearer token. Sanctum keeps the user it resolved, so the guards are forgotten before each request.
 *
 * @param  array<string, string|null>  $headers  Null removes a default header.
 * @param  array<string, mixed>  $body
 */
function withTabletToken(string $token, string $method, string $uri, array $headers = [], array $body = []): TestResponse
{
    $headers = array_filter(
        [...['Authorization' => 'Bearer '.$token, 'X-App-Version' => '1.2.0', 'X-Device-Id' => TOKEN_DEVICE_ID], ...$headers],
        fn ($value) => $value !== null,
    );
    app('auth')->forgetGuards();

    return test()->flushHeaders()->withHeaders($headers)->json($method, $uri, $body);
}

it('reads the profile with the Bearer token', function () {
    $user = makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);

    withTabletToken(signInTablet($user), 'GET', '/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('role', 'MARINE_PILOT');
});

it('answers 422 without X-Device-Id and 401 for another device, audited, without extending the token', function () {
    $this->freezeSecond();
    $user = makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);
    $token = signInTablet($user);
    $before = PersonalAccessToken::query()->sole()->only('last_used_at', 'expires_at');
    $this->travel(5)->days();

    withTabletToken($token, 'GET', '/api/v1/auth/me', ['X-Device-Id' => null])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['X-Device-Id']]);
    withTabletToken($token, 'GET', '/api/v1/auth/me', ['X-Device-Id' => 'another-tablet'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');

    $row = AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->sole();

    expect(PersonalAccessToken::query()->sole()->only('last_used_at', 'expires_at'))->toEqual($before)
        ->and($row->actor_id)->toBe($user->id)
        ->and($row->after_state)->toMatchArray(['route' => 'GET /api/v1/auth/me', 'status' => 401, 'code' => 'UNAUTHENTICATED', 'reason' => 'device_mismatch']);
});

it('moves the expiry to 30 days after each use', function () {
    $this->freezeSecond();
    $token = signInTablet(makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']));

    $this->travel(20)->days();
    withTabletToken($token, 'GET', '/api/v1/auth/me')->assertOk();

    $used = PersonalAccessToken::query()->sole();

    expect($used->expires_at->equalTo(now()->addDays(30)))->toBeTrue()
        ->and($used->last_used_at->equalTo(now()))->toBeTrue();

    $this->travel(25)->days();
    withTabletToken($token, 'GET', '/api/v1/auth/me')->assertOk();
});

it('answers a clean 401 for an expired, a revoked and an unknown token', function (Closure $spoil) {
    $token = signInTablet(makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']));

    $token = $spoil($token);

    withTabletToken($token, 'GET', '/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'UNAUTHENTICATED');
})->with([
    'expired after 30 days without use' => [function (string $token) {
        test()->travel(31)->days();

        return $token;
    }],
    'revoked' => [function (string $token) {
        PersonalAccessToken::query()->sole()->revoke();

        return $token;
    }],
    'unknown' => [fn () => '999999|'.str_repeat('x', 48)],
]);

it('answers 426 to a signed-in tablet whose app is below the minimum', function () {
    $token = signInTablet(makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']));
    PortSetting::query()->where('setting_key', 'min_app_version')->update(['setting_value' => '2.0.0']);

    withTabletToken($token, 'GET', '/api/v1/auth/me')
        ->assertStatus(426)
        ->assertJsonPath('min_app_version', '2.0.0');
});

it('applies the password gate to the token, and changes the password with it', function () {
    $user = makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k', 'must_change_password' => true]);
    $token = signInTablet($user);
    $otherDeviceToken = signInTablet($user, 'tablet-other');

    withTabletToken($token, 'GET', '/api/v1/audit-logs')
        ->assertForbidden()
        ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

    withTabletToken($token, 'POST', '/api/v1/auth/change-password', body: [
        'current_password' => 'password',
        'new_password' => 'NewPass2026',
        'new_password_confirmation' => 'NewPass2026',
    ])->assertOk()->assertJsonPath('must_change_password', false);

    expect($user->fresh()->must_change_password)->toBeFalse();

    withTabletToken($token, 'GET', '/api/v1/auth/me')->assertOk();
    withTabletToken($otherDeviceToken, 'GET', '/api/v1/auth/me', ['X-Device-Id' => 'tablet-other'])->assertOk();
});

it('refuses a wrong current password on the token route with 422', function () {
    $token = signInTablet(makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k', 'must_change_password' => true]));

    withTabletToken($token, 'POST', '/api/v1/auth/change-password', body: [
        'current_password' => 'wrong-password',
        'new_password' => 'NewPass2026',
        'new_password_confirmation' => 'NewPass2026',
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['current_password']]);
});

it('answers 403 ACCOUNT_DISABLED to a deactivated user and revokes the token', function () {
    $user = makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);
    $token = signInTablet($user);
    $user->forceFill(['is_active' => false])->save();

    withTabletToken($token, 'GET', '/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'ACCOUNT_DISABLED');

    expect(PersonalAccessToken::query()->sole()->revoked_at)->not->toBeNull();

    $user->forceFill(['is_active' => true])->save();

    withTabletToken($token, 'GET', '/api/v1/auth/me')->assertUnauthorized();
});
