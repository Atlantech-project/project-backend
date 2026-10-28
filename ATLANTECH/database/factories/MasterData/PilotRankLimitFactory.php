<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\PilotRank;
use App\Modules\MasterData\Models\PilotRankLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PilotRankLimit>
 */
class PilotRankLimitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pilot_rank' => PilotRank::First,
            'max_vessel_loa' => null,
        ];
    }
}
