<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * P2-E2 role assignment (FR-AC-11, DEC-36): end + insert in one transaction, the unit derived from the role, the
 * boundary time, no-op, the version token and the rollback. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $this->admin = makeUserWithRole(Role::PortGm);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function assignRole($test, User $target, array $body): TestResponse
{
    return $test->actingAs($test->admin, 'web')->postJson("/api/v1/users/{$target->id}/roles", [...['version' => VersionToken::of($target->refresh())], ...$body]);
}

it('replaces the role: ends the old row, inserts the new one in the right unit, and shows both newest first', function (Role $role, UnitType $unitType) {
    $target = makeUserWithRole(Role::PortStaff);
    $terminal = Terminal::factory()->create();
    OrganizationUnit::factory()->create(['terminal_id' => $terminal->id]);

    $body = ['role' => $role->value, ...($role->isTerminalScoped() ? ['terminal_id' => $terminal->id] : [])];

    assignRole($this, $target, $body)
        ->assertOk()
        ->assertJsonPath('active_role.role', $role->value)
        ->assertJsonPath('active_role.unit_type', $unitType->value)
        ->assertJsonCount(2, 'role_history')
        ->assertJsonPath('role_history.0.role', $role->value)
        ->assertJsonPath('role_history.1.role', 'PORT_STAFF')
        ->assertJsonPath('role_history.1.active_to', fn ($value) => $value !== null);

    expect(UserRoleAssignment::query()->where('user_id', $target->id)->whereNull('active_to')->count())->toBe(1);
})->with([
    'harbour' => [Role::PortGm, UnitType::HarbourAuthority],
    'marine services' => [Role::TugMaster, UnitType::MarineServices],
    'terminal' => [Role::TerminalAdmin, UnitType::Terminal],
]);

it('creates the Marine Services unit when it does not exist yet', function () {
    $target = makeUserWithRole(null);

    assignRole($this, $target, ['role' => 'PILOTAGE_MANAGER'])->assertOk();

    expect(OrganizationUnit::query()->where('unit_type', UnitType::MarineServices)->count())->toBe(1);
});

it('refuses a bad terminal_id with 422 and changes nothing', function (array $body, string $field) {
    $target = makeUserWithRole(Role::PortStaff);

    assignRole($this, $target, $body)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonStructure(['errors' => [$field]]);

    expect($target->activeRoleAssignment()->first()->role)->toBe(Role::PortStaff)
        ->and(UserRoleAssignment::query()->where('user_id', $target->id)->count())->toBe(1);
})->with([
    'terminal role without terminal_id' => [['role' => 'TERMINAL_OFFICER'], 'terminal_id'],
    'terminal_id on a harbour role' => [['role' => 'PORT_STAFF', 'terminal_id' => 5], 'terminal_id'],
    'terminal without an organization unit' => [['role' => 'TERMINAL_OFFICER', 'terminal_id' => 987654], 'terminal_id'],
    'unknown role' => [['role' => 'KING'], 'role'],
]);

it('is a no-op for the role and unit the user already holds: same version, no new row, no audit row', function () {
    $target = makeUserWithRole(Role::PortStaff);
    $version = VersionToken::of($target->refresh());

    assignRole($this, $target, ['role' => 'PORT_STAFF'])->assertOk()->assertJsonPath('version', $version)->assertJsonCount(1, 'role_history');

    expect(AuditLog::query()->count())->toBe(0);
});

it('lets the Port Admin change their own role when another active admin exists, and assign a role to a deactivated user', function () {
    makeUserWithRole(Role::PortGm);
    $inactive = makeUserWithRole(null, ['is_active' => false]);

    assignRole($this, $inactive, ['role' => 'TUG_OFFICER'])->assertOk()->assertJsonPath('active_role.role', 'TUG_OFFICER');
    assignRole($this, $this->admin, ['role' => 'PORT_STAFF'])->assertOk()->assertJsonPath('active_role.role', 'PORT_STAFF');
});

