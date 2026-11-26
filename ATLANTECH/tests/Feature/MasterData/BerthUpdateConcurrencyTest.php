<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Services\BerthService;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * P2-F2 lock proof, the same technique as UserRoleConcurrencyTest: `BerthService::update` reads the berth `FOR UPDATE` before it
 * decides. A second connection holds that row lock, so the update must wait (the wait is cut to one second and ends in "Lock wait
 * timeout", which proves it waited) and writes nothing. Runs on committed data because another connection cannot see uncommitted
 * rows; the rows are deleted afterwards.
 */

beforeEach(function () {
    useSchemaDatabase();
    config(['database.connections.mysql_b' => config('database.connections.mysql')]);
    DB::purge('mysql_b');
});

afterEach(function () {
    DB::connection('mysql_b')->rollBack();
    DB::disconnect('mysql_b');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

    if (isset($this->berth)) {
        DB::table('berth_terminals')->where('berth_id', $this->berth->id)->delete();
        Berth::query()->whereKey($this->berth->id)->delete();
        Terminal::query()->whereKey($this->terminalIds)->delete();
    }

    if (isset($this->admin)) {
        UserRoleAssignment::query()->where('user_id', $this->admin->id)->delete();
        User::query()->whereKey($this->admin->id)->delete();
        OrganizationUnit::query()->doesntHave('roleAssignments')->whereNull('terminal_id')->delete();
    }

    DB::disconnect();
});

it('waits for the row lock on the berth before it changes the berth or its terminals', function () {
    $this->admin = makeUserWithRole(Role::PortGm);
    [$first, $second] = Terminal::factory()->count(2)->create();
    $this->terminalIds = [$first->id, $second->id];
    $this->berth = Berth::factory()->create(['length' => 320, 'max_vessel_loa' => 300, 'name' => 'Before']);
    $this->berth->terminals()->attach($first->id);
    $version = VersionToken::of($this->berth->fresh());

    $other = DB::connection('mysql_b');
    $other->beginTransaction();
    $other->select('select id from berths where id = ? for update', [$this->berth->id]);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    expect(fn () => app(BerthService::class)->update($this->berth, ['name' => 'After', 'terminal_ids' => [$first->id, $second->id]], $version, $this->admin, 'PUT /x'))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect($this->berth->fresh()->name)->toBe('Before')
        ->and($this->berth->fresh()->terminals()->pluck('terminals.id')->all())->toBe([$first->id])
        ->and(AuditLog::query()->where('entity_type', 'berths')->where('entity_id', $this->berth->id)->count())->toBe(0);
});
