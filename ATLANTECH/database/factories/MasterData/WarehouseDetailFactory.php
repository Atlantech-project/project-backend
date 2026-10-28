<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\WarehouseDetail;
use App\Modules\Shared\Enums\StorageDetailKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseDetail>
 */
class WarehouseDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'storage_facility_id' => fake()->numberBetween(1, 1_000_000),
            'detail_kind' => StorageDetailKind::Warehouse,
            'min_temperature' => null,
            'max_temperature' => null,
            'temperature_monitoring' => false,
        ];
    }
}
