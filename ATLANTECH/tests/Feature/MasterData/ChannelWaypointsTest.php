<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Models\ChannelWaypoint;
use App\Modules\MasterData\Models\NavigationChannel;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-F1 channel waypoints (FR-MD-02, P-26 "add / reorder / remove"): the whole list is saved under the channel's version,
 * the position gives the order, and the audit row of the channel keeps the old list. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $this->admin = makeUserWithRole(Role::PortGm);
    $this->channel = NavigationChannel::factory()->create()->refresh();
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function waypoint(string $label, float $latitude = 31.5, float $longitude = 31.8): array
{
    return ['label' => $label, 'latitude' => $latitude, 'longitude' => $longitude];
}

function waypointsUrl(NavigationChannel $channel): string
{
    return "/api/v1/navigation-channels/{$channel->id}/waypoints";
}

/**
 * @return list<string> "order:label" in order.
 */
function storedWaypoints(NavigationChannel $channel): array
{
    return ChannelWaypoint::query()->where('channel_id', $channel->id)->orderBy('sequence_order')->get()
        ->map(fn (ChannelWaypoint $point) => $point->sequence_order.':'.$point->label)->all();
}

it('saves the list in the order sent, moves the channel version and audits the old and new lists', function () {
    $before = VersionToken::of($this->channel);

    $response = $this->actingAs($this->admin, 'web')->putJson(waypointsUrl($this->channel), [
        'waypoints' => [waypoint('Fairway Buoy', 31.512345, 31.812345), waypoint('Breakwater')],
        'version' => $before,
    ])->assertOk()
        ->assertJsonPath('waypoints.0.sequence_order', 1)
        ->assertJsonPath('waypoints.0.label', 'Fairway Buoy')
        ->assertJsonPath('waypoints.0.latitude', 31.512345)
        ->assertJsonPath('waypoints.1.label', 'Breakwater');

    $row = AuditLog::query()->where('action', AuditLogAction::Update)->where('entity_type', 'navigation_channels')->sole();

    expect($response->json('version'))->toBe(VersionToken::of($this->channel->fresh()))->not->toBe($before)
        ->and($row->entity_id)->toBe($this->channel->id)
        ->and($row->before_state['waypoints'])->toBe([])
        ->and(array_column($row->after_state['waypoints'], 'label'))->toBe(['Fairway Buoy', 'Breakwater']);
});

it('reorders, adds and removes by sending the new list', function () {
    foreach (['A', 'B', 'C'] as $index => $label) {
        ChannelWaypoint::factory()->create(['channel_id' => $this->channel->id, 'sequence_order' => $index + 1, 'label' => $label]);
    }
    $other = NavigationChannel::factory()->create();
    ChannelWaypoint::factory()->create(['channel_id' => $other->id, 'sequence_order' => 1, 'label' => 'A']);

    $this->actingAs($this->admin, 'web')->putJson(waypointsUrl($this->channel), [
        'waypoints' => [waypoint('C'), waypoint('A'), waypoint('D')],
        'version' => VersionToken::of($this->channel),
    ])->assertOk();

    $row = AuditLog::query()->where('action', AuditLogAction::Update)->where('entity_type', 'navigation_channels')->sole();

    expect(storedWaypoints($this->channel))->toBe(['1:C', '2:A', '3:D'])
        ->and(storedWaypoints($other))->toBe(['1:A'])
        ->and(array_column($row->before_state['waypoints'], 'label'))->toBe(['A', 'B', 'C']);
});

it('removes every waypoint with an empty list', function () {
    ChannelWaypoint::factory()->create(['channel_id' => $this->channel->id, 'sequence_order' => 1]);

    $this->actingAs($this->admin, 'web')->putJson(waypointsUrl($this->channel), ['waypoints' => [], 'version' => VersionToken::of($this->channel)])
        ->assertOk()
        ->assertJsonPath('waypoints', []);

    expect(storedWaypoints($this->channel))->toBe([]);
});

it('writes nothing when the list is the same', function () {
    $this->actingAs($this->admin, 'web')->putJson(waypointsUrl($this->channel), ['waypoints' => [waypoint('A')], 'version' => VersionToken::of($this->channel)])->assertOk();
    $version = VersionToken::of($this->channel->fresh());

    $this->actingAs($this->admin, 'web')->putJson(waypointsUrl($this->channel), ['waypoints' => [waypoint('A')], 'version' => $version])
        ->assertOk()
        ->assertJsonPath('version', $version);

    expect(AuditLog::query()->where('action', AuditLogAction::Update)->count())->toBe(1);
});

it('refuses an invalid list with 422 and keeps the old one', function (array $body, string $field) {
    ChannelWaypoint::factory()->create(['channel_id' => $this->channel->id, 'sequence_order' => 1, 'label' => 'Old']);

    $response = $this->actingAs($this->admin, 'web')
        ->putJson(waypointsUrl($this->channel), [...$body, 'version' => VersionToken::of($this->channel)])
        ->assertStatus(422);

    expect(array_keys($response->json('errors')))->toContain($field)
        ->and(storedWaypoints($this->channel))->toBe(['1:Old']);
})->with([
    'no list' => [[], 'waypoints'],
    'not a list' => [['waypoints' => ['a' => waypoint('A')]], 'waypoints'],
    'duplicate label, other case' => [['waypoints' => [waypoint('Buoy'), waypoint('BUOY')]], 'waypoints.0.label'],
    'missing label' => [['waypoints' => [['latitude' => 1, 'longitude' => 1]]], 'waypoints.0.label'],
    'label too long' => [['waypoints' => [waypoint(str_repeat('a', 61))]], 'waypoints.0.label'],
    'latitude out of range' => [['waypoints' => [waypoint('A', 91)]], 'waypoints.0.latitude'],
    'longitude missing' => [['waypoints' => [['label' => 'A', 'latitude' => 1]]], 'waypoints.0.longitude'],
    'client sends the order' => [['waypoints' => [[...waypoint('A'), 'sequence_order' => 5]]], 'waypoints.0.sequence_order'],
]);

it('answers 409 VERSION_CONFLICT to a stale channel version and keeps the list', function () {
    $stale = VersionToken::of($this->channel);
    $this->actingAs($this->admin, 'web')->putJson(waypointsUrl($this->channel), ['waypoints' => [waypoint('First')], 'version' => $stale])->assertOk();

    $this->actingAs($this->admin, 'web')->putJson(waypointsUrl($this->channel), ['waypoints' => [waypoint('Second')], 'version' => $stale])
        ->assertStatus(409)
        ->assertJsonPath('code', 'VERSION_CONFLICT');

    expect(storedWaypoints($this->channel))->toBe(['1:First']);
});

it('lets only the Port Admin save, refuses a guest, and answers 404 for an unknown channel', function () {
    $body = ['waypoints' => [waypoint('A')], 'version' => VersionToken::of($this->channel)];

    $this->putJson(waypointsUrl($this->channel), $body)->assertUnauthorized();
    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')->putJson(waypointsUrl($this->channel), $body)->assertForbidden();
    $this->actingAs($this->admin, 'web')->putJson('/api/v1/navigation-channels/999999/waypoints', $body)->assertNotFound();

    expect(storedWaypoints($this->channel))->toBe([]);
});
