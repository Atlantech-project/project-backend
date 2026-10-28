<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\VesselType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VesselType>
 */
class VesselTypeFactory extends Factory
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
        ];
    }
}
