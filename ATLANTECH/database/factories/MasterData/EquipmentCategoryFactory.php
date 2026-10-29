<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\EquipmentRole;
use App\Modules\MasterData\Models\EquipmentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EquipmentCategory>
 */
class EquipmentCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(10)),
            'name' => fake()->lexify(str_repeat('?', 10)),
            'equipment_role' => EquipmentRole::VesselHandling,
        ];
    }
}
