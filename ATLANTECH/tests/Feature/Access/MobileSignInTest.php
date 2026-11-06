<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\PersonalAccessToken;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserDevice;
use App\Modules\Access\Services\SignInService;
use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\PortSetting;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * P1-C3 POST /api/v1/auth/mobile/login (CT-04 v1.1, FR-AC-05, FR-AC-06). The tablet sends no Origin, so Sanctum treats it as
 * stateless (no session, no CSRF). MySQL schema database; every test is rolled back.
 */

const MOBILE_DEVICE_ID = 'tablet-7f3a';

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
 * @param  array<string, mixed>  $body
 * @param  array<string, string|null>  $headers  Null removes a default header.
 */
function mobileSignIn(array $body, array $headers = []): TestResponse
{
    $headers = array_filter([...['X-App-Version' => '1.2.0', 'X-Device-Id' => MOBILE_DEVICE_ID], ...$headers], fn ($value) => $value !== null);
    app('auth')->forgetGuards();

    return test()->flushHeaders()->withHeaders($headers)->postJson('/api/v1/auth/mobile/login', $body);
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function mobileCredentials(array $body = []): array
{
    return ['username' => 'pilot.k', 'password' => 'password', 'platform' => 'ANDROID', ...$body];
}

function mobileRejectedAttempts(): Collection
{
    return AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->get();
}

it('signs a tablet in: token, its expiry, the profile, the device row and the token row', function () {
    $this->freezeSecond();
    $user = makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k', 'name' => 'Marine Pilot', 'must_change_password' => true]);

    $response = mobileSignIn(mobileCredentials())
        ->assertOk()
        ->assertJsonPath('expires_at', now()->addDays(30)->toJSON())
        ->assertJsonPath('user', ['id' => $user->id, 'name' => 'Marine Pilot', 'role' => 'MARINE_PILOT', 'terminal_ids' => [], 'must_change_password' => true]);

    expect(array_keys($response->json()))->toBe(['token', 'expires_at', 'user'])
        ->and($response->json('token'))->toMatch('/^\d+\|\S{40,}$/');

    $device = UserDevice::query()->sole();
    $token = PersonalAccessToken::query()->sole();

    expect($device->only('user_id', 'device_id', 'app_version'))->toBe(['user_id' => $user->id, 'device_id' => MOBILE_DEVICE_ID, 'app_version' => '1.2.0'])
        ->and($device->platform->value)->toBe('ANDROID')
        ->and($device->last_seen_at->equalTo(now()))->toBeTrue()
        ->and($token->name)->toBe(MOBILE_DEVICE_ID)
        ->and($token->user_device_id)->toBe($device->id)
        ->and($token->tokenable_id)->toBe($user->id)
        ->and($token->revoked_at)->toBeNull()
        ->and(mobileRejectedAttempts())->toBeEmpty();
});

it('answers 422 for a missing or malformed device header', function (array $headers, string $field) {
    makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);

    mobileSignIn(mobileCredentials(), $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['errors' => [$field]])
        ->assertJsonPath('detail', fn (string $detail) => str_contains($detail, "The {$field} field"));

    expect(PersonalAccessToken::query()->count())->toBe(0);
})->with([
    'no X-App-Version' => [['X-App-Version' => null], 'X-App-Version'],
    'no X-Device-Id' => [['X-Device-Id' => null], 'X-Device-Id'],
    'version not x.y.z' => [['X-App-Version' => '1.2'], 'X-App-Version'],
    'version not a number' => [['X-App-Version' => 'latest'], 'X-App-Version'],
    'device id too long' => [['X-Device-Id' => str_repeat('d', 101)], 'X-Device-Id'],
]);

it('answers 426 with min_app_version and no token, before the body and the password are checked', function (array $body) {
    makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);

    mobileSignIn($body, ['X-App-Version' => '1.1.9'])
        ->assertStatus(426)
        ->assertJsonPath('code', 'APP_UPDATE_REQUIRED')
        ->assertJsonPath('min_app_version', '1.2.0');

    expect(PersonalAccessToken::query()->count())->toBe(0)
        ->and(UserDevice::query()->count())->toBe(0)
        ->and(mobileRejectedAttempts())->toBeEmpty();
})->with([
    'right password' => [mobileCredentials()],
    'wrong password' => [mobileCredentials(['password' => 'wrong-password'])],
    'outdated body without platform' => [['username' => 'pilot.k', 'password' => 'password']],
]);

it('answers 503 SERVICE_UNAVAILABLE while the port has no min_app_version', function () {
    PortSetting::query()->where('setting_key', 'min_app_version')->delete();
    makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);

    mobileSignIn(mobileCredentials())->assertStatus(503)->assertJsonPath('code', 'SERVICE_UNAVAILABLE');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('compares versions as numbers, not as text', function () {
    PortSetting::query()->where('setting_key', 'min_app_version')->update(['setting_value' => '1.9.0']);
    makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);

    mobileSignIn(mobileCredentials(), ['X-App-Version' => '1.10.0'])->assertOk();
});