it('keeps the period check true when a role is replaced within the second it was given, with no gap', function () {
    $this->freezeSecond();
    $target = makeUserWithRole(Role::PortStaff);

    assignRole($this, $target, ['role' => 'PORT_GM'])->assertOk();
    assignRole($this, $target, ['role' => 'MARINE_SAFETY_OFFICER'])->assertOk();

    $rows = UserRoleAssignment::query()->where('user_id', $target->id)->orderBy('id')->get();

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->active_to->equalTo($rows[1]->active_from))->toBeTrue()
        ->and($rows[1]->active_to->equalTo($rows[2]->active_from))->toBeTrue()
        ->and($rows[1]->active_to->greaterThan($rows[1]->active_from))->toBeTrue()
        ->and($rows[2]->active_to)->toBeNull();
});

it('audits the ended row as UPDATE and the new row as CREATE, both with the Port Admin as actor', function () {
    $target = makeUserWithRole(Role::PortStaff);

    assignRole($this, $target, ['role' => 'PORT_GM'])->assertOk();

    $rows = AuditLog::query()->where('entity_type', 'user_role_assignments')->orderBy('id')->get();

    expect($rows->pluck('action')->all())->toBe([AuditLogAction::Update, AuditLogAction::Create])
        ->and($rows->pluck('actor_id')->unique()->all())->toBe([$this->admin->id])
        ->and($rows[0]->before_state)->toHaveKey('active_to')
        ->and($rows[1]->after_state)->toMatchArray(['role' => 'PORT_GM']);
});

it('moves the version, and a stale version is 409 VERSION_CONFLICT, a missing one 422', function () {
    $target = makeUserWithRole(Role::PortStaff);
    $stale = VersionToken::of($target->refresh());
    usleep(2000);

    $changed = assignRole($this, $target, ['role' => 'PORT_GM'])->assertOk()->json('version');

    expect($changed)->not->toBe($stale);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$target->id}/roles", ['role' => 'PORT_STAFF', 'version' => $stale])
        ->assertStatus(409)->assertJsonPath('code', 'VERSION_CONFLICT');
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$target->id}/roles", ['role' => 'PORT_STAFF'])->assertStatus(422);
});

it('answers 403 to every role except the Port Admin', function (Role $role) {
    $target = makeUserWithRole(Role::PortStaff);
    $version = VersionToken::of($target->refresh());

    $this->actingAs(makeUserWithRole($role), 'web')->postJson("/api/v1/users/{$target->id}/roles", ['role' => 'PORT_GM', 'version' => $version])->assertForbidden();
    $this->actingAs(makeUserWithRole($role), 'web')->postJson("/api/v1/users/{$target->id}/roles/end", ['version' => $version])->assertForbidden();
})->with(array_values(array_filter(Role::cases(), fn (Role $role) => $role !== Role::PortGm)));

it('answers 401 to a guest', function () {
    $target = makeUserWithRole(Role::PortStaff);

    $this->postJson("/api/v1/users/{$target->id}/roles", ['role' => 'PORT_GM'])->assertUnauthorized();
    $this->postJson("/api/v1/users/{$target->id}/roles/end", [])->assertUnauthorized();
});

it('rolls back the ended row and the new row together when the audit write fails', function () {
    $target = makeUserWithRole(Role::PortStaff);

    app()->instance(AuditService::class, new class extends AuditService
    {
        public function recordCreated(Model $model, ?int $actorId): AuditLog
        {
            throw new RuntimeException('forced failure');
        }
    });

    assignRole($this, $target, ['role' => 'PORT_GM'])->assertStatus(500);

    expect(UserRoleAssignment::query()->where('user_id', $target->id)->count())->toBe(1)
        ->and($target->activeRoleAssignment()->first()->role)->toBe(Role::PortStaff);
});
