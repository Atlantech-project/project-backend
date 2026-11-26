<?php

namespace Database\Seeders;

use App\Modules\MasterData\Enums\PilotRank;
use App\Modules\MasterData\Models\PilotRankLimit;
use Illuminate\Database\Seeder;

/**
 * One `pilot_rank_limits` row per rank (Coding notes §7, P2-F3), with no limit (`max_vessel_loa` NULL). A row that
 * already exists is never changed, so a limit set by the Pilotage Manager survives a re-seed.
 */
class PilotRankLimitSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PilotRank::cases() as $rank) {
            PilotRankLimit::query()->firstOrCreate(['pilot_rank' => $rank], ['max_vessel_loa' => null]);
        }
    }
}
