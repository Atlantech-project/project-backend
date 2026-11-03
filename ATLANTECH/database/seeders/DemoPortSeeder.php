<?php

namespace Database\Seeders;

use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\ChannelWaypoint;
use App\Modules\MasterData\Models\NavigationChannel;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\Terminal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A demo port at the scale of the reference port: 8 terminals, 36 berths, a channel and three shifts, read from
 * `database/seeders/data/damietta-demo-port.json` (the same fixture AI-015 uses). Every row is upserted by its
 * code, so a second run changes nothing.
 */
class DemoPortSeeder extends Seeder
{
    /**
     * @var array<string, mixed>
     */
    private array $fixture;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedOrganizationUnits();
            $this->seedTerminals();
            $this->seedBerths();
            $this->seedChannel();
            $this->seedShifts();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function fixture(): array
    {
        return $this->fixture ??= json_decode((string) file_get_contents(database_path('seeders/data/damietta-demo-port.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function seedOrganizationUnits(): void
    {
        foreach (['HARBOUR_AUTHORITY', 'MARINE_SERVICES'] as $unitType) {
            OrganizationUnit::query()->firstOrCreate(['unit_type' => $unitType, 'terminal_id' => null]);
        }
    }

    private function seedTerminals(): void
    {
        foreach ($this->fixture()['terminals'] as $row) {
            $terminal = Terminal::query()->updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);

            OrganizationUnit::query()->firstOrCreate(['unit_type' => 'TERMINAL', 'terminal_id' => $terminal->id]);
        }
    }

    private function seedBerths(): void
    {
        $terminalIds = Terminal::query()->pluck('id', 'code');

        foreach ($this->fixture()['berths'] as $row) {
            $berth = Berth::query()->updateOrCreate(
                ['code' => $row['code']],
                collect($row)->except('terminal_codes')->all() + ['depth_datum' => $this->fixture()['depth_datum'], 'status' => 'ACTIVE'],
            );

            $berth->terminals()->syncWithoutDetaching(collect($row['terminal_codes'])->map(fn (string $code) => $terminalIds[$code]));
        }
    }

    private function seedChannel(): void
    {
        $row = $this->fixture()['navigation_channel'];

        $channel = NavigationChannel::query()->updateOrCreate(
            ['name' => $row['name']],
            collect($row)->except('waypoints')->all() + ['is_active' => true],
        );

        foreach ($row['waypoints'] as $index => $waypoint) {
            ChannelWaypoint::query()->updateOrCreate(
                ['channel_id' => $channel->id, 'sequence_order' => $index + 1],
                $waypoint,
            );
        }
    }

    private function seedShifts(): void
    {
        foreach ($this->fixture()['port_shifts'] as $row) {
            PortShift::query()->updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
        }
    }
}
