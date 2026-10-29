<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\Equipment;
use App\Modules\MasterData\Models\EquipmentCategory;
use App\Modules\MasterData\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'terminal_id' => Terminal::factory(),
            'equipment_category_id' => EquipmentCategory::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'maintenance_quantity' => 0,
            'blocked_quantity' => 0,
        ];
    }
}
