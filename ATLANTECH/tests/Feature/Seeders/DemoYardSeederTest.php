<?php

use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Enums\StorageDetailKind;
use App\Modules\Yard\Models\DgZoneAllowedClass;
use App\Modules\Yard\Models\OogZoneDetail;
use App\Modules\Yard\Models\ReeferZoneDetail;
use App\Modules\Yard\Models\YardBlock;
use App\Modules\Yard\Models\YardSlot;
use App\Modules\Yard\Models\YardZone;
use Database\Seeders\DemoPortSeeder;
use Database\Seeders\DemoYardSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;

/*
 * P2-F4 / DEC-29 G-03: a demo container yard on each container terminal of the demo port. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    $this->seed([ReferenceDataSeeder::class, DemoPortSeeder::class, DemoYardSeeder::class]);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('gives each of the three container terminals one yard with five zones, and no yard to the other terminals', function () {
    $yards = StorageFacility::query()->where('detail_kind', StorageDetailKind::ContainerYard)->get();

    expect($yards)->toHaveCount(3)
        ->and(Terminal::query()->whereIn('id', $yards->pluck('terminal_id'))->pluck('code')->sort()->values()->all())->toBe(['DCHC', 'DCT', 'DIPCO'])
        ->and(YardZone::query()->count())->toBe(15)
        ->and(YardBlock::query()->count())->toBe(18);
});

it('creates every slot of every block, all ACTIVE and empty, with the details of each zone kind', function () {
    expect(YardSlot::query()->count())->toBe(3 * (2 * 96 + 36 + 12 + 6 + 120))
        ->and(YardSlot::query()->where('status', '!=', 'ACTIVE')->orWhereNotNull('container_id')->count())->toBe(0)
        ->and(ReeferZoneDetail::query()->pluck('total_power_plugs')->unique()->all())->toBe([48])
        ->and(DgZoneAllowedClass::query()->count())->toBe(6)
        ->and(OogZoneDetail::query()->count())->toBe(3)
        ->and(YardZone::query()->whereHas('yardZoneType', fn ($type) => $type->where('code', 'EMPTY'))->count())->toBe(3);
});

it('adds nothing on a second run', function () {
    $counts = fn (): array => [StorageFacility::query()->count(), YardZone::query()->count(), YardBlock::query()->count(), YardSlot::query()->count(), DgZoneAllowedClass::query()->count()];
    $before = $counts();

    $this->seed(DemoYardSeeder::class);

    expect($counts())->toBe($before);
});
