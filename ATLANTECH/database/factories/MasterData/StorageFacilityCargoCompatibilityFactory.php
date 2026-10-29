<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\StorageFacilityCargoCompatibility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageFacilityCargoCompatibility>
 */
class StorageFacilityCargoCompatibilityFactory extends Factory
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
            'terminal_id' => fake()->numberBetween(1, 1_000_000),
            'cargo_type_id' => fake()->numberBetween(1, 1_000_000),
        ];
    }
}
