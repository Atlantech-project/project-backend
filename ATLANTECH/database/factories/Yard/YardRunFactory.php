<?php

namespace Database\Factories\Yard;

use App\Modules\Shared\Enums\RunStatus;
use App\Modules\Yard\Models\TerminalRecommendation;
use App\Modules\Yard\Models\YardRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YardRun>
 */
class YardRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'terminal_recommendation_id' => TerminalRecommendation::factory(),
            'triggered_by' => null,
            'status' => RunStatus::Queued,
            'outcome' => null,
            'error_category' => null,
            'retry_count' => 0,
            'result_detail' => null,
            'worker_version' => null,
            'queued_at' => now(),
            'started_at' => null,
            'finished_at' => null,
            'result_handled_at' => null,
        ];
    }
}
