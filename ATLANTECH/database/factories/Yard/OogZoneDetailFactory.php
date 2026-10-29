<?php

namespace Database\Factories\Yard;

use App\Modules\Yard\Enums\ZoneDetail;
use App\Modules\Yard\Models\OogZoneDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OogZoneDetail>
 */
class OogZoneDetailFactory extends Factory
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
            'zone_detail' => ZoneDetail::Oog,
            'max_length' => null,
            'max_width' => null,
            'max_height' => null,
            'max_weight' => null,
        ];
    }
}
