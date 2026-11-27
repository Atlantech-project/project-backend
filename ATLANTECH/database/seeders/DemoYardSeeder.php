<?php

namespace Database\Seeders;

use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\MasterData\Models\StorageType;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Enums\StorageDetailKind;
use App\Modules\Shared\Enums\TerminalType;
use App\Modules\Yard\Enums\YardSlotStatus;
use App\Modules\Yard\Enums\ZoneDetail;
use App\Modules\Yard\Models\DgZoneAllowedClass;
use App\Modules\Yard\Models\OogZoneDetail;
use App\Modules\Yard\Models\ReeferZoneDetail;
use App\Modules\Yard\Models\YardBlock;
use App\Modules\Yard\Models\YardSlot;
use App\Modules\Yard\Models\YardZone;
use App\Modules\Yard\Models\YardZoneType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A demo container yard for every container terminal of the demo port (P2-F4, DEC-29 G-03), read from
 * `database/seeders/data/damietta-demo-yard.json`. Invented layout: regular blocks with every slot ACTIVE. Records are
 * upserted by code and slots inserted only where missing, so a second run changes nothing. Needs the reference rows
 * (ReferenceDataSeeder) and the demo port (DemoPortSeeder).
 */
class DemoYardSeeder extends Seeder
{
    public function run(): void
    {
        DemoPortSeeder::ensureAllowed();

        $fixture = json_decode((string) file_get_contents(database_path('seeders/data/damietta-demo-yard.json')), true, flags: JSON_THROW_ON_ERROR);
        $storageTypeId = StorageType::query()->where('detail_kind', StorageDetailKind::ContainerYard)->orderBy('id')->firstOrFail()->id;
        $zoneTypes = YardZoneType::query()->get()->keyBy('code');

        DB::transaction(function () use ($fixture, $storageTypeId, $zoneTypes): void {
            $terminals = Terminal::query()
                ->whereIn('code', $fixture['terminal_codes'])
                ->where('terminal_type', TerminalType::Container)
                ->get();

            foreach ($terminals as $terminal) {
                $yard = StorageFacility::query()->updateOrCreate(
                    ['terminal_id' => $terminal->id, 'code' => $fixture['yard']['code']],
                    [
                        'name' => $fixture['yard']['name'],
                        'terminal_type' => TerminalType::Container,
                        'storage_type_id' => $storageTypeId,
                        'detail_kind' => StorageDetailKind::ContainerYard,
                        'status' => 'ACTIVE',
                    ],
                );

                foreach ($fixture['zones'] as $zoneRow) {
                    $this->seedZone($yard, $zoneTypes[$zoneRow['type_code']], $zoneRow);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function seedZone(StorageFacility $yard, YardZoneType $type, array $row): void
    {
        $zone = YardZone::query()->firstOrCreate(
            ['container_yard_id' => $yard->id, 'code' => $row['code']],
            ['detail_kind' => StorageDetailKind::ContainerYard, 'yard_zone_type_id' => $type->id, 'zone_detail' => $type->zone_detail],
        );
        $detail = $row['detail'] ?? [];

        match ($zone->zone_detail) {
            ZoneDetail::Reefer => ReeferZoneDetail::query()->updateOrCreate(['yard_zone_id' => $zone->id], ['zone_detail' => ZoneDetail::Reefer, ...$detail]),
            ZoneDetail::Oog => OogZoneDetail::query()->updateOrCreate(['yard_zone_id' => $zone->id], ['zone_detail' => ZoneDetail::Oog, ...$detail]),
            ZoneDetail::DangerousGoods => DgZoneAllowedClass::query()->insertOrIgnore(array_map(
                fn (string $class): array => ['yard_zone_id' => $zone->id, 'zone_detail' => ZoneDetail::DangerousGoods->value, 'imdg_class' => $class],
                $detail['imdg_classes'],
            )),
            ZoneDetail::None => null,
        };

        foreach ($row['blocks'] as $blockRow) {
            $block = YardBlock::query()->firstOrCreate(['yard_zone_id' => $zone->id, 'code' => $blockRow['code']]);
            $this->seedSlots($block, (int) $blockRow['bays'], (int) $blockRow['rows'], (int) $blockRow['tiers']);
        }
    }

    private function seedSlots(YardBlock $block, int $bays, int $rows, int $tiers): void
    {
        $slots = [];

        for ($bay = 1; $bay <= $bays; $bay++) {
            for ($row = 1; $row <= $rows; $row++) {
                for ($tier = 1; $tier <= $tiers; $tier++) {
                    $slots[] = ['yard_block_id' => $block->id, 'bay' => $bay, 'row_no' => $row, 'tier' => $tier, 'status' => YardSlotStatus::Active->value];
                }
            }
        }

        YardSlot::query()->insertOrIgnore($slots);
    }
}
