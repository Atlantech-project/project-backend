<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\Vessel;
use App\Modules\MasterData\Models\VesselType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vessel>
 */
class VesselFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'imo_number' => fake()->unique()->numerify(str_repeat('#', 7)),
            'name' => fake()->lexify(str_repeat('?', 10)),
            'vessel_type_id' => VesselType::factory(),
            'loa' => fake()->randomFloat(2, 150, 400),
            'beam' => fake()->randomFloat(2, 20, 60),
            'max_draft' => fake()->randomFloat(2, 6, 16),
            'net_tonnage' => null,
            'moulded_depth' => null,
            'call_sign' => null,
            'flag_state' => null,
        ];
    }
}
