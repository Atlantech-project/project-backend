<?php

namespace Database\Seeders;

use App\Modules\MasterData\Models\CapabilityType;
use App\Modules\MasterData\Models\CargoType;
use App\Modules\MasterData\Models\ContainerSizeType;
use App\Modules\MasterData\Models\EquipmentCategory;
use App\Modules\MasterData\Models\ExternalSystem;
use App\Modules\MasterData\Models\ImdgClass;
use App\Modules\MasterData\Models\StatusType;
use App\Modules\MasterData\Models\StorageType;
use App\Modules\MasterData\Models\VesselType;
use App\Modules\Yard\Models\YardZoneType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads the Backend sections of `reference-seed.json` (CT-18): each row is upserted by its code, so a second run
 * adds nothing and a changed name or unit follows the file. `port_settings` has its own seeder.
 */
class ReferenceDataSeeder extends Seeder
{
    /**
     * Section name in the file, model, and the columns that identify a row. Parents come before children.
     *
     * @var array<string, array{class-string<Model>, list<string>}>
     */
    private const SECTIONS = [
        'imdg_classes' => [ImdgClass::class, ['code']],
        'container_size_types' => [ContainerSizeType::class, ['code']],
        'vessel_types' => [VesselType::class, ['code']],
        'cargo_types' => [CargoType::class, ['code']],
        'yard_zone_types' => [YardZoneType::class, ['code']],
        'storage_types' => [StorageType::class, ['code']],
        'capability_types' => [CapabilityType::class, ['code']],
        'equipment_categories' => [EquipmentCategory::class, ['code']],
        'status_types' => [StatusType::class, ['component_type', 'code']],
        'external_systems' => [ExternalSystem::class, ['code']],
    ];

    public function run(): void
    {
        /** @var array<string, list<array<string, mixed>>> $spec */
        $spec = json_decode((string) file_get_contents(database_path('seeders/data/reference-seed.json')), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($spec): void {
            foreach (self::SECTIONS as $section => [$model, $keys]) {
                $columns = array_keys($spec[$section][0]);

                $model::query()->upsert($spec[$section], $keys, array_values(array_diff($columns, $keys)));
            }
        });
    }
}
