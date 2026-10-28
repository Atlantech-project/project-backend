<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\FacilityStatus;
use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\Shared\Enums\CapacityUnit;
use App\Modules\Shared\Enums\StorageDetailKind;
use App\Modules\Shared\Enums\TerminalType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageFacility>
 */
class StorageFacilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'terminal_id' => fake()->numberBetween(1, 1_000_000),
            'terminal_type' => TerminalType::NonContainer,
            'storage_type_id' => fake()->numberBetween(1, 1_000_000),
            'detail_kind' => StorageDetailKind::Warehouse,
            'name' => fake()->lexify(str_repeat('?', 10)),
            'code' => fake()->lexify(str_repeat('?', 10)),
            'area' => null,
            'max_floor_load' => null,
            'capacity' => 1000,
            'capacity_unit' => CapacityUnit::Tonnes,
            'current_occupancy' => '0.00',
            'reserved_capacity' => '0.00',
            'blocked_capacity' => '0.00',
            'status' => FacilityStatus::Active,
            'latitude' => null,
            'longitude' => null,
        ];
    }
}
