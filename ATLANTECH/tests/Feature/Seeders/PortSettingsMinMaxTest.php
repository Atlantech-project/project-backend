<?php

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\PortSetting;
use Database\Seeders\PortSettingsSeeder;
use Illuminate\Support\Facades\DB;

/*
 * DEC-59 Option 1: port_settings stores min_value and max_value (CT-18 v1.1).
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('has nullable min_value and max_value columns', function () {
    $columns = collect(DB::select('SHOW COLUMNS FROM port_settings'))->keyBy('Field');

    expect($columns['min_value']->Null)->toBe('YES')
        ->and($columns['max_value']->Null)->toBe('YES');
});

it('leaves min and max NULL for keys that have none', function () {
    $this->seed(PortSettingsSeeder::class);

    $row = PortSetting::query()->where('setting_key', 'min_app_version')->sole();

    expect($row->min_value)->toBeNull()->and($row->max_value)->toBeNull();
});

it('always refreshes min and max, even after the Port GM changed the value', function () {
    PortSetting::factory()->create([
        'setting_key' => 'reminder_lead_minutes',
        'setting_value' => '45',
        'value_type' => 'INT',
        'min_value' => 1,
        'max_value' => 2,
        'updated_by' => User::factory()->create()->id,
    ]);

    $this->seed(PortSettingsSeeder::class);

    $row = PortSetting::query()->where('setting_key', 'reminder_lead_minutes')->sole();

    expect($row->setting_value)->toBe('45')
        ->and($row->min_value)->toBeNull()
        ->and($row->max_value)->toBeNull();
});

it('stores a min and max from the spec when the row has them', function () {
    $spec = json_decode(file_get_contents(database_path('seeders/data/reference-seed.json')), true);
    $spec['port_settings'][0] += ['min' => 1, 'max' => 120];
    $path = tempnam(sys_get_temp_dir(), 'seed');
    file_put_contents($path, json_encode($spec));

    (new PortSettingsSeeder)->run($path);

    $row = PortSetting::query()->where('setting_key', $spec['port_settings'][0]['setting_key'])->sole();
    unlink($path);

    expect((float) $row->min_value)->toBe(1.0)->and((float) $row->max_value)->toBe(120.0);
});
