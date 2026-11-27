<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\ChannelWaypoint;
use App\Modules\MasterData\Models\NavigationChannel;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-F1 navigation channels (FR-MD-02, P-26): the port adds as many as it has, only the Port Admin writes, an active
 * channel keeps the depth datum of every berth, and nothing is deleted. Rolled back after each test.
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

function channelBody(array $overrides = []): array
{
    return [
        'name' => 'Main Channel',
        'depth' => 17.5,
        'depth_datum' => 'LAT',
        'width' => 300,
        'speed_limit_knots' => 8,
        ...$overrides,
    ];
}

function channelRow(array $overrides = []): NavigationChannel
{
    return NavigationChannel::factory()->create([...channelBody(), ...$overrides])->refresh();
}

it('creates more than one channel, active by default, and audits each', function () {
    $this->actingAs($this->admin, 'web')->postJson('/api/v1/navigation-channels', channelBody())
        ->assertCreated()
        ->assertJsonPath('name', 'Main Channel')
        ->assertJsonPath('depth', 17.5)
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('waypoints', []);
    $this->actingAs($this->admin, 'web')->postJson('/api/v1/navigation-channels', channelBody(['name' => 'East Channel']))
        ->assertCreated();

    expect(NavigationChannel::query()->count())->toBe(2)
        ->and(AuditLog::query()->where('action', AuditLogAction::Create)->where('entity_type', 'navigation_channels')->count())->toBe(2);
});

it('lists channels by name with waypoints in order, to an office role, filtered by is_active', function () {
    $main = channelRow();
    channelRow(['name' => 'Closed Channel', 'is_active' => false]);
    ChannelWaypoint::factory()->create(['channel_id' => $main->id, 'sequence_order' => 2, 'label' => 'Berth Turn']);
    ChannelWaypoint::factory()->create(['channel_id' => $main->id, 'sequence_order' => 1, 'label' => 'Fairway Buoy']);

    $reader = makeUserWithRole(Role::PortStaff);

    $this->actingAs($reader, 'web')->getJson('/api/v1/navigation-channels')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.name', 'Closed Channel')
        ->assertJsonPath('data.1.waypoints.0.label', 'Fairway Buoy')
        ->assertJsonPath('data.1.waypoints.1.label', 'Berth Turn');
    $this->actingAs($reader, 'web')->getJson('/api/v1/navigation-channels?is_active=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Main Channel');
});

it('refuses invalid values with 422 and creates nothing', function (array $overrides, string $field) {
    channelRow(['name' => 'Taken']);

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/navigation-channels', channelBody($overrides))
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => [$field]]);

    expect(NavigationChannel::query()->count())->toBe(1);
})->with([
    'duplicate name' => [['name' => 'Taken'], 'name'],
    'zero depth' => [['depth' => 0], 'depth'],
    'depth with 3 decimals' => [['depth' => 17.555], 'depth'],
    'negative width' => [['width' => -1], 'width'],
    'speed with 2 decimals' => [['speed_limit_knots' => 8.25], 'speed_limit_knots'],
    'speed too large' => [['speed_limit_knots' => 1000], 'speed_limit_knots'],
    'missing datum' => [['depth_datum' => ''], 'depth_datum'],
]);

it('refuses an active channel whose datum differs from any berth, even an inactive one', function (array $berthState) {
    Berth::factory()->create(['depth_datum' => 'LAT', ...$berthState]);

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/navigation-channels', channelBody(['depth_datum' => 'MSL']))
        ->assertStatus(422)
        ->assertJsonPath('errors.depth_datum.0', 'errors.navigation_channels.datum_mismatch');

    expect(NavigationChannel::query()->count())->toBe(0);
})->with([
    'active berth' => [[]],
    'inactive berth' => [['status' => 'INACTIVE']],
]);

it('refuses to change the datum of an active channel, or to activate a channel with another datum', function () {
    Berth::factory()->create(['depth_datum' => 'LAT']);
    $active = channelRow();
    $off = channelRow(['name' => 'Old Channel', 'depth_datum' => 'MSL', 'is_active' => false]);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/navigation-channels/{$active->id}", ['depth_datum' => 'MSL', 'version' => VersionToken::of($active)])
        ->assertStatus(422);
    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/navigation-channels/{$off->id}", ['is_active' => true, 'version' => VersionToken::of($off)])
        ->assertStatus(422);

    expect($active->fresh()->depth_datum)->toBe('LAT')
        ->and($off->fresh()->is_active)->toBeFalse();
});

it('edits an inactive channel whose datum differs, and other fields of a channel', function () {
    Berth::factory()->create(['depth_datum' => 'LAT']);
    $off = channelRow(['name' => 'Old Channel', 'depth_datum' => 'MSL', 'is_active' => false]);

    $response = $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/navigation-channels/{$off->id}", ['depth' => 12.25, 'version' => VersionToken::of($off)])
        ->assertOk()
        ->assertJsonPath('depth', 12.25)
        ->assertJsonPath('name', 'Old Channel')
        ->assertJsonPath('depth_datum', 'MSL');

    $row = AuditLog::query()->where('action', AuditLogAction::Update)->where('entity_type', 'navigation_channels')->sole();

    expect($response->json('version'))->toBe(VersionToken::of($off->fresh()))
        ->and((float) $row->before_state['depth'])->toBe(17.5)
        ->and((float) $row->after_state['depth'])->toBe(12.25);
});

it('answers 409 VERSION_CONFLICT to a stale version', function () {
    $channel = channelRow();
    $stale = VersionToken::of($channel);
    $this->actingAs($this->admin, 'web')->putJson("/api/v1/navigation-channels/{$channel->id}", ['width' => 310, 'version' => $stale])->assertOk();

    $this->actingAs($this->admin, 'web')->putJson("/api/v1/navigation-channels/{$channel->id}", ['width' => 320, 'version' => $stale])
        ->assertStatus(409)
        ->assertJsonPath('code', 'VERSION_CONFLICT');

    expect((float) $channel->fresh()->width)->toBe(310.0);
});

it('lets only the Port Admin write', function (Role $role) {
    $channel = channelRow();

    $this->actingAs(makeUserWithRole($role), 'web')->postJson('/api/v1/navigation-channels', channelBody(['name' => 'New']))
        ->assertForbidden();
    $this->actingAs(makeUserWithRole($role), 'web')
        ->putJson("/api/v1/navigation-channels/{$channel->id}", ['width' => 1, 'version' => VersionToken::of($channel)])
        ->assertForbidden();

    expect(NavigationChannel::query()->count())->toBe(1);
})->with([Role::PortStaff, Role::MarineSafetyOfficer]);

it('answers 401 to a guest and 404 for an unknown channel', function () {
    $this->getJson('/api/v1/navigation-channels')->assertUnauthorized();
    $this->actingAs($this->admin, 'web')->putJson('/api/v1/navigation-channels/999999', ['width' => 1])->assertNotFound();
});
