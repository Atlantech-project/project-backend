<?php

namespace Database\Factories\Learning;

use App\Modules\Learning\Enums\EvaluationMetricDatasetType;
use App\Modules\Learning\Enums\EvaluationMetricName;
use App\Modules\Learning\Models\EvaluationMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationMetric>
 */
class EvaluationMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_version_id' => null,
            'retraining_run_id' => null,
            'metric_name' => EvaluationMetricName::OverrideRate,
            'dataset_type' => EvaluationMetricDatasetType::Holdout,
            'metric_value' => fake()->randomFloat(2, 1, 9),
            'period_start' => now()->subWeek(),
            'period_end' => now(),
            'computed_at' => now(),
        ];
    }
}
