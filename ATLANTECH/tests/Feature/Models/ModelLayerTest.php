<?php

use App\Modules\Access\Models\User;
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

test('a row is written and read back through its model', function (string $class) {
    $written = $class::factory()->create();
    $read = $class::query()->whereKey($written->getKey())->first();

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
