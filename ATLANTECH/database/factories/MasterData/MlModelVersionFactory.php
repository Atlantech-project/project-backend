<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\MlModelVersionStatus;
use App\Modules\MasterData\Models\MlModel;
use App\Modules\MasterData\Models\MlModelVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MlModelVersion>
 */
class MlModelVersionFactory extends Factory
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
            'version_no' => fake()->numberBetween(1, 10),
            'status' => MlModelVersionStatus::Challenger,
            'hyperparameters' => null,
            'training_window_start' => null,
            'training_window_end' => null,
            'training_row_count' => null,
            'artifact_path' => fake()->lexify(str_repeat('?', 10)),
            'trained_at' => now(),
            'deployed_at' => null,
            'retired_at' => null,
        ];
    }
}
