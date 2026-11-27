<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-F3 tug fleet (FR-MD-07, Q10, Q11): bollard pull only, BUSY never set by the Tug Manager, the tug master link kept on
 * any status. Rolled back after each test.
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

function tugboatBody(array $overrides = []): array
{
    return [...['name' => 'Horus 1', 'tug_power' => '60.5', 'tug_power_unit' => 'BOLLARD_PULL_TONNES', 'capacity' => null], ...$overrides];
}

it('creates an available tug and audits it', function () {
    $this->actingAs($this->manager, 'web')->postJson('/api/v1/tugboats', tugboatBody())
        ->assertCreated()
        ->assertJsonPath('name', 'Horus 1')
        ->assertJsonPath('tug_power', '60.50')
        ->assertJsonPath('status', 'AVAILABLE')
        ->assertJsonPath('tug_master', null);

    expect(AuditLog::query()->where('action', AuditLogAction::Create)->where('entity_type', 'tugboats')->count())->toBe(1);
});

it('refuses bad tug data with 422', function (array $overrides, string $field) {
    Tugboat::factory()->create(['name' => 'Taken']);

    $this->actingAs($this->manager, 'web')->postJson('/api/v1/tugboats', tugboatBody($overrides))
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => [$field]]);
})->with([
    'power not positive' => [['tug_power' => '0'], 'tug_power'],
    'HP refused for now' => [['tug_power_unit' => 'HP'], 'tug_power_unit'],
    'KW refused for now' => [['tug_power_unit' => 'KW'], 'tug_power_unit'],
    'BUSY not settable' => [['status' => 'BUSY'], 'status'],
    'name taken' => [['name' => 'Taken'], 'name'],
]);

it('sets maintenance and inactive, keeps the tug master link, and refuses BUSY', function () {
    $tugboat = Tugboat::factory()->create();
    $master = makeTugMaster($tugboat);

    $response = $this->actingAs($this->manager, 'web')->patchJson("/api/v1/tugboats/{$tugboat->id}", ['status' => 'MAINTENANCE', 'version' => VersionToken::of($tugboat)])
        ->assertOk()
        ->assertJsonPath('status', 'MAINTENANCE')
        ->assertJsonPath('tug_master.user_id', $master->user_id);

    $this->actingAs($this->manager, 'web')->patchJson("/api/v1/tugboats/{$tugboat->id}", ['status' => 'INACTIVE', 'version' => $response->json('version')])
        ->assertOk()
        ->assertJsonPath('status', 'INACTIVE');

    $this->actingAs($this->manager, 'web')->patchJson("/api/v1/tugboats/{$tugboat->id}", ['status' => 'BUSY', 'version' => VersionToken::of($tugboat->refresh())])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['status']]);

    expect($master->refresh()->assigned_tugboat_id)->toBe($tugboat->id);
});

it('answers 409 VERSION_CONFLICT to a stale version', function () {
    $tugboat = Tugboat::factory()->create();
    $stale = VersionToken::of($tugboat);
    $this->travel(1)->seconds();
    $tugboat->touch();

    $this->actingAs($this->manager, 'web')->patchJson("/api/v1/tugboats/{$tugboat->id}", ['name' => 'X', 'version' => $stale])
        ->assertStatus(409)
        ->assertJsonPath('code', 'VERSION_CONFLICT');
});

it('lets the officer read the fleet but not change it', function () {
    $tugboat = Tugboat::factory()->create();
    $officer = makeUserWithRole(Role::TugOfficer);

    $this->actingAs($officer, 'web')->getJson('/api/v1/tugboats?status=AVAILABLE')->assertOk()->assertJsonPath('meta.total', Tugboat::query()->where('status', 'AVAILABLE')->count());
    $this->actingAs($officer, 'web')->getJson("/api/v1/tugboats/{$tugboat->id}")->assertOk()->assertJsonPath('id', $tugboat->id);
    $this->actingAs($officer, 'web')->postJson('/api/v1/tugboats', tugboatBody())->assertForbidden();
    $this->actingAs($officer, 'web')->patchJson("/api/v1/tugboats/{$tugboat->id}", ['name' => 'X', 'version' => VersionToken::of($tugboat)])->assertForbidden();
});

it('answers 401 to a guest and 403 to a tug master', function () {
    $this->getJson('/api/v1/tugboats')->assertUnauthorized();
    $this->actingAs(makeTugMaster()->user, 'web')->getJson('/api/v1/tugboats')->assertForbidden();
});
