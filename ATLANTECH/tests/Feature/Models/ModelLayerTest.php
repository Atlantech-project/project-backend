<?php

use App\Modules\Access\Models\User;
use App\Modules\Berthing\Models\BerthingRequestCargo;
use App\Modules\Execution\Models\ActualStatusUpdate;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\BerthTerminal;
use App\Modules\MasterData\Models\CargoType;
use App\Modules\MasterData\Models\Equipment;
use App\Modules\MasterData\Models\EquipmentCargoCompatibility;
use App\Modules\MasterData\Models\ImdgClass;
use App\Modules\MasterData\Models\MlModelVersion;
use App\Modules\MasterData\Models\SafetyConstraint;
use App\Modules\MasterData\Models\StatusType;
use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\MasterData\Models\StorageFacilityCargoCompatibility;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Models\TerminalCargoType;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Plan\Models\OperationalPlan;
use App\Modules\Recommendation\Models\RecommendationOption;
use App\Modules\Recommendation\Models\RecommendationOptionTug;
use App\Modules\Recommendation\Models\SolverRun;
use App\Modules\Recommendation\Models\SolverRunModelVersion;
use App\Modules\Yard\Models\DgZoneAllowedClass;
use App\Modules\Yard\Models\StorageAllocationItem;
use App\Modules\Yard\Models\StorageMovement;
use App\Modules\Yard\Models\YardZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
 * Every table is read and written through its model, with enum casts equal to the database ENUM lists (P1-B5).
 * Foreign-key checks are off so a factory may point at a parent from a later module that does not exist yet;
 * every test is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    DB::disconnect();
});

/**
 * Every Eloquent model in the domain modules.
 *
 * @return list<class-string<Model>>
 */
