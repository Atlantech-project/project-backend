<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\MasterData\Enums\PilotRank;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\MasterData\Models\PortProfile;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\MasterData\Models\TugMasterProfile;
use App\Modules\MasterData\Services\PortClock;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
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

/**
 * Let after-commit work (`ShouldDispatchAfterCommit` events, `afterCommit` jobs and callbacks) run inside a test that wraps itself in
 * `DB::beginTransaction()`: Laravel's testing transaction manager ignores that outer transaction, so a commit of the code's own
 * transaction runs the callbacks and a rollback drops them, as in production. Call it after `useSchemaDatabase()` and before
 * `DB::beginTransaction()`.
 */
function useTestingTransactionManager(): void
{
    $manager = new DatabaseTransactionsManager([DB::getDefaultConnection()]);

    app()->instance('db.transactions', $manager);
    DB::connection()->setTransactionManager($manager);
}

/**
 * The port profile row (id 1) with a real IANA time zone, so `PortClock::today()` works (P2-F3). Call it inside the
 * test transaction.
 */
function usePortProfile(string $timezone = 'Africa/Cairo'): PortProfile
{
    $profile = PortProfile::query()->find(PortClock::PORT_PROFILE_ID);

    if ($profile === null) {
        return PortProfile::factory()->create(['timezone' => $timezone]);
    }

    $profile->update(['timezone' => $timezone]);

    return $profile;
}

/**
 * A pilot (user, active `MARINE_PILOT` role, profile) for the P2-F3 tests.
 *
 * @param  array<string, mixed>  $attributes  User attributes.
 */
function makePilot(PilotRank $rank = PilotRank::Second, array $attributes = []): PilotProfile
{
    $user = makeUserWithRole(Role::MarinePilot, $attributes);

    return PilotProfile::query()->create(['user_id' => $user->id, 'pilot_rank' => $rank])->refresh();
}

/**
 * End the user's active role an hour ago and give them `$role` in the Harbour Authority unit (P2-F3: the profile stays,
 * as after a role change by the Port Admin).
 */
function moveUserToHarbourRole(User $user, Role $role): void
{
    UserRoleAssignment::query()->where('user_id', $user->id)->whereNull('active_to')
        ->update(['active_from' => now()->subDay(), 'active_to' => now()->subHour()]);

    $unit = OrganizationUnit::query()->where('unit_type', UnitType::HarbourAuthority)->first()
        ?? OrganizationUnit::factory()->harbourAuthority()->create();

    UserRoleAssignment::factory()->for($user)->for($unit)->create(['role' => $role, 'unit_type' => $unit->unit_type]);
}

/**
 * A tug master (user, active `TUG_MASTER` role, profile) for the P2-F3 tests, linked to `$tugboat` when given.
 *
 * @param  array<string, mixed>  $attributes  User attributes.
 */
function makeTugMaster(?Tugboat $tugboat = null, array $attributes = []): TugMasterProfile
{
    $user = makeUserWithRole(Role::TugMaster, $attributes);

    return TugMasterProfile::query()->create(['user_id' => $user->id, 'assigned_tugboat_id' => $tugboat?->id])->refresh();
}
