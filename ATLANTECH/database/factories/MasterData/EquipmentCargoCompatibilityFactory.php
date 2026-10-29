<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\EquipmentCargoCompatibility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentCargoCompatibility>
 */
class EquipmentCargoCompatibilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_id' => fake()->numberBetween(1, 1_000_000),
            'terminal_id' => fake()->numberBetween(1, 1_000_000),
            'cargo_type_id' => fake()->numberBetween(1, 1_000_000),
        ];
    }
}
