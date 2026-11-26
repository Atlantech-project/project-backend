<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\NavigationChannel;
use App\Modules\MasterData\Models\SafetyConstraint;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Recommendation\Models\RecommendationOption;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-F2 berths (FR-MD-04, FR-MD-05, DEC-51): measurements as the CHECKs, at least one active terminal on a new link,
 * the channel datum, the terminal list audited and moving the version, 409 for a pair in use, status changes as no-ops
 * when unchanged, and the terminal scope on reads (Q4). Rolled back after each test.
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

function berthBody(array $terminalIds, array $overrides = []): array
{
    return [
        'code' => 'B-01',
        'name' => 'Berth One',
        'length' => 320,
        'water_depth' => 14.5,
        'depth_datum' => 'LAT',
        'max_vessel_loa' => 300,
        'latitude' => 31.4123,
        'longitude' => 31.8123,
        'terminal_ids' => $terminalIds,
        ...$overrides,
    ];
}

function linkedBerth(Terminal ...$terminals): Berth
{
    $berth = Berth::factory()->create(['length' => 320, 'max_vessel_loa' => 300, 'depth_datum' => 'LAT']);
    $berth->terminals()->attach(array_map(fn (Terminal $terminal) => $terminal->id, $terminals));

    return $berth->fresh();
}

it('creates a shared berth linked to two terminals and audits it with the terminal ids', function () {
    [$first, $second] = Terminal::factory()->count(2)->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/v1/berths', berthBody([$second->id, $first->id]))
        ->assertCreated()
        ->assertJsonPath('length', 320)
        ->assertJsonPath('status', 'ACTIVE')
        ->assertJsonCount(2, 'terminals');

    $row = AuditLog::query()->where('entity_type', 'berths')->sole();

    expect($row->action)->toBe(AuditLogAction::Create)
        ->and($row->entity_id)->toBe($response->json('id'))
        ->and($row->after_state['terminal_ids'])->toBe([$first->id, $second->id]);
});

it('refuses bad measurements, no terminal and an unknown terminal with 422', function (array $overrides, string $field) {
    $terminal = Terminal::factory()->create();

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/berths', berthBody([$terminal->id], $overrides))
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => [$field]]);

    expect(Berth::query()->count())->toBe(0);
})->with([
    'LOA longer than the berth' => [['max_vessel_loa' => 321], 'max_vessel_loa'],
    'zero depth' => [['water_depth' => 0], 'water_depth'],
    'length too large' => [['length' => 100000], 'length'],
    'three decimals' => [['length' => 320.555], 'length'],
    'latitude out of range' => [['latitude' => 91], 'latitude'],
    'no terminals' => [['terminal_ids' => []], 'terminal_ids'],
    'unknown terminal' => [['terminal_ids' => [999999]], 'terminal_ids.0'],
]);

it('refuses a new link to an inactive terminal', function () {
    $inactive = Terminal::factory()->create(['is_active' => false]);

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/berths', berthBody([$inactive->id]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.terminal_ids', ['errors.berths.terminal_inactive']);
});

it('compares the depth datum with every active channel on create and on update', function () {
    $terminal = Terminal::factory()->create();
    NavigationChannel::factory()->create(['depth_datum' => 'LAT']);
    NavigationChannel::factory()->create(['depth_datum' => 'MSL', 'is_active' => false]);

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/berths', berthBody([$terminal->id], ['depth_datum' => 'CD']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.depth_datum', ['errors.berths.datum_mismatch']);

    $id = $this->actingAs($this->admin, 'web')->postJson('/api/v1/berths', berthBody([$terminal->id]))->assertCreated()->json('id');
    $berth = Berth::query()->findOrFail($id);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$id}", ['depth_datum' => 'CD', 'version' => VersionToken::of($berth)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.depth_datum', ['errors.berths.datum_mismatch']);
});

it('compares a sent LOA with the stored length on update, and refuses code and status', function () {
    $berth = linkedBerth(Terminal::factory()->create());

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['max_vessel_loa' => 330, 'version' => VersionToken::of($berth)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.max_vessel_loa', ['errors.berths.loa_exceeds_length']);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['code' => 'NEW', 'status' => 'INACTIVE', 'version' => VersionToken::of($berth)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.code', ['validation.prohibited'])
        ->assertJsonPath('errors.status', ['validation.prohibited']);

    expect((float) $berth->fresh()->max_vessel_loa)->toBe(300.0);
});

it('moves the version and audits the terminal list when only the terminals change', function () {
    [$first, $second] = Terminal::factory()->count(2)->create();
    $berth = linkedBerth($first);
    $before = VersionToken::of($berth);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['terminal_ids' => [$first->id, $second->id], 'version' => $before])
        ->assertOk()
        ->assertJsonCount(2, 'terminals')
        ->assertJsonPath('version', fn (string $version) => $version !== $before);

    $row = AuditLog::query()->where('entity_type', 'berths')->sole();

    expect($row->before_state)->toBe(['terminal_ids' => [$first->id]])
        ->and($row->after_state)->toBe(['terminal_ids' => [$first->id, $second->id]]);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['terminal_ids' => [$first->id], 'version' => $before])
        ->assertConflict()
        ->assertJsonPath('code', 'VERSION_CONFLICT');
});

