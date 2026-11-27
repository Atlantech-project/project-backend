<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Models\Vessel;
use App\Modules\MasterData\Models\VesselType;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P3-H7 vessel particulars (FR-BQ-13, sequence 2.8): the read with its version, a partial edit with its audit row, the
 * database CHECKs as 422, the IMO number that is never edited, `call_sign` and `flag_state` written only while empty
 * (CT-15), who may write, 404 and a stale version. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $this->staff = makeUserWithRole(Role::PortStaff);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function storedVessel(array $attributes = []): Vessel
{
    return Vessel::factory()->create([
        'imo_number' => '9839430',
        'name' => 'MSC Aurora',
        'loa' => 300,
        'beam' => 40,
        'max_draft' => 14,
        ...$attributes,
    ])->fresh();
}

function vesselEdit(Vessel $vessel, array $changes): array
{
    return [...$changes, 'version' => VersionToken::of($vessel)];
}

it('returns a vessel with its type and version', function () {
    $vessel = storedVessel();

    $this->actingAs($this->staff, 'web')->getJson("/api/v1/vessels/{$vessel->id}")
        ->assertOk()
        ->assertJsonPath('imo_number', '9839430')
        ->assertJsonPath('loa', 300)
        ->assertJsonPath('vessel_type.id', $vessel->vessel_type_id)
        ->assertJsonPath('version', VersionToken::of($vessel));
});

it('changes only the fields sent and audits their old and new values', function () {
    $vessel = storedVessel();
    $type = VesselType::factory()->create();

    $response = $this->actingAs($this->staff, 'web')->putJson("/api/v1/vessels/{$vessel->id}", vesselEdit($vessel, [
        'name' => 'MSC Aurora II',
        'vessel_type_id' => $type->id,
        'max_draft' => 14.5,
        'net_tonnage' => 52000,
        'moulded_depth' => 24.2,
    ]))
        ->assertOk()
        ->assertJsonPath('name', 'MSC Aurora II')
        ->assertJsonPath('vessel_type.id', $type->id)
        ->assertJsonPath('max_draft', 14.5)
        ->assertJsonPath('net_tonnage', 52000)
        ->assertJsonPath('moulded_depth', 24.2)
        ->assertJsonPath('loa', 300)
        ->assertJsonPath('imo_number', '9839430');

    expect($response->json('version'))->not->toBe(VersionToken::of($vessel));

    $row = AuditLog::query()->where('entity_type', 'vessels')->sole();

    expect($row->action)->toBe(AuditLogAction::Update)
        ->and($row->entity_id)->toBe($vessel->id)
        ->and($row->actor_id)->toBe($this->staff->id)
        ->and($row->before_state['name'])->toBe('MSC Aurora')
        ->and($row->after_state['name'])->toBe('MSC Aurora II')
        ->and($row->after_state)->not->toHaveKey('loa');
});

it('writes nothing when no value changes', function () {
    $vessel = storedVessel();

    $this->actingAs($this->staff, 'web')
        ->putJson("/api/v1/vessels/{$vessel->id}", vesselEdit($vessel, ['name' => 'MSC Aurora', 'loa' => 300]))
        ->assertOk()
        ->assertJsonPath('version', VersionToken::of($vessel));

    expect(AuditLog::query()->count())->toBe(0);
});

it('refuses an invalid value with 422 and changes nothing', function (array $changes, string $field) {
    $vessel = storedVessel();

    $this->actingAs($this->staff, 'web')->putJson("/api/v1/vessels/{$vessel->id}", vesselEdit($vessel, $changes))
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => [$field]]);

    expect($vessel->fresh()->getAttributes())->toBe($vessel->getAttributes())
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'the IMO number' => [['imo_number' => '9074729'], 'imo_number'],
    'the same IMO number' => [['imo_number' => '9839430'], 'imo_number'],
    'an empty name' => [['name' => ''], 'name'],
    'a long name' => [['name' => str_repeat('a', 121)], 'name'],
    'an unknown type' => [['vessel_type_id' => 999999], 'vessel_type_id'],
    'a zero length' => [['loa' => 0], 'loa'],
    'a length too large' => [['loa' => 100000], 'loa'],
    'three decimals' => [['beam' => 40.555], 'beam'],
    'a null draught' => [['max_draft' => null], 'max_draft'],
    'a draught too large' => [['max_draft' => 1000], 'max_draft'],
    'zero net tonnage' => [['net_tonnage' => 0], 'net_tonnage'],
    'a zero moulded depth' => [['moulded_depth' => 0], 'moulded_depth'],
    'a long call sign' => [['call_sign' => 'ABCDEFGHIJK'], 'call_sign'],
    'a flag of three letters' => [['flag_state' => 'EGY'], 'flag_state'],
    'a lower case flag' => [['flag_state' => 'eg'], 'flag_state'],
    'a beam equal to the stored length' => [['beam' => 300], 'beam'],
    'a length below the stored beam' => [['loa' => 39], 'beam'],
    'both, beam above length' => [['loa' => 100, 'beam' => 101], 'beam'],
]);

