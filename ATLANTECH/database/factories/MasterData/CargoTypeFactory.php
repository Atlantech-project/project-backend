<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\CargoTypeCategory;
use App\Modules\MasterData\Models\CargoType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CargoType>
 */
class CargoTypeFactory extends Factory
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
            'category' => CargoTypeCategory::GeneralCargo,
            'imdg_class' => null,
            'min_temperature' => null,
            'max_temperature' => null,
        ];
    }
}
