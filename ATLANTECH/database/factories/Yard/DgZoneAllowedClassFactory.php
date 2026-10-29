<?php

namespace Database\Factories\Yard;

use App\Modules\MasterData\Models\ImdgClass;
use App\Modules\Yard\Enums\ZoneDetail;
use App\Modules\Yard\Models\DgZoneAllowedClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DgZoneAllowedClass>
 */
class DgZoneAllowedClassFactory extends Factory
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
            'zone_detail' => ZoneDetail::DangerousGoods,
            'imdg_class' => ImdgClass::factory(),
        ];
    }
}
