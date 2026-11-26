<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\License\Models\LicenseState;
use App\Modules\MasterData\Enums\PilotRank;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-F3 pilot roster (FR-MD-09, Q4, Q5): create in one call with the role fixed to MARINE_PILOT, promote, deactivate and
 * activate; `{pilot}` binds only pilots. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $this->manager = makeUserWithRole(Role::PilotageManager);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function pilotBody(array $overrides = []): array
{
    return [...['name' => 'Omar Said', 'username' => 'omar.said', 'phone' => '+201000000002', 'pilot_rank' => 'THIRD'], ...$overrides];
}

it('creates the user, the MARINE_PILOT role and the profile in one call, ignoring any role sent', function () {
    $response = $this->actingAs($this->manager, 'web')->postJson('/api/v1/pilots', pilotBody(['role' => 'PORT_GM']))
        ->assertCreated()
        ->assertJsonPath('pilot_rank', 'THIRD')
        ->assertJsonPath('is_active', true);

    $user = User::query()->where('username', 'omar.said')->sole();

    expect($response->json('one_time_password'))->toBeString()
        ->and($response->json('id'))->toBe($user->id)
        ->and($user->activeRoleAssignment->role)->toBe(Role::MarinePilot)
        ->and(AuditLog::query()->where('action', AuditLogAction::Create)->where('entity_type', 'pilot_profiles')->count())->toBe(1);
});

it('refuses a new pilot at the plan limit and saves nothing', function () {
    LicenseState::factory()->create(['max_users' => User::query()->where('is_active', true)->count()]);
    $before = User::query()->count();

    $this->actingAs($this->manager, 'web')->postJson('/api/v1/pilots', pilotBody())
        ->assertStatus(422)
        ->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');

    expect(User::query()->count())->toBe($before)
        ->and(PilotProfile::query()->count())->toBe(0);
});

it('refuses a missing or unknown rank', function (array $overrides) {
    $this->actingAs($this->manager, 'web')->postJson('/api/v1/pilots', pilotBody($overrides))
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['pilot_rank']]);
})->with([[['pilot_rank' => null]], [['pilot_rank' => 'FOURTH']]]);

it('promotes a pilot and audits the profile change', function () {
    $pilot = makePilot(PilotRank::Third);

    $this->actingAs($this->manager, 'web')->patchJson("/api/v1/pilots/{$pilot->user_id}", ['pilot_rank' => 'SECOND', 'version' => VersionToken::of($pilot)])
        ->assertOk()
        ->assertJsonPath('pilot_rank', 'SECOND');

    expect(AuditLog::query()->where('action', AuditLogAction::Update)->where('entity_type', 'pilot_profiles')->count())->toBe(1);
});

it('moves the version when only the user changes, so an old version is refused', function () {
    $pilot = makePilot();
    $old = VersionToken::of($pilot);

    $this->actingAs($this->manager, 'web')->patchJson("/api/v1/pilots/{$pilot->user_id}", ['phone' => '+201000000009', 'version' => $old])
        ->assertOk()
        ->assertJsonPath('phone', '+201000000009');

    $this->actingAs($this->manager, 'web')->patchJson("/api/v1/pilots/{$pilot->user_id}", ['name' => 'Again', 'version' => $old])
        ->assertStatus(409)
        ->assertJsonPath('code', 'VERSION_CONFLICT');
});

it('deactivates and activates a pilot, keeping the profile', function () {
    $pilot = makePilot();

    $response = $this->actingAs($this->manager, 'web')->postJson("/api/v1/pilots/{$pilot->user_id}/deactivate", ['version' => VersionToken::of($pilot)])
        ->assertOk()
        ->assertJsonPath('is_active', false);

    expect(PilotProfile::query()->find($pilot->user_id))->not->toBeNull();

    $this->actingAs($this->manager, 'web')->postJson("/api/v1/pilots/{$pilot->user_id}/activate", ['version' => $response->json('version')])
        ->assertOk()
        ->assertJsonPath('is_active', true);
});

it('answers 404 for a user who is not a pilot, so a manager cannot deactivate anyone else', function () {
    $master = makeTugMaster();
    $admin = makeUserWithRole(Role::PortGm);

    foreach ([$master->user_id, $admin->id] as $id) {
        $this->actingAs($this->manager, 'web')->postJson("/api/v1/pilots/{$id}/deactivate", ['version' => '2026-01-01T00:00:00.000000Z'])->assertNotFound();
    }

    expect($admin->refresh()->is_active)->toBeTrue();
});

it('lists pilots by rank for the officer, who cannot write', function () {
    makePilot(PilotRank::First, ['name' => 'Aaa Pilot']);
    makePilot(PilotRank::Third, ['name' => 'Bbb Pilot']);
    $officer = makeUserWithRole(Role::PilotageOfficer);

    $this->actingAs($officer, 'web')->getJson('/api/v1/pilots?pilot_rank=FIRST')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Aaa Pilot');

    $this->actingAs($officer, 'web')->postJson('/api/v1/pilots', pilotBody())->assertForbidden();
});

it('answers 404 to every write on a former pilot whose role changed, so the last Port Admin cannot be deactivated through /pilots', function (string $method, string $suffix, array $body) {
    $pilot = makePilot();
    moveUserToHarbourRole($pilot->user, Role::PortGm);

    $this->actingAs($this->manager, 'web')->json($method, "/api/v1/pilots/{$pilot->user_id}{$suffix}", [...$body, 'version' => VersionToken::of($pilot)])
        ->assertNotFound();

    expect($pilot->user->refresh()->is_active)->toBeTrue()
        ->and($pilot->refresh()->pilot_rank)->toBe(PilotRank::Second);
})->with([
    'deactivate' => ['POST', '/deactivate', []],
    'update' => ['PATCH', '', ['name' => 'Renamed', 'pilot_rank' => 'FIRST']],
]);
