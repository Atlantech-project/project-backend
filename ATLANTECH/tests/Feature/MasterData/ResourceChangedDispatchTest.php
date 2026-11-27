<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Services\RoleStartHandler;
use App\Modules\MasterData\Enums\PilotRank;
use App\Modules\MasterData\Models\CapabilityType;
use App\Modules\MasterData\Models\EquipmentCategory;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\MasterData\Models\PilotRankLimit;
use App\Modules\MasterData\Models\PilotSchedule;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\MasterData\Models\TugMasterSchedule;
use App\Modules\Shared\Enums\ResourceKind;
use App\Modules\Shared\Enums\TerminalType;
use App\Modules\Shared\Events\ResourceChanged;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
 * P2-F7 (FR-MD-10, FR-RC-16, CT-10, DEC-32): every change of a tug, a pilot or equipment that affects planning reaches
 * `ResourceChanged` listeners once per resource, after the commit; a name or phone, a no-op or a refused request
 * announces nothing. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    useTestingTransactionManager();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    usePortProfile();
    $this->admin = makeUserWithRole(Role::PortGm);
    $this->tugManager = makeUserWithRole(Role::TugManager);
    $this->pilotageManager = makeUserWithRole(Role::PilotageManager);

    $this->received = collect();
    Event::listen(ResourceChanged::class, fn (ResourceChanged $event) => $this->received->push($event));
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

/**
 * The received events as sorted "KIND:id" strings, then forgotten.
 *
 * @return list<string>
 */
function announced(): array
{
    $events = test()->received->map(fn (ResourceChanged $event) => $event->kind->value.':'.$event->resourceId)->sort()->values()->all();
    test()->received->splice(0);

    return $events;
}

function tugEvents(Tugboat ...$tugboats): array
{
    return collect($tugboats)->map(fn (Tugboat $tugboat) => ResourceKind::Tug->value.':'.$tugboat->id)->sort()->values()->all();
}

function pilotEvents(int ...$userIds): array
{
    return collect($userIds)->map(fn (int $id) => ResourceKind::Pilot->value.':'.$id)->sort()->values()->all();
}

it('announces a tug only when its status, power or capacity changes', function (array $change, bool $sends) {
    $tugboat = Tugboat::factory()->create(['tug_power' => '60.50', 'capacity' => null]);

    $this->actingAs($this->tugManager, 'web')
        ->patchJson("/api/v1/tugboats/{$tugboat->id}", [...$change, 'version' => VersionToken::of($tugboat)])
        ->assertOk();

    expect(announced())->toBe($sends ? tugEvents($tugboat) : []);
})->with([
    'status' => [['status' => 'MAINTENANCE'], true],
    'power' => [['tug_power' => '70'], true],
    'capacity' => [['capacity' => '12'], true],
    'name only' => [['name' => 'Renamed'], false],
    'same power written another way' => [['tug_power' => '60.5'], false],
]);

it('announces nothing for a new tug, pilot or tug master', function () {
    $tugboat = Tugboat::factory()->create();

    $this->actingAs($this->tugManager, 'web')
        ->postJson('/api/v1/tugboats', ['name' => 'Horus 9', 'tug_power' => '60', 'tug_power_unit' => 'BOLLARD_PULL_TONNES'])
        ->assertCreated();
    $this->actingAs($this->tugManager, 'web')
        ->postJson('/api/v1/tug-masters', ['name' => 'Karim', 'username' => 'karim.f7', 'tugboat_id' => $tugboat->id])
        ->assertCreated();
    $this->actingAs($this->pilotageManager, 'web')
        ->postJson('/api/v1/pilots', ['name' => 'Omar', 'username' => 'omar.f7', 'pilot_rank' => 'THIRD'])
        ->assertCreated();

    expect(announced())->toBe([]);
});

it('announces a pilot for a rank change, deactivation and activation, not for a name or phone', function () {
    $pilot = makePilot(PilotRank::Third);
    $send = fn (string $method, string $suffix, array $body) => $this->actingAs($this->pilotageManager, 'web')
        ->json($method, "/api/v1/pilots/{$pilot->user_id}{$suffix}", [...$body, 'version' => VersionToken::of($pilot->refresh())])
        ->assertOk();

    $send('PATCH', '', ['name' => 'New name', 'phone' => '+201000000009']);
    expect(announced())->toBe([]);

    $send('PATCH', '', ['pilot_rank' => 'SECOND']);
    expect(announced())->toBe(pilotEvents($pilot->user_id));

    $send('POST', '/deactivate', []);
    expect(announced())->toBe(pilotEvents($pilot->user_id));

    $send('POST', '/deactivate', []);
    expect(announced())->toBe([]);

    $send('POST', '/activate', []);
    expect(announced())->toBe(pilotEvents($pilot->user_id));
});

