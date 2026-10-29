<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\Container;
use App\Modules\MasterData\Models\ContainerSizeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Container>
 */
class ContainerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'container_number' => fake()->unique()->numerify('MSCU#######'),
            'size_type_id' => ContainerSizeType::factory(),
        ];
    }
}
