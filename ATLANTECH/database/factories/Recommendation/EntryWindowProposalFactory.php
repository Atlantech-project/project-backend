<?php

namespace Database\Factories\Recommendation;

use App\Modules\Berthing\Models\BerthingRequest;
use App\Modules\Environment\Models\ForecastRun;
use App\Modules\Environment\Models\TidePredictionRun;
use App\Modules\Recommendation\Enums\EntryWindowProposalStatus;
use App\Modules\Recommendation\Models\EntryWindowProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryWindowProposal>
 */
class EntryWindowProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'berthing_request_id' => BerthingRequest::factory(),
            'forecast_run_id' => ForecastRun::factory(),
            'tide_prediction_run_id' => TidePredictionRun::factory(),
            'bias_correction_version_id' => null,
            'peak_congestion_score' => null,
            'status' => EntryWindowProposalStatus::Proposed,
            'generated_at' => now(),
            'confirmed_window_id' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'rejection_reason' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ];
    }
}
