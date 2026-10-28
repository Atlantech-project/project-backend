<?php

namespace Database\Factories\Recommendation;

use App\Modules\Recommendation\Enums\RecommendationPurpose;
use App\Modules\Recommendation\Enums\RecommendationSource;
use App\Modules\Recommendation\Enums\RecommendationStatus;
use App\Modules\Recommendation\Models\Recommendation;
use App\Modules\Recommendation\Models\SolverRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solver_run_id' => SolverRun::factory(),
            'berthing_request_id' => fake()->numberBetween(1, 1_000_000),
            'terminal_id' => fake()->numberBetween(1, 1_000_000),
            'entry_window_proposal_id' => fake()->numberBetween(1, 1_000_000),
            'status' => RecommendationStatus::PendingReview,
            'source' => RecommendationSource::Ai,
            'purpose' => RecommendationPurpose::Initial,
            'generated_at' => now(),
        ];
    }
}
