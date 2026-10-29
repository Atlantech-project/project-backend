<?php

namespace Database\Factories\Yard;

use App\Modules\Yard\Enums\ZoneDetail;
use App\Modules\Yard\Models\ReeferZoneDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReeferZoneDetail>
 */
class ReeferZoneDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'yard_zone_id' => fake()->numberBetween(1, 1_000_000),
            'zone_detail' => ZoneDetail::Reefer,
            'total_power_plugs' => fake()->numberBetween(1, 10),
            'power_type' => null,
        ];
    }
}
