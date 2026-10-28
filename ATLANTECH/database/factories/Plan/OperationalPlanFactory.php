<?php

namespace Database\Factories\Plan;

use App\Modules\Access\Models\User;
use App\Modules\Plan\Enums\OperationalPlanStatus;
use App\Modules\Plan\Models\OperationalPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalPlan>
 */
class OperationalPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'berthing_request_id' => fake()->numberBetween(1, 1_000_000),
            'recommendation_id' => fake()->numberBetween(1, 1_000_000),
            'recommendation_option_id' => fake()->numberBetween(1, 1_000_000),
            'status' => OperationalPlanStatus::Confirmed,
            'confirmed_by' => User::factory(),
            'confirmed_at' => now(),
        ];
    }
}
