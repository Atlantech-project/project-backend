<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Access\Services\UserAdministrationService;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\MasterData\Models\PilotRankLimit;
use App\Modules\MasterData\Models\PilotSchedule;
use App\Modules\MasterData\Models\PortProfile;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\MasterData\Models\TugMasterProfile;
use App\Modules\MasterData\Models\TugMasterSchedule;
use App\Modules\MasterData\Services\PilotRankLimitService;
use App\Modules\MasterData\Services\PilotService;
use App\Modules\MasterData\Services\PortClock;
use App\Modules\MasterData\Services\PortShiftService;
use App\Modules\MasterData\Services\ShiftScheduleService;
use App\Modules\MasterData\Services\TugMasterService;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * P2-F3 lock proofs ("also under a race"). A second connection holds the row the service must lock first; the wait is cut
 * to one second and ends in "Lock wait timeout", which proves the service waited instead of deciding on stale data. Runs on
 * committed data, not in a rolled-back transaction, because another connection cannot see uncommitted rows; every row
 * created here is deleted afterwards. No audit row is written: every timed-out write is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    config(['database.connections.mysql_b' => config('database.connections.mysql')]);
    DB::purge('mysql_b');
    $this->createdPortProfile = PortProfile::query()->find(PortClock::PORT_PROFILE_ID) === null;
    usePortProfile('Africa/Cairo');
    $this->units = OrganizationUnit::query()->pluck('id')->all();
    $this->lastUserId = (int) User::query()->max('id');
    $this->actor = makeUserWithRole(Role::PortGm);
});

afterEach(function () {
    DB::connection('mysql_b')->rollBack();
    DB::disconnect('mysql_b');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

    $userIds = User::query()->where('id', '>', $this->lastUserId)->pluck('id')->all();
    PilotSchedule::query()->delete();
    TugMasterSchedule::query()->delete();
    PilotProfile::query()->delete();
    TugMasterProfile::query()->delete();
    Tugboat::query()->delete();
    PortShift::query()->delete();
    PilotRankLimit::query()->delete();
    UserRoleAssignment::query()->whereIn('user_id', $userIds)->delete();
    User::query()->whereIn('id', $userIds)->delete();
    OrganizationUnit::query()->whereNotIn('id', $this->units)->doesntHave('roleAssignments')->delete();

    if ($this->createdPortProfile) {
        PortProfile::query()->whereKey(PortClock::PORT_PROFILE_ID)->delete();
    }

    DB::disconnect();
});

function holdLock(string $sql, array $bindings): void
{
    $other = DB::connection('mysql_b');
    $other->beginTransaction();
    $other->select($sql, $bindings);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
}

it('links a tug master only after taking the tug row, so two links to one tug cannot both pass', function () {
    $tugboat = Tugboat::factory()->create();
    $master = makeTugMaster();

    holdLock('select id from tugboats where id = ? for update', [$tugboat->id]);

    expect(fn () => app(TugMasterService::class)->assignTugboat($master, $tugboat->id, VersionToken::of($master), $this->actor->id))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect($master->refresh()->assigned_tugboat_id)->toBeNull();
});

it('creates a port shift only after taking the port profile row, so two creates cannot both pass the overlap check', function () {
    holdLock('select id from port_profiles where id = ? for update', [PortClock::PORT_PROFILE_ID]);

    expect(fn () => app(PortShiftService::class)->create(['code' => 'DAY', 'name' => 'Day', 'start_time' => '08:00', 'end_time' => '16:00'], $this->actor->id))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect(PortShift::query()->count())->toBe(0);
});

it('adds schedule entries only after a shift change in progress has finished', function () {
    $shift = PortShift::factory()->create(['start_time' => '08:00:00', 'end_time' => '16:00:00']);
    $pilot = makePilot();

    holdLock('select id from port_shifts where id = ? for update', [$shift->id]);

    expect(fn () => app(ShiftScheduleService::class)->addPilotEntries([['user_id' => $pilot->user_id, 'shift_date' => PortClock::today(), 'port_shift_id' => $shift->id]], $this->actor->id))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect(PilotSchedule::query()->count())->toBe(0);
});