it('names the broken rule in the 422, so the frontend can translate it', function () {
    $vessel = storedVessel();
    $vessel->forceFill(['call_sign' => 'SUXX'])->save();
    $vessel = $vessel->fresh();

    $this->actingAs($this->staff, 'web')->putJson("/api/v1/vessels/{$vessel->id}", vesselEdit($vessel, ['beam' => 300]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.beam.0', 'errors.vessels.beam_not_below_loa');

    $this->actingAs($this->staff, 'web')->putJson("/api/v1/vessels/{$vessel->id}", vesselEdit($vessel, ['call_sign' => 'SUYY']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.call_sign.0', 'errors.vessels.already_set');
});

it('fills an empty call sign and flag, and accepts the same values again', function () {
    $vessel = storedVessel();

    $this->actingAs($this->staff, 'web')
        ->putJson("/api/v1/vessels/{$vessel->id}", vesselEdit($vessel, ['call_sign' => 'SUXX', 'flag_state' => 'EG']))
        ->assertOk()
        ->assertJsonPath('call_sign', 'SUXX')
        ->assertJsonPath('flag_state', 'EG');

    $filled = $vessel->fresh();

    $this->actingAs($this->staff, 'web')
        ->putJson("/api/v1/vessels/{$vessel->id}", vesselEdit($filled, ['call_sign' => 'SUXX', 'flag_state' => 'EG', 'name' => 'Renamed']))
        ->assertOk()
        ->assertJsonPath('name', 'Renamed')
        ->assertJsonPath('call_sign', 'SUXX');
});

it('does not replace or clear a call sign or flag that has a value', function (string $column, ?string $value) {
    $vessel = storedVessel();
    $vessel->forceFill(['call_sign' => 'SUXX', 'flag_state' => 'EG'])->save();
    $vessel = $vessel->fresh();

    $this->actingAs($this->staff, 'web')
        ->putJson("/api/v1/vessels/{$vessel->id}", vesselEdit($vessel, [$column => $value, 'name' => 'Renamed']))
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => [$column]]);

    expect($vessel->fresh()->getAttributes())->toBe($vessel->getAttributes());
})->with([
    'another call sign' => ['call_sign', 'SUYY'],
    'no call sign' => ['call_sign', null],
    'another flag' => ['flag_state', 'PA'],
    'no flag' => ['flag_state', null],
]);

it('refuses a stale version with 409 and the current one', function () {
    $vessel = storedVessel();

    $this->actingAs($this->staff, 'web')
        ->putJson("/api/v1/vessels/{$vessel->id}", ['name' => 'Late', 'version' => '2000-01-01T00:00:00.000000Z'])
        ->assertConflict()
        ->assertJsonPath('code', 'VERSION_CONFLICT')
        ->assertJsonPath('current.version', VersionToken::of($vessel));

    expect($vessel->fresh()->name)->toBe('MSC Aurora');
});

it('lets every role with port-state.read see a vessel', function (Role $role) {
    $vessel = storedVessel();

    $this->actingAs(makeUserWithRole($role), 'web')->getJson("/api/v1/vessels/{$vessel->id}")->assertOk();
})->with([Role::PortGm, Role::TerminalAdmin]);

it('lets only Port Authority Staff edit a vessel', function (Role $role) {
    $vessel = storedVessel();

    $this->actingAs(makeUserWithRole($role), 'web')
        ->putJson("/api/v1/vessels/{$vessel->id}", vesselEdit($vessel, ['name' => 'Other']))
        ->assertForbidden();

    expect($vessel->fresh()->name)->toBe('MSC Aurora');
})->with([Role::PortGm, Role::TerminalAdmin, Role::MarineSafetyOfficer]);

it('answers 401 without a session and 404 for an unknown vessel', function () {
    $vessel = storedVessel();

    $this->getJson("/api/v1/vessels/{$vessel->id}")->assertUnauthorized();
    $this->actingAs($this->staff, 'web')->getJson('/api/v1/vessels/999999')->assertNotFound();
    $this->actingAs($this->staff, 'web')
        ->putJson('/api/v1/vessels/999999', ['name' => 'Nobody', 'version' => VersionToken::of($vessel)])
        ->assertNotFound();
});
