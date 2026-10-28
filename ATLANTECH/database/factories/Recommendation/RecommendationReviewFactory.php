<?php

namespace Database\Factories\Recommendation;

use App\Modules\Access\Models\User;
use App\Modules\Recommendation\Models\Recommendation;
use App\Modules\Recommendation\Models\RecommendationReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationReview>
 */
class RecommendationReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recommendation_id' => Recommendation::factory(),
            'user_id' => User::factory(),
            'viewed_at' => now(),
        ];
    }
}