it('changes a shift only after schedule entries being added for it are committed', function () {
    $shift = PortShift::factory()->create(['start_time' => '08:00:00', 'end_time' => '16:00:00']);

    holdLock('select id from port_shifts where id = ? lock in share mode', [$shift->id]);

    expect(fn () => app(PortShiftService::class)->update($shift, ['start_time' => '07:00'], VersionToken::of($shift), $this->actor, 'PATCH /x'))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect($shift->refresh()->start_time)->toBe('08:00:00');
});

it('changes one rank limit only after taking all three rows, so two changes cannot break the order together', function () {
    foreach (['FIRST' => null, 'SECOND' => '300', 'THIRD' => '200'] as $rank => $loa) {
        PilotRankLimit::query()->create(['pilot_rank' => $rank, 'max_vessel_loa' => $loa]);
    }
    $third = PilotRankLimit::query()->find('THIRD');

    holdLock('select pilot_rank from pilot_rank_limits where pilot_rank = ? for update', ['SECOND']);

    expect(fn () => app(PilotRankLimitService::class)->update($third, '250', VersionToken::of($third), $this->actor->id))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect(PilotRankLimit::query()->find('THIRD')->max_vessel_loa)->toBe('200.00');
});

it('deactivates a pilot only after taking the user row, so a role change at the same time is seen first', function () {
    $pilot = makePilot();

    holdLock('select id from users where id = ? for update', [$pilot->user_id]);

    expect(fn () => app(PilotService::class)->setActive($pilot, false, VersionToken::of($pilot), $this->actor->id))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect($pilot->user->refresh()->is_active)->toBeTrue();
});

it('reads the role with a locking read, so activating a pilot cannot count active users on an older snapshot', function () {
    $pilot = makePilot(attributes: ['is_active' => false]);
    $assignment = UserRoleAssignment::query()->where('user_id', $pilot->user_id)->whereNull('active_to')->sole();

    holdLock('select id from user_role_assignments where id = ? for update', [$assignment->id]);

    expect(fn () => app(PilotService::class)->setActive($pilot, true, VersionToken::of($pilot), $this->actor->id))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect($pilot->user->refresh()->is_active)->toBeFalse();
});

it('changes a user only after the tug master profile is free, so the link is never cleared on stale data', function (string $write) {
    $tugboat = Tugboat::factory()->create();
    $master = makeTugMaster($tugboat);
    $user = $master->user;
    $version = VersionToken::of($user);

    holdLock('select user_id from tug_master_profiles where user_id = ? for update', [$master->user_id]);

    $call = match ($write) {
        'deactivate' => fn () => app(UserAdministrationService::class)->deactivate($user, $version, $this->actor, 'POST /x'),
        'assign' => fn () => app(RoleAssignmentService::class)->assign($user, Role::PortStaff, null, $version, $this->actor, 'POST /x'),
        'end' => fn () => app(RoleAssignmentService::class)->end($user, $version, $this->actor, 'POST /x'),
    };

    expect($call)->toThrow(QueryException::class, 'Lock wait timeout');

    expect($master->refresh()->assigned_tugboat_id)->toBe($tugboat->id)
        ->and($user->refresh()->is_active)->toBeTrue();
})->with(['deactivate', 'assign', 'end']);

it('locks the user before the tug master profile in every tug master write, the order of the /users routes', function (string $write) {
    $master = makeTugMaster(Tugboat::factory()->create());
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'for update')) {
            $queries[] = $query->sql;
        }
    });
    DB::beginTransaction();

    match ($write) {
        'update' => app(TugMasterService::class)->update($master, ['name' => 'New Name'], VersionToken::of($master), $this->actor->id),
        'deactivate' => app(TugMasterService::class)->setActive($master, false, VersionToken::of($master), $this->actor->id),
        'unlink' => app(TugMasterService::class)->assignTugboat($master, null, VersionToken::of($master), $this->actor->id),
    };

    DB::rollBack();

    expect($queries[0])->toContain('from `users`')
        ->and(collect($queries)->search(fn (string $sql) => str_contains($sql, 'from `tug_master_profiles`')))->toBeGreaterThan(0);
})->with(['update', 'deactivate', 'unlink']);
