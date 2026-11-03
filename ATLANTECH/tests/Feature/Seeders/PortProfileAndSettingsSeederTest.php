<?php

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\PortProfile;
use App\Modules\MasterData\Models\PortSetting;
use Database\Seeders\PortProfileSeeder;
use Database\Seeders\PortSettingsSeeder;
use Illuminate\Support\Facades\DB;

/*
 * The two rows the mobile sign-in chain needs: port_profiles id 1 and the min_app_version setting (P1-B6, CT-18).
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('creates the single port profile row', function () {
    $this->seed(PortProfileSeeder::class);

    $profile = PortProfile::query()->sole();

    expect($profile->id)->toBe(1)
        ->and($profile->public_id)->toBe(PortProfileSeeder::PUBLIC_ID)
        ->and($profile->port_code)->toBe('EGDAM')
        ->and($profile->name)->toBe('Damietta Port');
});

it('leaves an existing port profile alone', function () {
    PortProfile::factory()->create(['id' => 1, 'name' => 'From SaaS']);

    $this->seed(PortProfileSeeder::class);

    expect(PortProfile::query()->sole()->name)->toBe('From SaaS');
});

it('seeds min_app_version as a STRING and reminder_lead_minutes as an INT', function () {
    $this->seed(PortSettingsSeeder::class);

    $minVersion = PortSetting::query()->where('setting_key', 'min_app_version')->sole();
    $reminder = PortSetting::query()->where('setting_key', 'reminder_lead_minutes')->sole();

    expect($minVersion->setting_value)->toBe('1.0.0')
        ->and($minVersion->value_type->value)->toBe('STRING')
        ->and($reminder->setting_value)->toBe('120')
        ->and($reminder->value_type->value)->toBe('INT');
});

it('adds no rows when it runs twice', function () {
    $this->seed(PortSettingsSeeder::class);
    $this->seed(PortSettingsSeeder::class);

    expect(PortSetting::query()->count())->toBe(2);
});

it('never touches a value the Port GM changed, but refreshes the description', function () {
    $gm = User::factory()->create();
    PortSetting::factory()->create([
        'setting_key' => 'min_app_version',
        'setting_value' => '2.3.0',
        'value_type' => 'STRING',
        'description' => 'old',
        'updated_by' => $gm->id,
    ]);

    $this->seed(PortSettingsSeeder::class);

    $row = PortSetting::query()->where('setting_key', 'min_app_version')->sole();
    expect($row->setting_value)->toBe('2.3.0')
        ->and($row->description)->not->toBe('old');
});

it('moves an untouched value to the new default', function () {
    PortSetting::factory()->create([
        'setting_key' => 'reminder_lead_minutes',
        'setting_value' => '30',
        'value_type' => 'INT',
        'updated_by' => null,
    ]);

    $this->seed(PortSettingsSeeder::class);

    expect(PortSetting::query()->where('setting_key', 'reminder_lead_minutes')->sole()->setting_value)->toBe('120');
});
