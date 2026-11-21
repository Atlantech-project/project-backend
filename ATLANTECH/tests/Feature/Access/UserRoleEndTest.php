<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * P2-E2 ending a role and the last-Port-Admin rule (FR-AC-12). Rolled back after each test.
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

function endRole($test, User $actor, User $target): TestResponse
{
    return $test->actingAs($actor, 'web')->postJson("/api/v1/users/{$target->id}/roles/end", ['version' => VersionToken::of($target->refresh())]);
}

it('ends the active role: the user has no role, the history keeps the row, and every request answers 403', function () {
    $target = makeUserWithRole(Role::PortStaff);

    endRole($this, $this->admin, $target)
        ->assertOk()
        ->assertJsonPath('active_role', null)
        ->assertJsonCount(1, 'role_history')
        ->assertJsonPath('role_history.0.active_to', fn ($value) => $value !== null);

    $this->actingAs($target->refresh(), 'web')->getJson('/api/v1/users')->assertForbidden();
    expect(AuditLog::query()->where('entity_type', 'user_role_assignments')->sole()->action)->toBe(AuditLogAction::Update);
});

it('is a no-op when the user has no active role', function () {
    $target = makeUserWithRole(null);
    $version = VersionToken::of($target->refresh());

    endRole($this, $this->admin, $target)->assertOk()->assertJsonPath('version', $version);

    expect(AuditLog::query()->count())->toBe(0);
});

it('refuses to end or replace the last active Port Admin role with 409 CONFLICT, audited, and changes nothing', function (string $action) {
    $actor = makeUserWithRole(Role::PortStaff);
    $actor->roleAssignments()->delete();
    $version = VersionToken::of($this->admin->refresh());

    // The only admin is the target; the actor holds the role through a token created before it was changed (pending request).
    $service = app(RoleAssignmentService::class);

    expect(fn () => $action === 'end'
        ? $service->end($this->admin, $version, $actor, 'POST /api/v1/users/x/roles/end')
        : $service->assign($this->admin, Role::PortStaff, null, $version, $actor, 'POST /api/v1/users/x/roles'))
        ->toThrow(fn (ApiProblemException $e) => $e->getStatusCode() === 409);

    $row = AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->sole();

    expect($this->admin->activeRoleAssignment()->first()->role)->toBe(Role::PortGm)
        ->and($row->entity_id)->toBe($this->admin->id)
        ->and($row->after_state)->toMatchArray(['code' => 'CONFLICT', 'reason' => 'last_port_admin']);
})->with(['end', 'replace']);

it('refuses the second of two admins ending each other, one after the other, so one admin remains', function () {
    $second = makeUserWithRole(Role::PortGm);

    endRole($this, $this->admin, $second)->assertOk();

    // The second admin's request was already past authentication; it now meets a lone admin.
    $stale = VersionToken::of($this->admin->refresh());

    expect(fn () => app(RoleAssignmentService::class)->end($this->admin, $stale, $second, 'POST /api/v1/users/x/roles/end'))
        ->toThrow(ApiProblemException::class);
    expect($this->admin->activeRoleAssignment()->first()->role)->toBe(Role::PortGm);
});

it('allows an admin to end their own role when a second active admin exists, and does not count an inactive one', function () {
    $inactive = makeUserWithRole(Role::PortGm, ['is_active' => false]);

    $service = app(RoleAssignmentService::class);

    expect(fn () => $service->end($this->admin, VersionToken::of($this->admin->refresh()), $this->admin, 'POST /x'))->toThrow(ApiProblemException::class);

    $inactive->update(['is_active' => true]);

    endRole($this, $this->admin, $this->admin)->assertOk()->assertJsonPath('active_role', null);
    expect(UserRoleAssignment::query()->where('user_id', $this->admin->id)->whereNull('active_to')->count())->toBe(0);
});

it('moves the version when the role ends', function () {
    $target = makeUserWithRole(Role::PortStaff);
    $before = VersionToken::of($target->refresh());
    usleep(2000);

    endRole($this, $this->admin, $target)->assertOk()->assertJsonPath('version', fn (string $version) => $version !== $before);
});
