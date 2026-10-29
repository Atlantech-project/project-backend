<?php

namespace Database\Factories\Recommendation;

use App\Modules\MasterData\Models\MlModelVersion;
use App\Modules\Recommendation\Models\SolverRun;
use App\Modules\Recommendation\Models\SolverRunModelVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolverRunModelVersion>
 */
class SolverRunModelVersionFactory extends Factory
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
            'model_version_id' => MlModelVersion::factory(),
        ];
    }
}
