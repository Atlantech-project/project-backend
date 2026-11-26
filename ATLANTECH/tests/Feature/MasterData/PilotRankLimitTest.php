<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Enums\PilotRank;
use App\Modules\MasterData\Models\PilotRankLimit;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-F3 rank limits (FR-MD-09, Q2, Q9): LOA only, null = no limit, and FIRST ≥ SECOND ≥ THIRD with null as the largest.
 * Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    PilotRankLimit::query()->delete();
    $this->limits = collect(['FIRST' => null, 'SECOND' => '300', 'THIRD' => '200'])
        ->map(fn (?string $loa, string $rank) => PilotRankLimit::query()->create(['pilot_rank' => $rank, 'max_vessel_loa' => $loa])->refresh());
    $this->manager = makeUserWithRole(Role::PilotageManager);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function patchRankLimit(object $test, string $rank, mixed $loa)
{
    $limit = PilotRankLimit::query()->find($rank);

    return $test->actingAs($test->manager, 'web')->patchJson("/api/v1/pilot-rank-limits/{$rank}", ['max_vessel_loa' => $loa, 'version' => VersionToken::of($limit)]);
}

it('lists the three ranks, most senior first', function () {
    $this->actingAs(makeUserWithRole(Role::PilotageOfficer), 'web')->getJson('/api/v1/pilot-rank-limits')
        ->assertOk()
        ->assertJsonPath('data.*.pilot_rank', ['FIRST', 'SECOND', 'THIRD'])
        ->assertJsonPath('data.0.max_vessel_loa', null)
        ->assertJsonPath('data.2.max_vessel_loa', '200.00');
});

it('updates a limit within the order and audits it', function (string $rank, mixed $loa) {
    patchRankLimit($this, $rank, $loa)->assertOk()->assertJsonPath('pilot_rank', $rank);

    expect(AuditLog::query()->where('action', AuditLogAction::Update)->where('entity_type', 'pilot_rank_limits')->sole()->after_state)
        ->toMatchArray(['pilot_rank' => $rank]);
})->with([
    'third up to second' => ['THIRD', '300'],
    'second to no limit' => ['SECOND', null],
    'first given a limit above second' => ['FIRST', '350.5'],
]);

it('refuses a change that breaks FIRST ≥ SECOND ≥ THIRD', function (string $rank, mixed $loa) {
    patchRankLimit($this, $rank, $loa)
        ->assertStatus(422)
        ->assertJsonPath('errors.max_vessel_loa', ['errors.pilot_rank_limits.order']);

    expect(PilotRankLimit::query()->find($rank)->max_vessel_loa)->toBe($this->limits[$rank]->max_vessel_loa);
})->with([
    'third above second' => ['THIRD', '301'],
    'third with no limit while second has one' => ['THIRD', null],
    'first below second' => ['FIRST', '299'],
]);

it('refuses a limit that is not positive', function () {
    patchRankLimit($this, 'THIRD', 0)->assertStatus(422)->assertJsonStructure(['errors' => ['max_vessel_loa']]);
});

it('answers 409 to a stale version and 403 to the Tug Manager and the Port Admin', function () {
    $stale = VersionToken::of($this->limits['THIRD']);
    patchRankLimit($this, 'THIRD', '250')->assertOk();

    $this->actingAs($this->manager, 'web')->patchJson('/api/v1/pilot-rank-limits/THIRD', ['max_vessel_loa' => '240', 'version' => $stale])
        ->assertStatus(409)
        ->assertJsonPath('code', 'VERSION_CONFLICT');

    expect(AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->where('entity_type', 'pilot_rank_limits')->sole())
        ->entity_id->toBeNull()
        ->after_state->toMatchArray(['route' => 'PATCH /api/v1/pilot-rank-limits/THIRD', 'reason' => 'stale_version']);

    foreach ([Role::TugManager, Role::PortGm] as $role) {
        $this->actingAs(makeUserWithRole($role), 'web')->patchJson('/api/v1/pilot-rank-limits/THIRD', ['max_vessel_loa' => '240', 'version' => $stale])->assertForbidden();
    }

    expect(PilotRank::Third->value)->toBe('THIRD');
});
