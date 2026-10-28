<?php

namespace Database\Factories\MasterData;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Enums\PilotRank;
use App\Modules\MasterData\Models\PilotProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PilotProfile>
 */
class PilotProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pilot_rank' => PilotRank::First,
        ];
    }
}