it('announces the old and the new tug on a link, a move and an unlink', function () {
    [$first, $second] = Tugboat::factory()->count(2)->create();
    $master = makeTugMaster();
    $link = fn (?int $tugboatId) => $this->actingAs($this->tugManager, 'web')
        ->putJson("/api/v1/tug-masters/{$master->user_id}/tugboat", ['tugboat_id' => $tugboatId, 'version' => VersionToken::of($master->refresh())])
        ->assertOk();

    $link($first->id);
    expect(announced())->toBe(tugEvents($first));

    $link($first->id);
    expect(announced())->toBe([]);

    $link($second->id);
    expect(announced())->toBe(tugEvents($first, $second));

    $link(null);
    expect(announced())->toBe(tugEvents($second));
});

it('announces the tug of a deactivated tug master, nothing on activation without a tug or for a name', function () {
    $tugboat = Tugboat::factory()->create();
    $master = makeTugMaster($tugboat);
    $send = fn (string $method, string $suffix, array $body) => $this->actingAs($this->tugManager, 'web')
        ->json($method, "/api/v1/tug-masters/{$master->user_id}{$suffix}", [...$body, 'version' => VersionToken::of($master->refresh())])
        ->assertOk();

    $send('PATCH', '', ['name' => 'New name']);
    expect(announced())->toBe([]);

    $send('POST', '/deactivate', []);
    expect(announced())->toBe(tugEvents($tugboat));

    $send('POST', '/activate', []);
    expect(announced())->toBe([]);
});

it('announces each pilot once for added and removed schedule entries', function () {
    [$first, $second] = [makePilot(), makePilot()];
    $shift = PortShift::factory()->create();
    $entry = fn (int $userId, int $days) => ['user_id' => $userId, 'shift_date' => now()->addDays($days)->toDateString(), 'port_shift_id' => $shift->id];

    $this->actingAs($this->pilotageManager, 'web')
        ->postJson('/api/v1/pilot-schedules', ['entries' => [$entry($first->user_id, 3), $entry($first->user_id, 4), $entry($second->user_id, 3)]])
        ->assertCreated();
    expect(announced())->toBe(pilotEvents($first->user_id, $second->user_id));

    $schedule = PilotSchedule::query()->where('pilot_user_id', $first->user_id)->firstOrFail();
    $this->actingAs($this->pilotageManager, 'web')->deleteJson("/api/v1/pilot-schedules/{$schedule->id}")->assertNoContent();

    expect(announced())->toBe(pilotEvents($first->user_id));
});

it('announces the tug once for tug master schedule entries, and nothing for a tug master without a tug', function () {
    $tugboat = Tugboat::factory()->create();
    [$linked, $unlinked] = [makeTugMaster($tugboat), makeTugMaster()];
    $shift = PortShift::factory()->create();
    $entry = fn (int $userId, int $days) => ['user_id' => $userId, 'shift_date' => now()->addDays($days)->toDateString(), 'port_shift_id' => $shift->id];

    $this->actingAs($this->tugManager, 'web')
        ->postJson('/api/v1/tug-master-schedules', ['entries' => [$entry($linked->user_id, 3), $entry($linked->user_id, 4), $entry($unlinked->user_id, 3)]])
        ->assertCreated();
    expect(announced())->toBe(tugEvents($tugboat));

    $schedule = fn (int $userId) => TugMasterSchedule::query()->where('tug_master_user_id', $userId)->firstOrFail();

    $this->actingAs($this->tugManager, 'web')->deleteJson('/api/v1/tug-master-schedules/'.$schedule($unlinked->user_id)->id)->assertNoContent();
    expect(announced())->toBe([]);

    $this->actingAs($this->tugManager, 'web')->deleteJson('/api/v1/tug-master-schedules/'.$schedule($linked->user_id)->id)->assertNoContent();
    expect(announced())->toBe(tugEvents($tugboat));
});

it('announces nothing for a refused schedule batch', function () {
    $pilot = makePilot();
    $shift = PortShift::factory()->create();
    $good = ['user_id' => $pilot->user_id, 'shift_date' => now()->addDays(3)->toDateString(), 'port_shift_id' => $shift->id];

    $this->actingAs($this->pilotageManager, 'web')
        ->postJson('/api/v1/pilot-schedules', ['entries' => [$good, $good]])
        ->assertStatus(422);

    expect(announced())->toBe([]);
});

dataset('leaving through /users', [
    'deactivate' => ['deactivate', []],
    'replace the role' => ['roles', ['role' => 'PORT_STAFF']],
    'end the role' => ['roles/end', []],
]);