it('answers 422 for a missing or unknown platform', function (array $body) {
    makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);

    mobileSignIn($body)->assertStatus(422)->assertJsonStructure(['errors' => ['platform']]);
})->with([
    'no platform' => [['username' => 'pilot.k', 'password' => 'password']],
    'unknown platform' => [mobileCredentials(['platform' => 'WINDOWS'])],
]);

it('answers 401 and 403 ACCOUNT_DISABLED as the web sign-in, audited, with no token', function (array $userAttributes, array $body, int $status, string $code) {
    makeUserWithRole(Role::TugMaster, ['username' => 'pilot.k', ...$userAttributes]);

    mobileSignIn(mobileCredentials($body))->assertStatus($status)->assertJsonPath('code', $code);

    expect(mobileRejectedAttempts()->sole()->after_state)->toMatchArray(['route' => 'POST /api/v1/auth/mobile/login', 'status' => $status, 'code' => $code])
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and(UserDevice::query()->count())->toBe(0);
})->with([
    'wrong password' => [[], ['password' => 'wrong-password'], 401, 'UNAUTHENTICATED'],
    'deactivated' => [['is_active' => false], [], 403, 'ACCOUNT_DISABLED'],
]);

it('shares the sign-in throttle with the web: 429 after 5 failed attempts', function () {
    makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);

    foreach (range(1, SignInService::MAX_ATTEMPTS_PER_USERNAME) as $attempt) {
        mobileSignIn(mobileCredentials(['password' => 'wrong-password']))->assertStatus(401);
    }

    mobileSignIn(mobileCredentials())->assertStatus(429)->assertHeader('Retry-After');
});

it('refuses a revoked device with 403 DEVICE_REVOKED, audited, and issues no token', function () {
    $user = makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);
    $device = UserDevice::factory()->revoked()->for($user)->create(['device_id' => MOBILE_DEVICE_ID]);

    mobileSignIn(mobileCredentials())
        ->assertForbidden()
        ->assertJsonPath('code', 'DEVICE_REVOKED');

    $row = mobileRejectedAttempts()->sole();

    expect($row->actor_id)->toBe($user->id)
        ->and($row->entity_type)->toBe('user_devices')
        ->and($row->entity_id)->toBe($device->id)
        ->and($row->after_state)->toMatchArray(['status' => 403, 'code' => 'DEVICE_REVOKED', 'reason' => 'device_revoked'])
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and($device->fresh()->revoked_at)->not->toBeNull();
});

it('keeps one live token per device: a new sign-in revokes the old token and updates the device row', function () {
    makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);

    mobileSignIn(mobileCredentials())->assertOk();
    mobileSignIn(mobileCredentials(['platform' => 'IOS']), ['X-App-Version' => '1.3.0'])->assertOk();

    $tokens = PersonalAccessToken::query()->orderBy('id')->get();
    $device = UserDevice::query()->sole();

    expect($tokens)->toHaveCount(2)
        ->and($tokens[0]->revoked_at)->not->toBeNull()
        ->and($tokens[1]->revoked_at)->toBeNull()
        ->and($device->app_version)->toBe('1.3.0')
        ->and($device->platform->value)->toBe('IOS');
});

it('registers a second user on the same tablet as a separate device row', function () {
    makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);
    makeUserWithRole(Role::TugMaster, ['username' => 'tug.master']);

    mobileSignIn(mobileCredentials())->assertOk();
    mobileSignIn(mobileCredentials(['username' => 'tug.master']))->assertOk();

    expect(UserDevice::query()->where('device_id', MOBILE_DEVICE_ID)->count())->toBe(2)
        ->and(PersonalAccessToken::query()->whereNull('revoked_at')->count())->toBe(2);
});

it('lets an office role sign in on a tablet', function () {
    makeUserWithRole(Role::PortStaff, ['username' => 'pilot.k']);

    mobileSignIn(mobileCredentials())->assertOk()->assertJsonPath('user.role', 'PORT_STAFF');
});

it('never deletes a token', function () {
    makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);
    mobileSignIn(mobileCredentials())->assertOk();

    expect(fn () => PersonalAccessToken::query()->sole()->delete())->toThrow(LogicException::class);
});

it('does not touch the user row', function () {
    $user = makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.k']);
    $before = User::query()->find($user->id)->getAttributes();

    mobileSignIn(mobileCredentials())->assertOk();

    expect(User::query()->find($user->id)->getAttributes())->toBe($before);
});
