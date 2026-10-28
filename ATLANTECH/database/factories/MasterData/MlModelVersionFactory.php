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
     * Define the model's default state. `ml_models` holds one row per `model_key` (reference data),
     * so versions reuse an existing model and only create one when there is none.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ml_model_id' => fn () => MlModel::query()->value('id') ?? MlModel::factory(),
            'version_no' => fake()->unique()->numberBetween(1, 1_000_000),
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
