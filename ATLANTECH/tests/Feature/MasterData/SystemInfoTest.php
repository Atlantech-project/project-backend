<?php

use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\PortProfile;
use App\Modules\MasterData\Models\PortSetting;
use Illuminate\Support\Facades\DB;

/*
 * P1-C5 GET /api/v1/system/info (CT-04 v1.1): public, never 426, the tablet's first call and its connectivity check (DEC-58).
 * MySQL schema database; every test is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function seedSystemInfoPort(?string $portCode = 'EGDAM'): PortProfile
{
    PortSetting::factory()->create(['setting_key' => 'min_app_version', 'setting_value' => '1.2.0', 'value_type' => PortSettingValueType::String]);

    return PortProfile::factory()->create(['public_id' => '0d9f6a52-7c1b-4b7e-9a3e-5a2f1c8d7e10', 'port_code' => $portCode, 'name' => 'Damietta Port']);
}

it('answers a guest with the port, the fixed licence fields and the minimum app version', function () {
    seedSystemInfoPort();

    $this->getJson('/api/v1/system/info')
        ->assertOk()
        ->assertExactJson([
            'port_id' => '0d9f6a52-7c1b-4b7e-9a3e-5a2f1c8d7e10',
            'port_code' => 'EGDAM',
            'port_name' => 'Damietta Port',
            'license_mode' => 'FULL',
            'token_warning_days_left' => null,
            'min_app_version' => '1.2.0',
        ]);
});

it('answers port_code null when the port has none', function () {
    seedSystemInfoPort(portCode: null);

    $this->getJson('/api/v1/system/info')->assertOk()->assertJsonPath('port_code', null);
});

it('is never refused with 426, whatever app version the tablet sends', function (string $appVersion) {
    seedSystemInfoPort();

    $this->withHeaders(['X-App-Version' => $appVersion, 'X-Device-Id' => 'tablet-1'])
        ->getJson('/api/v1/system/info')
        ->assertOk();
})->with(['older than the minimum' => '0.9.0', 'malformed' => 'not-a-version']);

it('answers 503 SERVICE_UNAVAILABLE while the port is not provisioned', function (Closure $seed) {
    $seed();

    $this->getJson('/api/v1/system/info')
        ->assertStatus(503)
        ->assertJsonPath('code', 'SERVICE_UNAVAILABLE');
})->with([
    'no profile row' => [fn () => PortSetting::factory()->create(['setting_key' => 'min_app_version', 'setting_value' => '1.0.0', 'value_type' => PortSettingValueType::String])],
    'no min_app_version setting' => [fn () => PortProfile::factory()->create()],
]);