function postUser(int $userId, string $suffix, array $body = [])
{
    $version = VersionToken::of(User::query()->findOrFail($userId));

    return test()->actingAs(test()->admin, 'web')->postJson("/api/v1/users/{$userId}/{$suffix}", [...$body, 'version' => $version]);
}

it('announces a pilot who leaves through /users', function (string $suffix, array $body) {
    $pilot = makePilot();
    $this->travel(1)->seconds();

    postUser($pilot->user_id, $suffix, $body)->assertOk();

    expect(announced())->toBe(pilotEvents($pilot->user_id));
})->with('leaving through /users');

it('announces the tug of a tug master who leaves through /users, and nothing without a tug', function (string $suffix, array $body) {
    $tugboat = Tugboat::factory()->create();
    $linked = makeTugMaster($tugboat);
    $unlinked = makeTugMaster();
    $this->travel(1)->seconds();

    postUser($linked->user_id, $suffix, $body)->assertOk();
    expect(announced())->toBe(tugEvents($tugboat));

    postUser($unlinked->user_id, $suffix, $body)->assertOk();
    expect(announced())->toBe([]);
})->with('leaving through /users');

it('announces a pilot who is activated again through /users, once', function () {
    $pilot = makePilot(attributes: ['is_active' => false]);

    postUser($pilot->user_id, 'activate')->assertOk();
    expect(announced())->toBe(pilotEvents($pilot->user_id));

    postUser($pilot->user_id, 'activate')->assertOk();
    expect(announced())->toBe([]);
});

it('announces a pilot who is given the role again, only while active', function (bool $active) {
    $pilot = makePilot(attributes: ['is_active' => $active]);
    moveUserToHarbourRole($pilot->user, Role::PortStaff);

    postUser($pilot->user_id, 'roles', ['role' => 'MARINE_PILOT'])->assertOk();

    expect(announced())->toBe($active ? pilotEvents($pilot->user_id) : []);
})->with(['active' => true, 'inactive' => false]);

it('announces nothing for the same role again, a user without a pilot profile or an activated tug master', function () {
    $pilot = makePilot();
    $newPilot = makeUserWithRole(Role::PortStaff);
    $master = makeTugMaster(attributes: ['is_active' => false]);
    $this->travel(1)->seconds();

    postUser($pilot->user_id, 'roles', ['role' => 'MARINE_PILOT'])->assertOk();
    postUser($newPilot->id, 'roles', ['role' => 'MARINE_PILOT'])->assertOk();
    postUser($master->user_id, 'activate')->assertOk();

    expect(announced())->toBe([]);
});

it('announces nothing and saves nothing when a /users activation rolls back', function () {
    $pilot = makePilot(attributes: ['is_active' => false]);

    app()->bind(RoleStartHandler::class, fn () => new class implements RoleStartHandler
    {
        public function roleStarted(int $userId, Role $role, int $actorId): void
        {
            ResourceChanged::dispatch(ResourceKind::Pilot, $userId, $actorId);

            throw new RuntimeException('failed after announcing');
        }
    });

    postUser($pilot->user_id, 'activate')->assertStatus(500);

    expect(announced())->toBe([])
        ->and($pilot->user->refresh()->is_active)->toBeFalse();
});

it('announces every active pilot of a rank when its limit changes, and nothing when it does not', function () {
    PilotRankLimit::query()->delete();
    foreach (['FIRST' => null, 'SECOND' => '300', 'THIRD' => '200'] as $rank => $loa) {
        PilotRankLimit::query()->create(['pilot_rank' => $rank, 'max_vessel_loa' => $loa]);
    }
    [$one, $two] = [makePilot(PilotRank::Second), makePilot(PilotRank::Second)];
    makePilot(PilotRank::Third);
    makePilot(PilotRank::Second, ['is_active' => false]);
    moveUserToHarbourRole(makePilot(PilotRank::Second)->user, Role::PortStaff);
    $change = fn (string $loa) => $this->actingAs($this->pilotageManager, 'web')
        ->patchJson('/api/v1/pilot-rank-limits/SECOND', ['max_vessel_loa' => $loa, 'version' => VersionToken::of(PilotRankLimit::query()->find('SECOND'))]);

    $change('300.00')->assertOk();
    expect(announced())->toBe([]);

    $change('100')->assertStatus(422);
    expect(announced())->toBe([]);

    $change('250')->assertOk();
    expect(announced())->toBe(pilotEvents($one->user_id, $two->user_id));
});

