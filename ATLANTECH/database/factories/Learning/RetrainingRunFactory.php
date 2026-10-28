<?php

namespace Database\Factories\Learning;

use App\Modules\Learning\Enums\RetrainingRunDecision;
use App\Modules\Learning\Models\RetrainingRun;
use App\Modules\MasterData\Models\MlModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetrainingRun>
 */
class RetrainingRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ml_model_id' => MlModel::factory(),
            'champion_version_id' => null,
            'challenger_version_id' => fake()->numberBetween(1, 1_000_000),
            'started_at' => now(),
            'finished_at' => null,
            'decision' => RetrainingRunDecision::Promoted,
            'decision_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ];
    }
}
