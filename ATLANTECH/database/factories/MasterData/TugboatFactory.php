<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\TugboatStatus;
use App\Modules\MasterData\Enums\TugPowerUnit;
use App\Modules\MasterData\Models\Tugboat;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tugboat>
 */
class TugboatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => strtoupper(Str::random(10)),
            'tug_power' => fake()->randomFloat(2, 1, 9),
            'tug_power_unit' => TugPowerUnit::BollardPullTonnes,
            'capacity' => null,
            'status' => TugboatStatus::Available,
        ];
    }
}
