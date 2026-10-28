<?php

namespace Database\Factories\Recommendation;

use App\Modules\Recommendation\Enums\SolverRunStatus;
use App\Modules\Recommendation\Enums\SolverRunTriggerType;
use App\Modules\Recommendation\Models\SolverRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolverRun>
 */
class SolverRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trigger_type' => SolverRunTriggerType::NewRequest,
            'triggered_by' => null,
            'horizon_start' => now(),
            'horizon_end' => now()->addDays(3),
            'status' => SolverRunStatus::Queued,
            'objective_value' => null,
            'solve_time_ms' => null,
            'solver_version' => fake()->lexify(str_repeat('?', 10)),
            'result_detail' => null,
            'queued_at' => now(),
            'started_at' => null,
            'finished_at' => null,
            'result_handled_at' => null,
        ];
    }
}
