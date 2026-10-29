<?php

namespace Database\Factories\Yard;

use App\Modules\Shared\Enums\CapacityUnit;
use App\Modules\Yard\Enums\TerminalRecommendationType;
use App\Modules\Yard\Models\StorageAllocationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageAllocationItem>
 */
class StorageAllocationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'terminal_recommendation_id' => fake()->numberBetween(1, 1_000_000),
            'rec_type' => TerminalRecommendationType::StorageAllocation,
            'storage_facility_id' => fake()->numberBetween(1, 1_000_000),
            'cargo_type_id' => fake()->numberBetween(1, 1_000_000),
            'quantity' => fake()->randomFloat(2, 1, 9),
            'quantity_unit' => CapacityUnit::Tonnes,
        ];
    }
}
