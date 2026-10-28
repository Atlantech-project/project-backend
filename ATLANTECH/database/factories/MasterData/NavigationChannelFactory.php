<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\NavigationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NavigationChannel>
 */
class NavigationChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => strtoupper(Str::random(10)),
            'depth' => fake()->randomFloat(2, 1, 9),
            'depth_datum' => fake()->lexify(str_repeat('?', 10)),
            'width' => fake()->randomFloat(2, 1, 9),
            'speed_limit_knots' => fake()->randomFloat(1, 1, 9),
            'is_active' => true,
        ];
    }
}
