<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\PortProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortProfile>
 */
class PortProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'public_id' => fake()->uuid(),
            'port_code' => null,
            'governorate_code' => fake()->lexify(str_repeat('?', 6)),
            'name' => fake()->lexify(str_repeat('?', 10)),
            'latitude' => fake()->randomFloat(2, 1, 9),
            'longitude' => fake()->randomFloat(2, 1, 9),
            'timezone' => fake()->lexify(str_repeat('?', 10)),
            'boundary_coordinates' => null,
        ];
    }
}
