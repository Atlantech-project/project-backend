<?php

namespace Database\Factories\Recommendation;

use App\Modules\Recommendation\Enums\DemandForecastSlotMethod;
use App\Modules\Recommendation\Models\DemandForecastSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemandForecastSlot>
 */
class DemandForecastSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generated_at' => now(),
            'slot_start' => now(),
            'slot_end' => now()->addHour(),
            'expected_arrivals' => fake()->numberBetween(1, 10),
            'congestion_score' => fake()->randomFloat(2, 0, 1),
            'method' => DemandForecastSlotMethod::Aggregation,
            'model_version_id' => null,
        ];
    }
}
