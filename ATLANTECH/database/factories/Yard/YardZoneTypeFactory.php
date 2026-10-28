<?php

namespace Database\Factories\Yard;

use App\Modules\Yard\Enums\ZoneDetail;
use App\Modules\Yard\Models\YardZoneType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<YardZoneType>
 */
class YardZoneTypeFactory extends Factory
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
            'zone_detail' => ZoneDetail::None,
        ];
    }
}
