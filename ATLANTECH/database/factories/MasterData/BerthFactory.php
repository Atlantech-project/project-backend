<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\FacilityStatus;
use App\Modules\MasterData\Models\Berth;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Berth>
 */
class BerthFactory extends Factory
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
            'name' => null,
            'length' => fake()->randomFloat(2, 300, 400),
            'water_depth' => fake()->randomFloat(2, 1, 9),
            'depth_datum' => fake()->lexify(str_repeat('?', 10)),
            'max_vessel_loa' => fake()->randomFloat(2, 150, 290),
            'status' => FacilityStatus::Active,
            'latitude' => null,
            'longitude' => null,
        ];
    }
}
