<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\PortSetting;
use App\Modules\MasterData\Models\Terminal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
 * P2-F2 through a Bearer token (IDEMP-01): a berth write needs an `Idempotency-Key`, and a retry with the same key replays the
 * first answer instead of creating a second berth. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    PortSetting::factory()->create(['setting_key' => 'min_app_version', 'setting_value' => '1.2.0', 'value_type' => PortSettingValueType::String]);

    $admin = makeUserWithRole(Role::PortGm);
    app('auth')->forgetGuards();
    $this->token = $this->withHeaders(['X-App-Version' => '1.2.0', 'X-Device-Id' => 'tablet-f2'])
        ->postJson('/api/v1/auth/mobile/login', ['username' => $admin->username, 'password' => 'password', 'platform' => 'ANDROID'])
        ->assertOk()
        ->json('token');
    $this->terminal = Terminal::factory()->create();
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function bearerBerthBody(int $terminalId): array
{
    return ['code' => 'B-BEARER', 'length' => 300, 'water_depth' => 12, 'depth_datum' => 'LAT', 'max_vessel_loa' => 250, 'terminal_ids' => [$terminalId]];
}

function postBerthWithToken(string $token, array $body, ?string $key): TestResponse
{
    app('auth')->forgetGuards();

    return test()->flushHeaders()
        ->withHeaders(array_filter(['Authorization' => 'Bearer '.$token, 'X-App-Version' => '1.2.0', 'X-Device-Id' => 'tablet-f2', 'Idempotency-Key' => $key]))
        ->postJson('/api/v1/berths', $body);
}

it('replays the first answer for a retry with the same Idempotency-Key and creates one berth', function () {
    $key = (string) Str::uuid();

    $first = postBerthWithToken($this->token, bearerBerthBody($this->terminal->id), $key)->assertCreated();
    $retry = postBerthWithToken($this->token, bearerBerthBody($this->terminal->id), $key)->assertCreated();

    expect($retry->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and($retry->json('id'))->toBe($first->json('id'))
        ->and(Berth::query()->where('code', 'B-BEARER')->count())->toBe(1);
});

it('refuses a Bearer write without an Idempotency-Key with 422', function () {
    postBerthWithToken($this->token, bearerBerthBody($this->terminal->id), null)
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['Idempotency-Key']]);

    expect(Berth::query()->count())->toBe(0);
});
