<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\Access\Services\UserAdministrationService;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * P2-E1 lock proof. Two admins deactivating each other at once is serialised by one thing: the service locks the whole Port Admin
 * set (`FOR UPDATE`, id order) before it decides. A second connection holds that lock and the service must wait for it (the wait is cut
 * to one second and ends in "Lock wait timeout", which proves it waited). Runs on committed data, not in a rolled-back transaction, because
 * another connection cannot see uncommitted rows; the rows are deleted afterwards.
 */

beforeEach(function () {
    useSchemaDatabase();
    config(['database.connections.mysql_b' => config('database.connections.mysql')]);
    DB::purge('mysql_b');
    $this->created = ['users' => [], 'units' => []];
});

afterEach(function () {
    DB::connection('mysql_b')->rollBack();
    DB::disconnect('mysql_b');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

    UserRoleAssignment::query()->whereIn('user_id', $this->created['users'])->delete();
    User::query()->whereIn('id', $this->created['users'])->delete();
    OrganizationUnit::query()->whereIn('id', $this->created['units'])->doesntHave('roleAssignments')->delete();
    DB::disconnect();
});

it('waits for the lock on the admin set, even when the lock is on the other admin and not on the target', function () {
    $first = makeUserWithRole(Role::PortGm);
    $second = makeUserWithRole(Role::PortGm);
    $this->created['users'] = [$first->id, $second->id];
    $this->created['units'] = OrganizationUnit::query()->pluck('id')->all();

    $other = DB::connection('mysql_b');
    $other->beginTransaction();
    $other->select('select id from users where id = ? for update', [$first->id]);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    expect(fn () => app(UserAdministrationService::class)->deactivate($second, VersionToken::of($second->refresh()), $first, 'POST /x'))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect($second->refresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('entity_id', $second->id)->where('entity_type', 'users')->count())->toBe(0);
});
