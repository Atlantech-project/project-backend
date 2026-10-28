<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\CapabilityType;
use App\Modules\MasterData\Models\Equipment;
use App\Modules\MasterData\Models\EquipmentCapability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentCapability>
 */
class EquipmentCapabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'capability_type_id' => CapabilityType::factory(),
            'capability_value' => fake()->randomFloat(2, 1, 9),
        ];
    }
}
