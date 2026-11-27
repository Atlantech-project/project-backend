<?php

use App\Modules\MasterData\Enums\PilotRank;
use App\Modules\MasterData\Models\PilotRankLimit;
use Database\Seeders\PilotRankLimitSeeder;
use Illuminate\Support\Facades\DB;

/*
 * P2-F3: the three rank-limit rows exist with no limit, and a limit set later survives a re-seed.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    PilotRankLimit::query()->delete();
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('creates one row per rank with no limit', function () {
    $this->seed(PilotRankLimitSeeder::class);

    expect(PilotRankLimit::query()->orderBy('pilot_rank')->pluck('max_vessel_loa', 'pilot_rank')->all())
        ->toBe(['FIRST' => null, 'SECOND' => null, 'THIRD' => null]);
});

it('keeps a limit that was already set', function () {
    PilotRankLimit::query()->create(['pilot_rank' => PilotRank::Third, 'max_vessel_loa' => 150]);

    $this->seed(PilotRankLimitSeeder::class);

    expect(PilotRankLimit::query()->count())->toBe(3)
        ->and(PilotRankLimit::query()->find('THIRD')->max_vessel_loa)->toBe('150.00');
});
