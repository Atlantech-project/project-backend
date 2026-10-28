<?php

namespace Database\Factories\Execution;

use App\Modules\Access\Models\User;
use App\Modules\Execution\Enums\ActualStatusUpdateSyncSource;
use App\Modules\Execution\Models\ActualStatusUpdate;
use App\Modules\Shared\Enums\ComponentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActualStatusUpdate>
 */
class ActualStatusUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_event_id' => fake()->uuid(),
            'plan_component_id' => fake()->numberBetween(1, 1_000_000),
            'component_type' => ComponentType::Berth,
            'reported_by' => User::factory(),
            'status' => fake()->numberBetween(1, 1_000_000),
            'reported_at' => now(),
            'client_sent_at' => null,
            'received_at' => now(),
            'clock_skew_suspect' => false,
            'sync_source' => ActualStatusUpdateSyncSource::Mobile,
        ];
    }
}
