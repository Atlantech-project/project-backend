<?php

namespace Database\Factories\Recommendation;

use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Recommendation\Models\RecommendationOption;
use App\Modules\Recommendation\Models\RecommendationOptionTug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationOptionTug>
 */
class RecommendationOptionTugFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'option_id' => RecommendationOption::factory(),
            'tugboat_id' => Tugboat::factory(),
        ];
    }
}
