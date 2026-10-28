<?php

namespace Database\Factories\Recommendation;

use App\Modules\Berthing\Models\BerthingRequest;
use App\Modules\Recommendation\Models\Part1Run;
use App\Modules\Shared\Enums\RunStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part1Run>
 */
class Part1RunFactory extends Factory
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
            'status' => RunStatus::Queued,
            'outcome' => null,
            'retry_count' => 0,
            'entry_window_proposal_id' => null,
            'queued_at' => now(),
            'started_at' => null,
            'finished_at' => null,
            'result_handled_at' => null,
        ];
    }
}
