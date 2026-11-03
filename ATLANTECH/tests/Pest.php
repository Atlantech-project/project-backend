<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Point the default connection at the MySQL schema-test database (docker-compose.yml) and
 * build it from the migrations once per run. SQLite cannot run the CHECK constraints,
 * generated columns and ENUMs the port schema relies on (CT-17).
 */
function useSchemaDatabase(): void
{
    static $migrated = false;

    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => env('DB_SCHEMA_TEST_DATABASE', 'atlantech_testing'),
    ]);
    DB::purge('mysql');

    if (! $migrated) {
        Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
        $migrated = true;
    }
}

/**
 * A user holding one active role in the unit the role belongs to (the Harbour Authority and Marine Services units are single;
 * a terminal role gets a new terminal unless `$unit` is given). A null role makes a user with no active role.
 *
 * @param  array<string, mixed>  $attributes  User attributes.
 */
function makeUserWithRole(?Role $role, array $attributes = [], ?OrganizationUnit $unit = null): User
{
    $user = User::factory()->create($attributes);

    if ($role === null) {
        return $user;
    }

    $unit ??= match (true) {
        $role->isTerminalScoped() => OrganizationUnit::factory()->create(),
        in_array($role, [Role::PortGm, Role::PortStaff, Role::MarineSafetyOfficer], true) => OrganizationUnit::query()->where('unit_type', UnitType::HarbourAuthority)->first()
            ?? OrganizationUnit::factory()->harbourAuthority()->create(),
        default => OrganizationUnit::query()->where('unit_type', UnitType::MarineServices)->first()
            ?? OrganizationUnit::factory()->marineServices()->create(),
    };

    UserRoleAssignment::factory()->for($user)->for($unit)->create(['role' => $role, 'unit_type' => $unit->unit_type]);

    return $user;
}
