<?php

namespace Database\Factories\Environment;

use App\Modules\Environment\Models\TidePredictionPoint;
use App\Modules\Environment\Models\TidePredictionRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TidePredictionPoint>
 */
class TidePredictionPointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tide_prediction_run_id' => TidePredictionRun::factory(),
            'valid_at' => now(),
            'height' => fake()->randomFloat(2, 1, 9),
        ];
    }
}
