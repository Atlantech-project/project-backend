<?php

namespace Database\Factories\Yard;

use App\Modules\Shared\Enums\TerminalType;
use App\Modules\Yard\Enums\TerminalRecommendationDecision;
use App\Modules\Yard\Enums\TerminalRecommendationType;
use App\Modules\Yard\Models\TerminalRecommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TerminalRecommendation>
 */
class TerminalRecommendationFactory extends Factory
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
            'terminal_type' => TerminalType::Container,
            'rec_type' => TerminalRecommendationType::YardPlacement,
            'window_start' => null,
            'window_end' => null,
            'decision' => TerminalRecommendationDecision::Pending,
            'decided_by' => null,
            'decided_at' => null,
            'decision_note' => null,
            'lifecycle_status' => null,
            'failure_reason' => null,
            'exported_at' => null,
            'exported_by' => null,
            'rationale_template_id' => null,
            'confidence' => null,
            'rationale_params' => null,
        ];
    }
}