it('audits the terminal list too when the measurements change in the same request', function () {
    [$first, $second] = Terminal::factory()->count(2)->create();
    $berth = linkedBerth($first);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['name' => 'Renamed', 'terminal_ids' => [$second->id], 'version' => VersionToken::of($berth)])
        ->assertOk();

    $rows = AuditLog::query()->where('entity_type', 'berths')->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->after_state)->toMatchArray(['name' => 'Renamed'])
        ->and($rows[0]->after_state)->not->toHaveKey('terminal_ids')
        ->and($rows[1]->after_state)->toBe(['terminal_ids' => [$second->id]]);
});

it('keeps an existing link to a terminal that became inactive, but refuses adding a new inactive one', function () {
    $active = Terminal::factory()->create();
    $deactivated = Terminal::factory()->create();
    $berth = linkedBerth($deactivated);
    $deactivated->update(['is_active' => false]);
    $newInactive = Terminal::factory()->create(['is_active' => false]);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['name' => 'Still editable', 'terminal_ids' => [$deactivated->id, $active->id], 'version' => VersionToken::of($berth)])
        ->assertOk();

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['terminal_ids' => [$deactivated->id, $newInactive->id], 'version' => VersionToken::of($berth->fresh())])
        ->assertUnprocessable()
        ->assertJsonPath('errors.terminal_ids', ['errors.berths.terminal_inactive']);
});

it('refuses removing a terminal a safety constraint uses with 409 CONFLICT, audited, and changes nothing', function () {
    [$first, $second] = Terminal::factory()->count(2)->create();
    $berth = linkedBerth($first, $second);
    SafetyConstraint::factory()->create(['berth_id' => $berth->id, 'terminal_id' => $second->id]);
    $before = VersionToken::of($berth);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['name' => 'Not saved', 'terminal_ids' => [$first->id], 'version' => $before])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $row = AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->sole();

    expect($row->entity_type)->toBe('berths')
        ->and($row->after_state)->toMatchArray(['status' => 409, 'code' => 'CONFLICT', 'reason' => 'terminal_in_use'])
        ->and($berth->fresh()->terminals()->count())->toBe(2)
        ->and($berth->fresh()->name)->not->toBe('Not saved')
        ->and(VersionToken::of($berth->fresh()))->toBe($before);
});

it('changes the status with its own endpoints; the same status again is a no-op', function () {
    $berth = linkedBerth(Terminal::factory()->create());

    $this->actingAs($this->admin, 'web')
        ->postJson("/api/v1/berths/{$berth->id}/maintenance", ['version' => VersionToken::of($berth)])
        ->assertOk()
        ->assertJsonPath('status', 'MAINTENANCE');

    $version = VersionToken::of($berth->fresh());
    $rows = AuditLog::query()->count();

    $this->actingAs($this->admin, 'web')
        ->postJson("/api/v1/berths/{$berth->id}/maintenance", ['version' => $version])
        ->assertOk()
        ->assertJsonPath('version', $version);

    expect(AuditLog::query()->count())->toBe($rows);

    $this->actingAs($this->admin, 'web')
        ->postJson("/api/v1/berths/{$berth->id}/deactivate", ['version' => $version])
        ->assertOk()
        ->assertJsonPath('status', 'INACTIVE');

    $this->actingAs($this->admin, 'web')
        ->postJson("/api/v1/berths/{$berth->id}/activate", ['version' => VersionToken::of($berth->fresh())])
        ->assertOk()
        ->assertJsonPath('status', 'ACTIVE');
});

it('shows a terminal-scoped user only the berths of their terminal, shared berths included', function () {
    $own = OrganizationUnit::factory()->create();
    $other = OrganizationUnit::factory()->create();
    $ownTerminal = Terminal::query()->findOrFail($own->terminal_id);
    $otherTerminal = Terminal::query()->findOrFail($other->terminal_id);
    $ownOnly = linkedBerth($ownTerminal);
    $shared = linkedBerth($ownTerminal, $otherTerminal);
    $otherOnly = linkedBerth($otherTerminal);
    $user = makeUserWithRole(Role::TerminalAdmin, unit: $own);

    $ids = $this->actingAs($user, 'web')->getJson('/api/v1/berths')->assertOk()->json('data.*.id');
    expect($ids)->toEqualCanonicalizing([$ownOnly->id, $shared->id]);

    $this->actingAs($user, 'web')->getJson("/api/v1/berths/{$shared->id}")->assertOk();
    $this->actingAs($user, 'web')->getJson("/api/v1/berths/{$otherOnly->id}")->assertForbidden();
    $this->actingAs($user, 'web')->getJson("/api/v1/berths?terminal_id={$otherTerminal->id}")->assertForbidden();

    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')->getJson("/api/v1/berths?terminal_id={$otherTerminal->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('answers 403 to writes from every role except the Port Admin', function () {
    $terminal = Terminal::factory()->create();

    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')->postJson('/api/v1/berths', berthBody([$terminal->id]))->assertForbidden();
});

it('refuses removing a terminal a recommendation option uses with 409 CONFLICT', function () {
    [$first, $second] = Terminal::factory()->count(2)->create();
    $berth = linkedBerth($first, $second);
    $option = RecommendationOption::factory()->make(['berth_id' => $berth->id, 'terminal_id' => $second->id])->getAttributes();
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    DB::table('recommendation_options')->insert(collect($option)->map(fn ($value) => $value instanceof BackedEnum ? $value->value : $value)->all());
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['terminal_ids' => [$first->id], 'version' => VersionToken::of($berth)])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    expect($berth->fresh()->terminals()->count())->toBe(2);
});
