<?php

namespace Database\Factories\Learning;

use App\Modules\Learning\Enums\DriftMetricType;
use App\Modules\Learning\Models\DriftMeasurement;
use App\Modules\MasterData\Models\MlModelVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriftMeasurement>
 */
class DriftMeasurementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_version_id' => MlModelVersion::factory(),
            'metric_type' => DriftMetricType::RollingMae,
            'feature_name' => null,
            'metric_value' => fake()->randomFloat(2, 1, 9),
            'threshold' => fake()->randomFloat(2, 1, 9),
            'measured_at' => now(),
            'notified_at' => null,
        ];
    }
}
