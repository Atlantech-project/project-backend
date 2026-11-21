<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserDevice;
use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\PortSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * P2-E3 end to end with a real token from the mobile sign-in (P1-C3): a revoked device cannot act and cannot sign in; after a restore the
 * old token stays dead and a new sign-in works. Rolled back after each test.
 */

const E3_DEVICE = 'tablet-e3';

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    PortSetting::factory()->create(['setting_key' => 'min_app_version', 'setting_value' => '1.2.0', 'value_type' => PortSettingValueType::String]);
    $this->admin = makeUserWithRole(Role::PortGm);
    $this->pilot = makeUserWithRole(Role::MarinePilot, ['username' => 'pilot.e3']);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function e3SignIn(User $user): TestResponse
{
    app('auth')->forgetGuards();

    return test()->flushHeaders()->withHeaders(['X-App-Version' => '1.2.0', 'X-Device-Id' => E3_DEVICE])
        ->postJson('/api/v1/auth/mobile/login', ['username' => $user->username, 'password' => 'password', 'platform' => 'ANDROID']);
}

function e3Me(string $token): TestResponse
{
    app('auth')->forgetGuards();

    return test()->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$token, 'X-App-Version' => '1.2.0', 'X-Device-Id' => E3_DEVICE])->getJson('/api/v1/auth/me');
}

function e3AdminPost(User $admin, string $uri): TestResponse
{
    app('auth')->forgetGuards();
    config(['sanctum.stateful' => ['localhost:5173']]);

    return test()->flushHeaders()->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])->actingAs($admin, 'web')->postJson($uri);
}

it('makes a revoked device useless and a restored device usable again with a new token only', function () {
    $oldToken = e3SignIn($this->pilot)->assertOk()->json('token');
    e3Me($oldToken)->assertOk();
    $device = UserDevice::query()->where('user_id', $this->pilot->id)->sole();

    e3AdminPost($this->admin, "/api/v1/users/{$this->pilot->id}/devices/{$device->id}/revoke")->assertOk();

    e3Me($oldToken)->assertUnauthorized();
    e3SignIn($this->pilot)->assertForbidden()->assertJsonPath('code', 'DEVICE_REVOKED');

    e3AdminPost($this->admin, "/api/v1/users/{$this->pilot->id}/devices/{$device->id}/restore")->assertOk();

    e3Me($oldToken)->assertUnauthorized();
    $newToken = e3SignIn($this->pilot)->assertOk()->json('token');

    expect($newToken)->not->toBe($oldToken);
    e3Me($newToken)->assertOk();
});

it('revoke-all ends every token of the user at once', function () {
    $token = e3SignIn($this->pilot)->assertOk()->json('token');

    e3AdminPost($this->admin, "/api/v1/users/{$this->pilot->id}/devices/revoke-all")->assertOk()->assertJsonPath('tokens_revoked', 1);

    e3Me($token)->assertUnauthorized();
});
