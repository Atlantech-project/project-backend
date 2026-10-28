<?php

namespace Database\Factories\Recommendation;

use App\Modules\Recommendation\Enums\RecommendationOptionServiceTimeMethod;
use App\Modules\Recommendation\Models\RecommendationOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationOption>
 */
class RecommendationOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recommendation_id' => fake()->numberBetween(1, 1_000_000),
            'terminal_id' => fake()->numberBetween(1, 1_000_000),
            'option_rank' => fake()->numberBetween(1, 10),
            'berth_id' => fake()->numberBetween(1, 1_000_000),
            'planned_start' => now(),
            'planned_end' => now()->addHours(12),
            'pilot_user_id' => null,
            'service_time_method' => RecommendationOptionServiceTimeMethod::Formula,
            'service_time_p50_hours' => fake()->randomFloat(2, 1, 9),
            'service_time_p90_hours' => null,
            'delay_risk' => null,
            'objective_score' => null,
            'rationale_template_id' => null,
            'rationale_params' => null,
        ];
    }
}
