<?php

namespace Database\Factories\Execution;

use App\Modules\Execution\Enums\DeviationType;
use App\Modules\Execution\Models\DeviationLog;
use App\Modules\Plan\Models\PlanComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviationLog>
 */
class DeviationLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_component_id' => PlanComponent::factory(),
            'status_update_id' => null,
            'deviation_type' => DeviationType::Delay,
            'planned_value' => fake()->lexify(str_repeat('?', 10)),
            'actual_value' => fake()->lexify(str_repeat('?', 10)),
            'delay_minutes' => null,
            'deviation_reason' => null,
            'detected_at' => now(),
        ];
    }
}
