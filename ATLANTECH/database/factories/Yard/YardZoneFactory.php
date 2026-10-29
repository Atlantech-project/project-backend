<?php

namespace Database\Factories\Yard;

use App\Modules\Shared\Enums\StorageDetailKind;
use App\Modules\Yard\Enums\ZoneDetail;
use App\Modules\Yard\Models\YardZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YardZone>
 */
class YardZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'container_yard_id' => fake()->numberBetween(1, 1_000_000),
            'detail_kind' => StorageDetailKind::ContainerYard,
            'yard_zone_type_id' => fake()->numberBetween(1, 1_000_000),
            'zone_detail' => ZoneDetail::None,
            'code' => fake()->lexify(str_repeat('?', 10)),
        ];
    }
}
