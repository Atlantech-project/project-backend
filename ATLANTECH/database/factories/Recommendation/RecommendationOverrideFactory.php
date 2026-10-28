<?php

namespace Database\Factories\Recommendation;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\Berth;
use App\Modules\Recommendation\Enums\RecommendationOverrideComponentType;
use App\Modules\Recommendation\Models\RecommendationOption;
use App\Modules\Recommendation\Models\RecommendationOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationOverride>
 */
class RecommendationOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recommendation_option_id' => RecommendationOption::factory(),
            'component_type' => RecommendationOverrideComponentType::Berth,
            'original_berth_id' => Berth::factory(),
            'override_berth_id' => Berth::factory(),
            'original_pilot_user_id' => null,
            'override_pilot_user_id' => null,
            'original_tugboat_id' => null,
            'override_tugboat_id' => null,
            'original_start' => null,
            'override_start' => null,
            'override_reason' => fake()->lexify(str_repeat('?', 10)),
            'overridden_by' => User::factory(),
            'overridden_at' => now(),
        ];
    }
}
