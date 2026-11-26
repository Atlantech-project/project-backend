<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\SafetyConstraint;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Enums\ResourceKind;
use App\Modules\Shared\Events\ResourceChanged;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
 * P2-F2 step 5 (FR-MD-06, SHARED-01): every berth change reaches `ResourceChanged` listeners after the commit, a terminal
 * (de)activation announces each linked berth, and a no-op or a refused request announces nothing. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    useTestingTransactionManager();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $this->admin = makeUserWithRole(Role::PortGm);

    $this->received = collect();
    Event::listen(ResourceChanged::class, fn (ResourceChanged $event) => $this->received->push($event));
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function announcedBerth(Terminal ...$terminals): Berth
{
    $berth = Berth::factory()->create(['length' => 320, 'max_vessel_loa' => 300, 'depth_datum' => 'LAT']);
    $berth->terminals()->attach(array_map(fn (Terminal $terminal) => $terminal->id, $terminals));

    return $berth->fresh();
}

it('announces a new berth with its id and the actor', function () {
    $terminal = Terminal::factory()->create();

    $id = $this->actingAs($this->admin, 'web')->postJson('/api/v1/berths', [
        'code' => 'B-9', 'length' => 300, 'water_depth' => 12, 'depth_datum' => 'LAT', 'max_vessel_loa' => 250, 'terminal_ids' => [$terminal->id],
    ])->assertCreated()->json('id');

    expect($this->received)->toHaveCount(1)
        ->and($this->received->first()->kind)->toBe(ResourceKind::Berth)
        ->and($this->received->first()->resourceId)->toBe($id)
        ->and($this->received->first()->actorId)->toBe($this->admin->id);
});

it('announces an update of the measurements or of the terminals, and nothing when nothing changed', function () {
    [$first, $second] = Terminal::factory()->count(2)->create();
    $berth = announcedBerth($first);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['name' => $berth->name, 'version' => VersionToken::of($berth)])
        ->assertOk();
    expect($this->received)->toBeEmpty();

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['water_depth' => 15, 'version' => VersionToken::of($berth->fresh())])
        ->assertOk();
    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/berths/{$berth->id}", ['terminal_ids' => [$first->id, $second->id], 'version' => VersionToken::of($berth->fresh())])
        ->assertOk();

    expect($this->received->pluck('resourceId')->all())->toBe([$berth->id, $berth->id]);
});

it('announces nothing for a refused update: pair in use, bad measurement or stale version', function () {
    [$first, $second] = Terminal::factory()->count(2)->create();
    $berth = announcedBerth($first, $second);
    SafetyConstraint::factory()->create(['berth_id' => $berth->id, 'terminal_id' => $second->id]);
    $stale = VersionToken::of($berth);
    DB::table('berths')->where('id', $berth->id)->update(['updated_at' => now()->addSecond()]);
    $current = VersionToken::of($berth->fresh());

    $this->actingAs($this->admin, 'web')->putJson("/api/v1/berths/{$berth->id}", ['terminal_ids' => [$first->id], 'version' => $current])->assertConflict();
    $this->actingAs($this->admin, 'web')->putJson("/api/v1/berths/{$berth->id}", ['max_vessel_loa' => 330, 'version' => $current])->assertUnprocessable();
    $this->actingAs($this->admin, 'web')->putJson("/api/v1/berths/{$berth->id}", ['water_depth' => 15, 'version' => $stale])->assertConflict();

    expect($this->received)->toBeEmpty();
});

it('announces a status change once, and nothing for the same status again', function () {
    $berth = announcedBerth(Terminal::factory()->create());

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/berths/{$berth->id}/maintenance", ['version' => VersionToken::of($berth)])->assertOk();
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/berths/{$berth->id}/maintenance", ['version' => VersionToken::of($berth->fresh())])->assertOk();

    expect($this->received)->toHaveCount(1);
});

it('announces every linked berth when a terminal is deactivated, and nothing on the no-op or a rename', function () {
    [$terminal, $other] = Terminal::factory()->count(2)->create();
    $own = announcedBerth($terminal);
    $shared = announcedBerth($terminal, $other);
    announcedBerth($other);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/terminals/{$terminal->id}", ['name' => 'Renamed terminal', 'version' => VersionToken::of($terminal)])
        ->assertOk();
    expect($this->received)->toBeEmpty();

    $this->actingAs($this->admin, 'web')
        ->postJson("/api/v1/terminals/{$terminal->id}/deactivate", ['version' => VersionToken::of($terminal->fresh())])
        ->assertOk();
    $this->actingAs($this->admin, 'web')
        ->postJson("/api/v1/terminals/{$terminal->id}/deactivate", ['version' => VersionToken::of($terminal->fresh())])
        ->assertOk();

    expect($this->received->pluck('resourceId')->sort()->values()->all())->toBe(collect([$own->id, $shared->id])->sort()->values()->all())
        ->and($this->received->pluck('kind')->unique()->all())->toBe([ResourceKind::Berth]);
});