function domainModels(): array
{
    $classes = [];

    foreach (glob(dirname(__DIR__, 3).'/app/Modules/*/Models/*.php') as $file) {
        $class = 'App\\Modules\\'.basename(dirname($file, 2)).'\\Models\\'.basename($file, '.php');

        if (is_subclass_of($class, Model::class)) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

/**
 * The relation methods a model declares itself (return type is a Relation).
 *
 * @param  class-string<Model>  $class
 * @return list<string>
 */
function declaredRelations(string $class): array
{
    $reflection = new ReflectionClass($class);

    return collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn (ReflectionMethod $method) => $method->class === $class
            && $method->getNumberOfParameters() === 0
            && $method->getReturnType() instanceof ReflectionNamedType
            && is_subclass_of($method->getReturnType()->getName(), Relation::class))
        ->map(fn (ReflectionMethod $method) => $method->getName())
        ->values()
        ->all();
}

test('every model points at its own table and no two models share one', function () {
    $tables = collect(domainModels())->map(fn (string $class) => (new $class)->getTable());
    $existing = DB::table('information_schema.TABLES')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
        ->pluck('TABLE_NAME');

    expect($tables->diff($existing)->values()->all())->toBe([])
        ->and($tables->duplicates()->values()->all())->toBe([]);
})->group('schema');

test('every contract table has a model', function () {
    preg_match_all(
        '/^\|\s*\d+\s*\|\s*`[a-z0-9_]+`\s*\|[^|]+\|\s*`([a-z0-9_]+)`\s*\|/m',
        file_get_contents(base_path('docs/reference/contracts/db/table-names.md')),
        $matches,
    );
    $modelled = collect(domainModels())->map(fn (string $class) => (new $class)->getTable());

    expect($matches[1])->toHaveCount(92)
        ->and(array_values(array_diff($matches[1], $modelled->all())))->toBe([])
        ->and($modelled)->toHaveCount(92);
})->group('schema');

test('a row is written and read back through its model', function (string $class) {
    $written = $class::factory()->create();
    $primary = DB::table('information_schema.STATISTICS')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
        ->where('TABLE_NAME', $written->getTable())
        ->where('INDEX_NAME', 'PRIMARY')
        ->orderBy('SEQ_IN_INDEX')
        ->pluck('COLUMN_NAME');
    $read = $class::query()
        ->where(fn ($query) => $primary->each(fn (string $column) => $query->where($column, $written->getAttributes()[$column])))
        ->first();

    expect($read)->toBeInstanceOf($class)
        ->and(collect($read->getFillable())->diff(array_keys($read->getAttributes()))->values()->all())->toBe([]);

    foreach ($read->getCasts() as $column => $cast) {
        if (enum_exists($cast) && $read->getAttribute($column) !== null) {
            expect($read->getAttribute($column))->toBeInstanceOf($cast);
        }
    }
})->with(fn () => domainModels())->group('schema');

test('every enum cast holds exactly the values of its database ENUM', function (string $class) {
    $model = new $class;
    $columns = DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
        ->where('TABLE_NAME', $model->getTable())
        ->where('DATA_TYPE', 'enum')
        ->pluck('COLUMN_TYPE', 'COLUMN_NAME');

    $casts = collect($model->getCasts())->filter(fn (string $cast) => enum_exists($cast));

    expect($casts->keys()->sort()->values()->all())->toBe($columns->keys()->sort()->values()->all());

    foreach ($casts as $column => $enum) {
        preg_match_all("/'([^']*)'/", $columns[$column], $values);

        expect(array_column($enum::cases(), 'value'))->toBe($values[1], "{$class}::{$column}");
    }
})->with(fn () => domainModels())->group('schema');

test('every declared relation loads', function (string $class) {
    $model = $class::factory()->create();

    foreach (declaredRelations($class) as $relation) {
        $query = $model->{$relation}();

        expect($query)->toBeInstanceOf(Relation::class)
            ->and(fn () => $query->getResults())->not->toThrow(Throwable::class);
    }
})->with(fn () => collect(domainModels())->filter(fn (string $class) => declaredRelations($class) !== [])->values()->all())
    ->group('schema');

test('a belongsTo relation returns its own parent, not a neighbour', function (string $child, string $relation, string $parent, string $column) {
    $parent::factory()->create();
    $target = $parent::factory()->create();
    $row = $child::factory()->create([$column => $target->getKey()]);

    expect($row->{$relation}?->getKey())->toBe($target->getKey());
})->with([
    'StorageMovement::storageFacility' => [StorageMovement::class, 'storageFacility', StorageFacility::class, 'storage_facility_id'],
    'StorageAllocationItem::storageFacility' => [StorageAllocationItem::class, 'storageFacility', StorageFacility::class, 'storage_facility_id'],
    'BerthingRequestCargo::terminal' => [BerthingRequestCargo::class, 'terminal', Terminal::class, 'terminal_id'],
    'EquipmentCargoCompatibility::terminal' => [EquipmentCargoCompatibility::class, 'terminal', Terminal::class, 'terminal_id'],
    'StorageFacilityCargoCompatibility::terminal' => [StorageFacilityCargoCompatibility::class, 'terminal', Terminal::class, 'terminal_id'],
    'RecommendationOption::berth' => [RecommendationOption::class, 'berth', Berth::class, 'berth_id'],
    'SafetyConstraint::berth' => [SafetyConstraint::class, 'berth', Berth::class, 'berth_id'],
    'OperationalPlan::confirmedBy' => [OperationalPlan::class, 'confirmedBy', User::class, 'confirmed_by'],
])->group('schema');

test('a status update finds its status type by code within its own component type', function () {
    StatusType::factory()->create(['component_type' => 'BERTH', 'code' => 'ALONGSIDE']);
    StatusType::factory()->create(['component_type' => 'TUG', 'code' => 'ARRIVED']);
    StatusType::factory()->create(['component_type' => 'BERTH', 'code' => 'ARRIVED']);
    $update = ActualStatusUpdate::factory()->create(['component_type' => 'BERTH', 'status' => 'ARRIVED']);

    expect($update->statusType?->component_type?->value)->toBe('BERTH')
        ->and($update->statusType?->code)->toBe('ARRIVED');
})->group('schema');

test('eager loading the status type is refused instead of returning nothing', function () {
    ActualStatusUpdate::factory()->create();

    expect(fn () => ActualStatusUpdate::query()->with('statusType')->get())->toThrow(LogicException::class);
})->group('schema');

test('a belongsToMany through a pivot returns exactly the linked rows', function (string $owner, string $relation, string $pivot, array $keys, string $related) {
    [$ownerKey, $relatedKey, $relatedColumn] = $keys;
    $model = $owner::factory()->create();
    $related::factory()->create();
    $linked = $related::factory()->create();
    $pivot::factory()->create([$ownerKey => $model->getKey(), $relatedKey => $linked->getAttribute($relatedColumn)]);

    expect($model->{$relation}()->pluck($related::query()->getModel()->qualifyColumn($relatedColumn))->all())
        ->toBe([$linked->getAttribute($relatedColumn)]);
})->with([
    'Berth::terminals' => [Berth::class, 'terminals', BerthTerminal::class, ['berth_id', 'terminal_id', 'id'], Terminal::class],
    'Terminal::berths' => [Terminal::class, 'berths', BerthTerminal::class, ['terminal_id', 'berth_id', 'id'], Berth::class],
    'Terminal::cargoTypes' => [Terminal::class, 'cargoTypes', TerminalCargoType::class, ['terminal_id', 'cargo_type_id', 'id'], CargoType::class],
    'StorageFacility::cargoTypes' => [StorageFacility::class, 'cargoTypes', StorageFacilityCargoCompatibility::class, ['storage_facility_id', 'cargo_type_id', 'id'], CargoType::class],
    'Equipment::cargoTypes' => [Equipment::class, 'cargoTypes', EquipmentCargoCompatibility::class, ['equipment_id', 'cargo_type_id', 'id'], CargoType::class],
    'YardZone::imdgClasses' => [YardZone::class, 'imdgClasses', DgZoneAllowedClass::class, ['yard_zone_id', 'imdg_class', 'code'], ImdgClass::class],
    'RecommendationOption::tugboats' => [RecommendationOption::class, 'tugboats', RecommendationOptionTug::class, ['option_id', 'tugboat_id', 'id'], Tugboat::class],
    'SolverRun::modelVersions' => [SolverRun::class, 'modelVersions', SolverRunModelVersion::class, ['solver_run_id', 'model_version_id', 'id'], MlModelVersion::class],
])->group('schema');

test('tables only an AI worker inserts give the Backend nothing to fill beyond what CT-15 allows', function () {
    preg_match_all('/^\| `([a-z_]+)` \| AI[^|]*\| INSERT: [^|]+\| ([^|]+) \|/m', file_get_contents(base_path('docs/reference/contracts/ai/table-ownership.md')), $rows, PREG_SET_ORDER);
    $models = collect(domainModels())->keyBy(fn (string $class) => (new $class)->getTable());

    expect($rows)->toHaveCount(19)
        ->and(collect($rows)->filter(fn (array $row) => str_starts_with(trim($row[2]), 'Nothing')))->toHaveCount(13);

    foreach ($rows as [, $table, $backendMay]) {
        if (str_starts_with(trim($backendMay), 'Nothing')) {
            expect((new $models[$table])->getFillable())->toBe([], $table);
        }
    }
})->group('schema');

test('columns a worker updates are not fillable, and generated columns never are (CT-15)', function () {
    preg_match_all('/^GRANT UPDATE \(([^)]+)\) ON `([a-z_]+)`/m', file_get_contents(database_path('grants/workers.sql')), $grants, PREG_SET_ORDER);
    $models = collect(domainModels())->keyBy(fn (string $class) => (new $class)->getTable());
    $generated = DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
        ->where('EXTRA', 'like', '%STORED GENERATED%')
        ->get(['TABLE_NAME', 'COLUMN_NAME']);

    foreach ($grants as [, $columns, $table]) {
        $aiColumns = array_map(fn (string $column) => trim($column, ' `'), explode(',', $columns));

        expect(array_values(array_intersect($aiColumns, (new $models[$table])->getFillable())))->toBe([], $table);
    }

    foreach ($generated as $column) {
        expect((new $models[$column->TABLE_NAME])->getFillable())->not->toContain($column->COLUMN_NAME);
    }
})->group('schema');

test('a user signs in with username and a hashed password_hash, never shown (DEC-35)', function () {
    $user = User::factory()->create(['username' => 'staff.'.Str::lower(Str::random(6)), 'password_hash' => 'secret-pass']);

    expect(Hash::check('secret-pass', $user->getAuthPassword()))->toBeTrue()
        ->and($user->getAuthPasswordName())->toBe('password_hash')
        ->and($user->toArray())->not->toHaveKey('password_hash');
})->group('schema');

test('the version token updated_at is written and read with microseconds', function () {
    $user = User::factory()->create();

    expect(DB::table('users')->where('id', $user->id)->value('updated_at'))
        ->toBe($user->updated_at->format('Y-m-d H:i:s.u'));
})->group('schema');
