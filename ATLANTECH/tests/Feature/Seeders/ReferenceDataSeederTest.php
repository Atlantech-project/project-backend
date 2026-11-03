<?php

use App\Modules\MasterData\Models\CargoType;
use App\Modules\MasterData\Models\ImdgClass;
use App\Modules\MasterData\Models\StatusType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('loads every Backend section of reference-seed.json', function () {
    $this->seed(ReferenceDataSeeder::class);

    $expected = [
        'imdg_classes' => 20, 'container_size_types' => 13, 'vessel_types' => 6, 'cargo_types' => 7,
        'yard_zone_types' => 4, 'storage_types' => 4, 'capability_types' => 4, 'equipment_categories' => 6,
        'status_types' => 8, 'external_systems' => 1,
    ];

    expect(collect($expected)->map(fn (int $count, string $table) => DB::table($table)->count())->all())->toBe($expected);
});

it('adds nothing on a second run and follows a changed name', function () {
    $this->seed(ReferenceDataSeeder::class);
    ImdgClass::query()->where('code', '3')->update(['name' => 'changed']);

    $this->seed(ReferenceDataSeeder::class);

    expect(ImdgClass::query()->count())->toBe(20)
        ->and(StatusType::query()->count())->toBe(8)
        ->and(ImdgClass::query()->where('code', '3')->value('name'))->toBe('Flammable liquids');
});

it('follows a changed name on status_types, whose key is (component_type, code)', function () {
    $this->seed(ReferenceDataSeeder::class);
    $row = StatusType::query()->first();
    $expected = $row->name;
    StatusType::query()->where(['component_type' => $row->component_type, 'code' => $row->code])->update(['name' => 'changed']);

    $this->seed(ReferenceDataSeeder::class);

    expect(StatusType::query()->where(['component_type' => $row->component_type, 'code' => $row->code])->value('name'))->toBe($expected);
});

it('keeps cargo types pointing at existing IMDG classes', function () {
    $this->seed(ReferenceDataSeeder::class);

    $orphans = CargoType::query()->whereNotNull('imdg_class')
        ->whereNotIn('imdg_class', ImdgClass::query()->select('code'))->count();

    expect($orphans)->toBe(0);
});

it('uses the same data as the contract copy in docs/reference', function () {
    expect(file_get_contents(database_path('seeders/data/reference-seed.json')))
        ->toBe(file_get_contents(base_path('docs/reference/contracts/reference-seed.json')));
});