it('announces new equipment and a change of quantities or capabilities, not an identical update', function () {
    $terminal = Terminal::factory()->create(['terminal_type' => TerminalType::Container, 'is_active' => true]);
    $unit = OrganizationUnit::query()->where('terminal_id', $terminal->id)->first()
        ?? OrganizationUnit::factory()->create(['terminal_id' => $terminal->id]);
    $officer = makeUserWithRole(Role::TerminalAdmin, unit: $unit);
    $capability = CapabilityType::factory()->create();
    $path = "/api/v1/terminals/{$terminal->id}/equipment";
    $body = ['quantity' => 5, 'maintenance_quantity' => 1, 'blocked_quantity' => 0, 'capabilities' => [['capability_type_id' => $capability->id, 'value' => 65]]];

    $created = $this->actingAs($officer, 'web')
        ->postJson($path, [...$body, 'equipment_category_id' => EquipmentCategory::factory()->create()->id])
        ->assertCreated();
    $id = $created->json('id');
    expect(announced())->toBe([ResourceKind::Equipment->value.':'.$id]);

    $version = $created->json('version');
    $update = function (array $change) use ($officer, $path, $id, $body, &$version) {
        $version = $this->actingAs($officer, 'web')->putJson("{$path}/{$id}", [...$body, ...$change, 'version' => $version])->assertOk()->json('version');
    };

    $update([]);
    expect(announced())->toBe([]);

    $update(['maintenance_quantity' => 2]);
    expect(announced())->toBe([ResourceKind::Equipment->value.':'.$id]);

    $update(['maintenance_quantity' => 2, 'capabilities' => [['capability_type_id' => $capability->id, 'value' => 70]]]);
    expect(announced())->toBe([ResourceKind::Equipment->value.':'.$id]);
});

it('announces nothing for a forbidden or stale request', function () {
    $tugboat = Tugboat::factory()->create();
    $pilot = makePilot();

    $this->actingAs($this->pilotageManager, 'web')
        ->patchJson("/api/v1/tugboats/{$tugboat->id}", ['status' => 'MAINTENANCE', 'version' => VersionToken::of($tugboat)])
        ->assertStatus(403);
    $this->actingAs($this->tugManager, 'web')
        ->patchJson("/api/v1/tugboats/{$tugboat->id}", ['status' => 'MAINTENANCE', 'version' => '2026-01-01T00:00:00.000000Z'])
        ->assertStatus(409);
    $this->actingAs($this->admin, 'web')
        ->postJson("/api/v1/users/{$pilot->user_id}/deactivate", ['version' => '2026-01-01T00:00:00.000000Z'])
        ->assertStatus(409);

    expect(announced())->toBe([]);
});

it('announces both the tug and the pilot when a linked tug master is given the pilot role', function () {
    $tugboat = Tugboat::factory()->create();
    $master = makeTugMaster($tugboat);
    PilotProfile::query()->create(['user_id' => $master->user_id, 'pilot_rank' => PilotRank::Third]);
    $this->travel(1)->seconds();

    postUser($master->user_id, 'roles', ['role' => 'MARINE_PILOT'])->assertOk();

    expect(announced())->toBe([...pilotEvents($master->user_id), ...tugEvents($tugboat)]);
});

it('announces nothing for a refused tug link or equipment on a terminal that is not a container terminal', function () {
    $tugboat = Tugboat::factory()->create();
    makeTugMaster($tugboat);
    $other = makeTugMaster(Tugboat::factory()->create());

    $this->actingAs($this->tugManager, 'web')
        ->putJson("/api/v1/tug-masters/{$other->user_id}/tugboat", ['tugboat_id' => $tugboat->id, 'version' => VersionToken::of($other)])
        ->assertStatus(422);

    $terminal = Terminal::factory()->create(['terminal_type' => TerminalType::NonContainer, 'is_active' => true]);
    $unit = OrganizationUnit::query()->where('terminal_id', $terminal->id)->first()
        ?? OrganizationUnit::factory()->create(['terminal_id' => $terminal->id]);
    $this->actingAs(makeUserWithRole(Role::TerminalAdmin, unit: $unit), 'web')
        ->postJson("/api/v1/terminals/{$terminal->id}/equipment", [
            'equipment_category_id' => EquipmentCategory::factory()->create()->id,
            'quantity' => 5, 'maintenance_quantity' => 0, 'blocked_quantity' => 0,
            'capabilities' => [['capability_type_id' => CapabilityType::factory()->create()->id, 'value' => 65]],
        ])
        ->assertStatus(422);

    expect(announced())->toBe([]);
});

it('announces nothing when /users deactivates a pilot who is already inactive', function () {
    $pilot = makePilot(attributes: ['is_active' => false]);

    postUser($pilot->user_id, 'deactivate')->assertOk();

    expect(announced())->toBe([]);
});
