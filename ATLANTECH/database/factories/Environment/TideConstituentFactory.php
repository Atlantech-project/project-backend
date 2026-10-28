<?php

namespace Database\Factories\Environment;

use App\Modules\Environment\Models\TideConstituent;
use App\Modules\Environment\Models\TideConstituentSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TideConstituent>
 */
class TideConstituentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'constituent_set_id' => TideConstituentSet::factory(),
            'constituent_code' => fake()->lexify(str_repeat('?', 10)),
            'amplitude' => fake()->randomFloat(2, 1, 9),
            'phase_lag' => fake()->randomFloat(2, 1, 9),
            'speed_deg_per_hour' => fake()->randomFloat(2, 1, 9),
        ];
    }
}
