<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\MasterData\Models\PilotSchedule;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\MasterData\Models\TugMasterProfile;
use App\Modules\MasterData\Models\TugMasterSchedule;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoPeopleSeeder;
use Database\Seeders\DemoPortSeeder;
use Database\Seeders\PortProfileSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['seeding.demo_user_password' => null]);
    $this->seed(DemoPortSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
    DB::rollBack();
    DB::disconnect();
});

it('creates a user for each of the 12 roles in a unit that matches the role', function () {
    $this->seed(DemoPeopleSeeder::class);

    $roles = UserRoleAssignment::query()->whereNull('active_to')->distinct()->pluck('role')->map->value->sort()->values()->all();

    expect($roles)->toBe(collect(Role::cases())->map->value->sort()->values()->all());
});

it('creates 12 pilots, 10 tugs and one master for each tug', function () {
    $this->seed(DemoPeopleSeeder::class);

    expect(PilotProfile::query()->count())->toBe(12)
        ->and(Tugboat::query()->count())->toBe(10)
        ->and(TugMasterProfile::query()->whereNotNull('assigned_tugboat_id')->distinct('assigned_tugboat_id')->count('assigned_tugboat_id'))->toBe(10)
        ->and(UserRoleAssignment::query()->where('role', 'MARINE_PILOT')->count())->toBe(12)
        ->and(UserRoleAssignment::query()->where('role', 'TUG_MASTER')->count())->toBe(10);
});

it('schedules every pilot and tug master for 7 days, one shift a day', function () {
    Carbon::setTestNow('2026-11-10 09:00:00');

    $this->seed(DemoPeopleSeeder::class);

    expect(PilotSchedule::query()->count())->toBe(12 * 7)
        ->and(TugMasterSchedule::query()->count())->toBe(10 * 7)
        ->and(PilotSchedule::query()->min('shift_date'))->toBe('2026-11-10')
        ->and(PilotSchedule::query()->max('shift_date'))->toBe('2026-11-16')
        ->and(PilotSchedule::query()->where('shift_date', '2026-11-10')->distinct('port_shift_id')->count('port_shift_id'))->toBe(3);
});

it('forces a password change and gives nobody a usable password when none is configured', function () {
    $this->seed(DemoPeopleSeeder::class);

    expect(User::query()->where('must_change_password', false)->count())->toBe(0)
        ->and(User::query()->count())->toBe(32)
        ->and(Hash::check('', User::query()->where('username', 'port.gm')->value('password_hash')))->toBeFalse()
        ->and(User::query()->distinct('password_hash')->count('password_hash'))->toBe(1);
});

it('uses DEMO_USER_PASSWORD for every demo user when it is set', function () {
    config(['seeding.demo_user_password' => 'a-demo-password-for-tests']);

    $this->seed(DemoPeopleSeeder::class);

    expect(Hash::check('a-demo-password-for-tests', User::query()->where('username', 'pilot.01')->value('password_hash')))->toBeTrue();
});

it('does not duplicate anyone when it runs twice', function () {
    Carbon::setTestNow('2026-11-10 09:00:00');

    $this->seed(DemoPeopleSeeder::class);
    $this->seed(DemoPeopleSeeder::class);

    expect(User::query()->count())->toBe(32)
        ->and(UserRoleAssignment::query()->count())->toBe(32)
        ->and(PilotSchedule::query()->count())->toBe(84);
});

it('loads the demo port only when the config allows it', function () {
    config(['seeding.demo_port' => false]);
    DB::table('berth_terminals')->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    DB::table('berths')->delete();
    DB::table('terminals')->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');

    $this->seed(DatabaseSeeder::class);

    expect(DB::table('berths')->count())->toBe(0)
        ->and(DB::table('port_profiles')->count())->toBe(1)
        ->and(DB::table('port_settings')->where('setting_key', 'min_app_version')->exists())->toBeTrue();

    config(['seeding.demo_port' => true]);
    $this->seed(DatabaseSeeder::class);

    expect(DB::table('berths')->count())->toBe(36)
        ->and(User::query()->count())->toBe(33);
});

it('refuses to run any demo seeder by class name when demo seeding is off', function (string $seeder) {
    config(['seeding.demo_port' => false]);

    expect(fn () => $this->seed($seeder))->toThrow(RuntimeException::class, 'SEED_DEMO_PORT');
})->with([DemoPortSeeder::class, DemoPeopleSeeder::class, PortProfileSeeder::class]);

it('turns demo seeding off in production and when APP_ENV is missing, unless SEED_DEMO_PORT is set', function (?string $appEnv, ?string $seedDemoPort, bool $expected) {
    $saved = [getenv('APP_ENV'), getenv('SEED_DEMO_PORT'), $_ENV, $_SERVER];

    foreach (['APP_ENV' => $appEnv, 'SEED_DEMO_PORT' => $seedDemoPort] as $name => $value) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($value === null ? $name : "{$name}={$value}");

        if ($value !== null) {
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
    }

    try {
        expect((require config_path('seeding.php'))['demo_port'])->toBe($expected);
    } finally {
        putenv($saved[0] === false ? 'APP_ENV' : 'APP_ENV='.$saved[0]);
        putenv($saved[1] === false ? 'SEED_DEMO_PORT' : 'SEED_DEMO_PORT='.$saved[1]);
        [, , $_ENV, $_SERVER] = $saved;
    }
})->with([
    'production' => ['production', null, false],
    'APP_ENV missing' => [null, null, false],
    'local' => ['local', null, true],
    'production forced' => ['production', 'true', true],
]);
