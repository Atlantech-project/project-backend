<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * P2-E2 lock proof, the same technique as UserDeactivateConcurrencyTest. Ending or replacing a role locks the whole Port Admin set
 * (`FOR UPDATE`, id order) before it decides. A second connection holds the lock of the other admin and the service must wait for it
 * (the wait is cut to one second and ends in "Lock wait timeout", which proves it waited). Runs on committed data because another
 * connection cannot see uncommitted rows; the rows are deleted afterwards.
 */

beforeEach(function () {
    useSchemaDatabase();
    config(['database.connections.mysql_b' => config('database.connections.mysql')]);
    DB::purge('mysql_b');
    $this->created = [];
});

afterEach(function () {
    DB::connection('mysql_b')->rollBack();
    DB::disconnect('mysql_b');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

    UserRoleAssignment::query()->whereIn('user_id', $this->created)->delete();
    User::query()->whereIn('id', $this->created)->delete();
    OrganizationUnit::query()->doesntHave('roleAssignments')->delete();
    DB::disconnect();
});

it('waits for the lock on the other admin when ending or replacing a role', function (string $action) {
    $first = makeUserWithRole(Role::PortGm);
    $second = makeUserWithRole(Role::PortGm);
    $this->created = [$first->id, $second->id];

    $other = DB::connection('mysql_b');
    $other->beginTransaction();
    $other->select('select id from users where id = ? for update', [$first->id]);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    $service = app(RoleAssignmentService::class);
    $version = VersionToken::of($second->refresh());

    $call = $action === 'end'
        ? fn () => $service->end($second, $version, $first, 'POST /x')
        : fn () => $service->assign($second, Role::PortStaff, null, $version, $first, 'POST /x');

    expect($call)->toThrow(QueryException::class, 'Lock wait timeout');

    expect($second->refresh()->activeRoleAssignment()->first()->role)->toBe(Role::PortGm)
        ->and(AuditLog::query()->where('entity_type', 'user_role_assignments')->whereIn('entity_id', UserRoleAssignment::query()->where('user_id', $second->id)->pluck('id'))->count())->toBe(0);
})->with(['end', 'assign']);
