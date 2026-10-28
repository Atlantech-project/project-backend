<?php

namespace Database\Factories\Environment;

use App\Modules\Environment\Models\TideConstituentSet;
use App\Modules\Environment\Models\TidePredictionRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TidePredictionRun>
 */
class TidePredictionRunFactory extends Factory
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
            'computed_at' => now(),
            'horizon_start' => now(),
            'horizon_end' => now()->addDays(3),
        ];
    }
}
