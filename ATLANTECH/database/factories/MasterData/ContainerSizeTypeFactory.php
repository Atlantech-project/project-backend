<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\ContainerSizeTypeGroup;
use App\Modules\MasterData\Models\ContainerSizeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerSizeType>
 */
class ContainerSizeTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify(str_repeat('#', 4)),
            'length_ft' => fake()->randomElement([10, 20, 40, 45]),
            'is_high_cube' => false,
            'type_group' => ContainerSizeTypeGroup::General,
            'teu_factor' => fake()->randomFloat(2, 1, 9),
        ];
    }
}
