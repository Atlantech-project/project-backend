<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\License\Models\LicenseState;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\MasterData\Models\TugMasterProfile;
use App\Modules\MasterData\Models\TugMasterSchedule;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-F3 tug masters (FR-MD-08, Q4, Q5, Q6): one call creates user, role and profile; one tug master per tug (422 on
 * `tugboat_id`); deactivation clears the link and keeps the schedules; `{tugMaster}` binds only tug masters. Rolled
 * back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $this->manager = makeUserWithRole(Role::TugManager);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function tugMasterBody(array $overrides = []): array
{
    return [...['name' => 'Karim Adel', 'username' => 'karim.adel', 'phone' => null], ...$overrides];
}

it('creates the user, the TUG_MASTER role in Marine Services and the profile in one call, with a one-time password', function () {
    $tugboat = Tugboat::factory()->create();

    $response = $this->actingAs($this->manager, 'web')->postJson('/api/v1/tug-masters', tugMasterBody(['tugboat_id' => $tugboat->id]))
        ->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('username', 'karim.adel')
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('tugboat.id', $tugboat->id);

    $user = User::query()->where('username', 'karim.adel')->sole();

    expect($response->json('one_time_password'))->toBeString()->toHaveLength(20)
        ->and($user->activeRoleAssignment->role)->toBe(Role::TugMaster)
        ->and($user->activeRoleAssignment->unit_type->value)->toBe('MARINE_SERVICES')
        ->and(TugMasterProfile::query()->find($user->id)->assigned_tugboat_id)->toBe($tugboat->id)
        ->and(AuditLog::query()->where('action', AuditLogAction::Create)->pluck('entity_type')->sort()->values()->all())
        ->toBe(['tug_master_profiles', 'user_role_assignments', 'users']);
});

it('refuses a tug that already has a tug master with 422 on tugboat_id, on create and on relink, and saves nothing', function () {
    $tugboat = Tugboat::factory()->create();
    makeTugMaster($tugboat);
    $other = makeTugMaster();
    $users = User::query()->count();

    $this->actingAs($this->manager, 'web')->postJson('/api/v1/tug-masters', tugMasterBody(['tugboat_id' => $tugboat->id]))
        ->assertStatus(422)
        ->assertJsonPath('errors.tugboat_id', ['errors.tug_masters.tug_taken']);

    $this->actingAs($this->manager, 'web')->putJson("/api/v1/tug-masters/{$other->user_id}/tugboat", ['tugboat_id' => $tugboat->id, 'version' => VersionToken::of($other)])
        ->assertStatus(422)
        ->assertJsonPath('errors.tugboat_id', ['errors.tug_masters.tug_taken']);

    expect(User::query()->count())->toBe($users)
        ->and($other->refresh()->assigned_tugboat_id)->toBeNull();
});

it('moves a tug master to another tug and frees the old one, and unlinks with null', function () {
    $first = Tugboat::factory()->create();
    $second = Tugboat::factory()->create();
    $master = makeTugMaster($first);

    $moved = $this->actingAs($this->manager, 'web')->putJson("/api/v1/tug-masters/{$master->user_id}/tugboat", ['tugboat_id' => $second->id, 'version' => VersionToken::of($master)])
        ->assertOk()
        ->assertJsonPath('tugboat.id', $second->id);

    expect($first->tugMaster()->exists())->toBeFalse();

    $this->actingAs($this->manager, 'web')->putJson("/api/v1/tug-masters/{$master->user_id}/tugboat", ['tugboat_id' => null, 'version' => $moved->json('version')])
        ->assertOk()
        ->assertJsonPath('tugboat', null);
});

it('deactivates: the user is inactive, the tug link is cleared and the schedules are kept, in one transaction', function () {
    usePortProfile();
    $tugboat = Tugboat::factory()->create();
    $master = makeTugMaster($tugboat);
    $shift = PortShift::factory()->create();
    TugMasterSchedule::query()->create(['tug_master_user_id' => $master->user_id, 'shift_date' => now()->addDays(3)->toDateString(), 'port_shift_id' => $shift->id, 'created_by' => $this->manager->id]);

    $response = $this->actingAs($this->manager, 'web')->postJson("/api/v1/tug-masters/{$master->user_id}/deactivate", ['version' => VersionToken::of($master)])
        ->assertOk()
        ->assertJsonPath('is_active', false)
        ->assertJsonPath('tugboat', null);

    expect($master->refresh()->assigned_tugboat_id)->toBeNull()
        ->and($master->user->is_active)->toBeFalse()
        ->and(TugMasterSchedule::query()->where('tug_master_user_id', $master->user_id)->count())->toBe(1);

    $this->actingAs($this->manager, 'web')->postJson("/api/v1/tug-masters/{$master->user_id}/activate", ['version' => $response->json('version')])
        ->assertOk()
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('tugboat', null);
});

it('refuses activation at the plan limit', function () {
    $master = makeTugMaster(null, ['is_active' => false]);
    LicenseState::factory()->create(['max_users' => User::query()->where('is_active', true)->count()]);

    $this->actingAs($this->manager, 'web')->postJson("/api/v1/tug-masters/{$master->user_id}/activate", ['version' => VersionToken::of($master)])
        ->assertStatus(422)
        ->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');
});

it('updates the name and moves the version', function () {
    $master = makeTugMaster();

    $response = $this->actingAs($this->manager, 'web')->patchJson("/api/v1/tug-masters/{$master->user_id}", ['name' => 'New Name', 'version' => VersionToken::of($master)])
        ->assertOk()
        ->assertJsonPath('name', 'New Name');

    expect($response->json('version'))->not->toBe(VersionToken::of($master));
});

it('answers 404 for a user who is not a tug master', function (string $method, string $suffix) {
    $pilot = makePilot();
    $plain = makeUserWithRole(Role::PortStaff);

    foreach ([$pilot->user_id, $plain->id] as $id) {
        $this->actingAs($this->manager, 'web')->json($method, "/api/v1/tug-masters/{$id}{$suffix}", ['version' => '2026-01-01T00:00:00.000000Z', 'name' => 'X', 'tugboat_id' => null])
            ->assertNotFound();
    }

    expect($plain->refresh()->is_active)->toBeTrue();
})->with([
    'show' => ['GET', ''],
    'update' => ['PATCH', ''],
    'deactivate' => ['POST', '/deactivate'],
    'relink' => ['PUT', '/tugboat'],
]);

it('lists tug masters with their tug and is_active', function () {
    makeTugMaster(Tugboat::factory()->create(), ['name' => 'Aaa Master']);
    makeTugMaster(null, ['name' => 'Bbb Master', 'is_active' => false]);

    $this->actingAs(makeUserWithRole(Role::TugOfficer), 'web')->getJson('/api/v1/tug-masters?is_active=0&q=Bbb')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Bbb Master')
        ->assertJsonPath('data.0.is_active', false);
});

it('refuses writes from the Tug Officer and the Pilotage Manager', function (Role $role) {
    $this->actingAs(makeUserWithRole($role), 'web')->postJson('/api/v1/tug-masters', tugMasterBody())->assertForbidden();
})->with([Role::TugOfficer, Role::PilotageManager]);
