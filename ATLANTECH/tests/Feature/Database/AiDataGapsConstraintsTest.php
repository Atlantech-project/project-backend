<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * CHECK constraints added for the AI data gaps G-09, G-10 and G-14 (DEC-28, DEC-32, CT-17 minor version).
 * Foreign-key checks are off so a test exercises only the rule it names; every test is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
});

function insertPortProfile(?string $portCode): void
{
    DB::table('port_profiles')->insert([
        'id' => 1, 'public_id' => '7d0f6b1e-5a1c-4e0a-9a53-0d1b7e3c2f10', 'port_code' => $portCode,
        'governorate_code' => 'DT', 'name' => 'Damietta Port', 'latitude' => 31.4333, 'longitude' => 31.7833, 'timezone' => 'Africa/Cairo',
    ]);
}

function insertOption(array $overrides = []): void
{
    DB::table('recommendation_options')->insert(array_merge([
        'recommendation_id' => 1, 'terminal_id' => 1, 'option_rank' => 1, 'berth_id' => 1,
        'planned_start' => '2026-11-10 06:00:00', 'planned_end' => '2026-11-10 18:00:00',
        'service_time_method' => 'FORMULA', 'service_time_p50_hours' => 10,
    ], $overrides));
}

test('the port code must be a five-character upper-case UN/LOCODE or empty', function (?string $code, bool $accepted) {
    if ($accepted) {
        insertPortProfile($code);
        expect(DB::table('port_profiles')->value('port_code'))->toBe($code);
    } else {
        expect(fn () => insertPortProfile($code))->toThrow(QueryException::class, 'chk_port_profile_code');
    }
})->with([
    'EGDAM' => ['EGDAM', true],
    'empty' => [null, true],
    'lower case' => ['egdam', false],
    'too long' => ['EGDAM01', false],
    'too short' => ['EGD', false],
])->group('schema');

test('a loading container count cannot be negative', function () {
    $insert = fn (int $count) => DB::table('berthing_requests')->insert([
        'vessel_id' => 1, 'requested_terminal_id' => 1, 'source' => 'MANUAL', 'submitted_by' => 1, 'requested_eta' => '2026-11-10 06:00:00',
        'arrival_draft' => 10, 'block_coefficient' => 0.7, 'gross_tonnage' => 1000, 'deadweight_tonnes' => 1000, 'loading_container_count' => $count,
    ]);

    expect($insert(0))->toBeTrue()
        ->and(fn () => $insert(-1))->toThrow(QueryException::class, 'chk_br_loading');
})->group('schema');

test('a pilot interval needs both ends, a pilot and a later end', function (array $values, bool $accepted) {
    if ($accepted) {
        insertOption($values);
        expect(DB::table('recommendation_options')->count())->toBe(1);
    } else {
        expect(fn () => insertOption($values))->toThrow(QueryException::class, 'chk_ro_pilot_interval');
    }
})->with([
    'no interval' => [[], true],
    'full interval with a pilot' => [['pilot_user_id' => 1, 'pilot_start' => '2026-11-10 07:00:00', 'pilot_end' => '2026-11-10 09:00:00'], true],
    'only a start' => [['pilot_user_id' => 1, 'pilot_start' => '2026-11-10 07:00:00'], false],
    'end before start' => [['pilot_user_id' => 1, 'pilot_start' => '2026-11-10 09:00:00', 'pilot_end' => '2026-11-10 07:00:00'], false],
    'interval without a pilot' => [['pilot_start' => '2026-11-10 07:00:00', 'pilot_end' => '2026-11-10 09:00:00'], false],
])->group('schema');

test('a tug interval needs both ends and a later end', function (array $values, bool $accepted) {
    $insert = fn () => DB::table('recommendation_option_tugs')->insert(array_merge(['option_id' => 1, 'tugboat_id' => 1], $values));

    if ($accepted) {
        $insert();
        expect(DB::table('recommendation_option_tugs')->count())->toBe(1);
    } else {
        expect($insert)->toThrow(QueryException::class, 'chk_rot_interval');
    }
})->with([
    'no interval' => [[], true],
    'full interval' => [['planned_start' => '2026-11-10 07:00:00', 'planned_end' => '2026-11-10 09:00:00'], true],
    'only an end' => [['planned_end' => '2026-11-10 09:00:00'], false],
    'end equals start' => [['planned_start' => '2026-11-10 07:00:00', 'planned_end' => '2026-11-10 07:00:00'], false],
])->group('schema');
