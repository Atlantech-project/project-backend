<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Access\Services\UserAdministrationService;
use App\Modules\License\Models\LicenseState;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-E1 deactivate and activate (FR-AC-10): the rules for yourself and the last Port Admin, the version token, the no-op,
 * the plan limit on activate, and the order of two admins acting on each other. Rolled back after each test.
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

function versionOf(User $user): string
{
    return VersionToken::of($user->refresh());
}

it('deactivates a user, changes the version, audits it, and the user is refused on the next request', function () {
    $target = makeUserWithRole(Role::PortStaff);
    $before = versionOf($target);
    usleep(2000);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$target->id}/deactivate", ['version' => $before])
        ->assertOk()
        ->assertJsonPath('is_active', false)
        ->assertJsonPath('version', fn (string $version) => $version !== $before);

    $row = AuditLog::query()->where('action', AuditLogAction::Update)->where('entity_type', 'users')->sole();

    expect($row->entity_id)->toBe($target->id)
        ->and($row->actor_id)->toBe($this->admin->id)
        ->and($row->before_state)->toMatchArray(['is_active' => true])
        ->and($row->after_state)->toMatchArray(['is_active' => false]);

    $this->actingAs($target->refresh(), 'web')->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'ACCOUNT_DISABLED');
});

it('refuses to deactivate yourself with 409 CONFLICT, audited as REJECTED_ATTEMPT, and changes nothing', function () {
    makeUserWithRole(Role::PortGm);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->admin->id}/deactivate", ['version' => versionOf($this->admin)])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT')
        ->assertJsonPath('detail', __('errors.users.cannot_deactivate_self'));

    $row = AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->sole();

    expect($this->admin->refresh()->is_active)->toBeTrue()
        ->and($row->actor_id)->toBe($this->admin->id)
        ->and($row->entity_id)->toBe($this->admin->id)
        ->and($row->after_state)->toMatchArray(['status' => 409, 'code' => 'CONFLICT', 'reason' => 'cannot_deactivate_self']);
});

it('refuses to deactivate the last active Port Admin when the actor is no longer an active admin, and allows it with a second admin', function () {
    $second = makeUserWithRole(Role::PortGm);
    $service = app(UserAdministrationService::class);

    $service->deactivate($second, versionOf($second), $this->admin, 'POST /x');

    expect($second->refresh()->is_active)->toBeFalse();

    // The pending request of the second admin (authenticated before the first one committed) now meets a lone admin.
    expect(fn () => $service->deactivate($this->admin, versionOf($this->admin), $second, 'POST /api/v1/users/x/deactivate'))
        ->toThrow(fn (ApiProblemException $e) => $e->errorCode === ErrorCode::Conflict && str_contains($e->detail, 'Port Admin'));

    $row = AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->sole();

    expect($this->admin->refresh()->is_active)->toBeTrue()
        ->and($row->actor_id)->toBe($second->id)
        ->and($row->after_state)->toMatchArray(['reason' => 'last_port_admin', 'route' => 'POST /api/v1/users/x/deactivate']);
});

it('does not count an inactive admin, so the only active admin stays protected', function () {
    makeUserWithRole(Role::PortGm, ['is_active' => false]);
    $service = app(UserAdministrationService::class);
    $actor = makeUserWithRole(Role::PortStaff);

    expect(fn () => $service->deactivate($this->admin, versionOf($this->admin), $actor, 'POST /x'))
        ->toThrow(ApiProblemException::class);
});

it('answers 409 VERSION_CONFLICT for a stale version, 422 for a missing one, and nothing changes', function () {
    $target = makeUserWithRole(Role::PortStaff);
    $stale = versionOf($target);
    usleep(2000);
    $target->touch();

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$target->id}/deactivate", ['version' => $stale])
        ->assertStatus(409)
        ->assertJsonPath('code', 'VERSION_CONFLICT')
        ->assertJsonPath('current.id', $target->id);
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$target->id}/deactivate", [])
        ->assertStatus(422);

    expect($target->refresh()->is_active)->toBeTrue();
});

it('deactivating an inactive user and activating an active one are no-ops with the same version and no audit row', function () {
    $inactive = makeUserWithRole(Role::PortStaff, ['is_active' => false]);
    $active = makeUserWithRole(Role::PortStaff);
    $inactiveVersion = versionOf($inactive);
    $activeVersion = versionOf($active);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$inactive->id}/deactivate", ['version' => $inactiveVersion])
        ->assertOk()->assertJsonPath('version', $inactiveVersion);
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$active->id}/activate", ['version' => $activeVersion])
        ->assertOk()->assertJsonPath('version', $activeVersion);

    expect(AuditLog::query()->where('entity_type', 'users')->count())->toBe(0);
});

it('activates a user and audits it', function () {
    $target = makeUserWithRole(Role::PortStaff, ['is_active' => false]);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$target->id}/activate", ['version' => versionOf($target)])
        ->assertOk()
        ->assertJsonPath('is_active', true);

    expect(AuditLog::query()->where('action', AuditLogAction::Update)->sole()->after_state)->toMatchArray(['is_active' => true]);
});

it('refuses to activate over the plan limit with 422 PLAN_LIMIT_EXCEEDED, and an inactive user does not count', function () {
    $target = makeUserWithRole(Role::PortStaff, ['is_active' => false]);
    LicenseState::factory()->create(['max_users' => 1]);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$target->id}/activate", ['version' => versionOf($target)])
        ->assertStatus(422)
        ->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');

    expect($target->refresh()->is_active)->toBeFalse();

    LicenseState::query()->update(['max_users' => 2]);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$target->id}/activate", ['version' => versionOf($target)])
        ->assertOk();
});

it('is allowed while the licence is READ_ONLY (the licence guard is still a pass-through, P2-D5)', function () {
    LicenseState::factory()->create();
    $target = makeUserWithRole(Role::PortStaff);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$target->id}/deactivate", ['version' => versionOf($target)])
        ->assertOk();
});

it('answers 403 to every role except the Port Admin, before any 404', function (Role $role) {
    $target = makeUserWithRole(Role::PortStaff);

    $this->actingAs(makeUserWithRole($role), 'web')->postJson("/api/v1/users/{$target->id}/deactivate", ['version' => versionOf($target)])
        ->assertForbidden();
    $this->actingAs(makeUserWithRole($role), 'web')->postJson('/api/v1/users/999999/deactivate', ['version' => versionOf($target)])
        ->assertForbidden();
})->with(array_values(array_filter(Role::cases(), fn (Role $role) => $role !== Role::PortGm)));

it('answers 404 for an unknown user to the Port Admin', function () {
    $this->actingAs($this->admin, 'web')->postJson('/api/v1/users/999999/deactivate', ['version' => '2026-11-02T10:15:04.123456Z'])
        ->assertNotFound();
});
