<?php

namespace Database\Factories\Yard;

use App\Modules\Yard\Enums\StorageMovementDirection;
use App\Modules\Yard\Enums\StorageMovementSource;
use App\Modules\Yard\Models\StorageMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageMovement>
 */
class StorageMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'storage_facility_id' => fake()->numberBetween(1, 1_000_000),
            'cargo_type_id' => fake()->numberBetween(1, 1_000_000),
            'direction' => StorageMovementDirection::In,
            'quantity' => fake()->randomFloat(2, 1, 9),
            'source' => StorageMovementSource::VesselDischarge,
            'plan_component_id' => null,
            'occurred_at' => now(),
            'recorded_by' => null,
        ];
    }
}
