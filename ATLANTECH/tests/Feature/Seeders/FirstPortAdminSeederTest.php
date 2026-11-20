<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Database\Seeders\FirstPortAdminSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['seeding.first_port_admin' => ['username' => 'First.Admin', 'name' => 'First Admin', 'password' => 'Secret-Value-1']]);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('creates the unit, the user, the PORT_GM role and an audit row with no actor, and hashes the password', function () {
    $this->seed(FirstPortAdminSeeder::class);

    $user = User::query()->where('username', 'first.admin')->sole();

    expect($user->must_change_password)->toBeTrue()
        ->and(Hash::check('Secret-Value-1', $user->password_hash))->toBeTrue()
        ->and($user->activeRoleAssignment->role)->toBe(Role::PortGm)
        ->and(OrganizationUnit::query()->where('unit_type', 'HARBOUR_AUTHORITY')->count())->toBe(1);

    $row = AuditLog::query()->where('action', AuditLogAction::Create)->sole();

    expect($row->actor_id)->toBeNull()
        ->and(json_encode($row->after_state))->not->toContain('Secret-Value-1');
});

it('does nothing the second time, without the three values, or when an active Port Admin exists', function () {
    $this->seed(FirstPortAdminSeeder::class);
    $this->seed(FirstPortAdminSeeder::class);

    expect(User::query()->where('username', 'first.admin')->count())->toBe(1);

    config(['seeding.first_port_admin' => ['username' => 'other', 'name' => 'Other', 'password' => null]]);
    $this->seed(FirstPortAdminSeeder::class);

    expect(User::query()->where('username', 'other')->exists())->toBeFalse();

    config(['seeding.first_port_admin' => ['username' => 'other', 'name' => 'Other', 'password' => 'Another-Value-2']]);
    $this->seed(FirstPortAdminSeeder::class);

    expect(User::query()->where('username', 'other')->exists())->toBeFalse()
        ->and(UserRoleAssignment::query()->where('role', Role::PortGm)->count())->toBe(1);
});
