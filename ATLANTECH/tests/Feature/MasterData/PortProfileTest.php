<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Models\PortProfile;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-F1 port profile (FR-MD-01, P-26): the office roles read it, only the Port Admin edits the fields the port owns, the
 * SaaS fields are refused, and every update goes through the version token and the audit log. Rolled back after each test.
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

function seededPortProfile(): PortProfile
{
    return PortProfile::factory()
        ->create(['port_code' => 'EGDAM', 'governorate_code' => 'EG-DT', 'name' => 'Damietta Port', 'timezone' => 'Africa/Cairo'])
        ->refresh();
}

it('shows the profile to an office role, with its version', function () {
    $profile = seededPortProfile();

    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')
        ->getJson('/api/v1/port-profile')
        ->assertOk()
        ->assertJsonPath('id', 1)
        ->assertJsonPath('name', 'Damietta Port')
        ->assertJsonPath('port_code', 'EGDAM')
        ->assertJsonPath('version', VersionToken::of($profile));
});

it('answers 503 while the port has no profile row', function () {
    $this->actingAs($this->admin, 'web')->getJson('/api/v1/port-profile')
        ->assertStatus(503)
        ->assertJsonPath('code', 'SERVICE_UNAVAILABLE');
});

it('lets the Port Admin change the owned fields, keeps the others and audits before and after', function () {
    $profile = seededPortProfile();
    $boundary = ['type' => 'Polygon', 'coordinates' => [[[31.75, 31.42], [31.81, 31.42], [31.81, 31.48], [31.75, 31.42]]]];

    $response = $this->actingAs($this->admin, 'web')->putJson('/api/v1/port-profile/1', [
        'name' => 'Port of Damietta',
        'latitude' => 31.4501,
        'longitude' => 31.7602,
        'boundary_coordinates' => $boundary,
        'version' => VersionToken::of($profile),
    ])->assertOk()
        ->assertJsonPath('name', 'Port of Damietta')
        ->assertJsonPath('latitude', 31.4501)
        ->assertJsonPath('timezone', 'Africa/Cairo')
        ->assertJsonPath('public_id', $profile->public_id)
        ->assertJsonPath('boundary_coordinates', $boundary);

    $fresh = $profile->fresh();
    $row = AuditLog::query()->where('action', AuditLogAction::Update)->where('entity_type', 'port_profiles')->sole();

    expect($response->json('version'))->toBe(VersionToken::of($fresh))->not->toBe(VersionToken::of($profile))
        ->and($row->actor_id)->toBe($this->admin->id)
        ->and($row->before_state['name'])->toBe('Damietta Port')
        ->and($row->after_state['name'])->toBe('Port of Damietta');
});

it('refuses the SaaS fields and invalid values with 422 and changes nothing', function (array $body, string $field) {
    $profile = seededPortProfile();

    $this->actingAs($this->admin, 'web')->putJson('/api/v1/port-profile/1', [...$body, 'version' => VersionToken::of($profile)])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['errors' => [$field]]);

    expect($profile->fresh()->getAttributes())->toBe($profile->getAttributes());
})->with([
    'public_id' => [['public_id' => '0d9f6a52-7c1b-4b7e-9a3e-5a2f1c8d7e10'], 'public_id'],
    'port_code' => [['port_code' => 'EGALY'], 'port_code'],
    'governorate_code' => [['governorate_code' => 'EG-ALX'], 'governorate_code'],
    'id' => [['id' => 2], 'id'],
    'empty name' => [['name' => ''], 'name'],
    'name too long' => [['name' => str_repeat('a', 151)], 'name'],
    'latitude out of range' => [['latitude' => 90.5], 'latitude'],
    'longitude out of range' => [['longitude' => -181], 'longitude'],
    'latitude with 7 decimals' => [['latitude' => '31.4501234'], 'latitude'],
    'unknown timezone' => [['timezone' => 'Africa/Damietta'], 'timezone'],
    'open boundary ring' => [['boundary_coordinates' => ['type' => 'Polygon', 'coordinates' => [[[31.1, 31.1], [31.2, 31.1], [31.2, 31.2], [31.1, 31.2]]]]], 'boundary_coordinates'],
]);

it('answers 409 VERSION_CONFLICT to a stale version, audits it and changes nothing', function () {
    $profile = seededPortProfile();
    $stale = VersionToken::of($profile);
    $this->actingAs($this->admin, 'web')->putJson('/api/v1/port-profile/1', ['name' => 'First edit', 'version' => $stale])->assertOk();

    $this->actingAs($this->admin, 'web')->putJson('/api/v1/port-profile/1', ['name' => 'Second edit', 'version' => $stale])
        ->assertStatus(409)
        ->assertJsonPath('code', 'VERSION_CONFLICT');

    expect($profile->fresh()->name)->toBe('First edit')
        ->and(AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->sole()->entity_type)->toBe('port_profiles');
});

it('lets only the Port Admin edit: any other role gets 403 and nothing changes', function (Role $role) {
    $profile = seededPortProfile();

    $this->actingAs(makeUserWithRole($role), 'web')
        ->putJson('/api/v1/port-profile/1', ['name' => 'Changed', 'version' => VersionToken::of($profile)])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    expect($profile->fresh()->name)->toBe('Damietta Port');
})->with([Role::PortStaff, Role::MarineSafetyOfficer, Role::TerminalAdmin]);

it('refuses a guest, and a field role the read', function () {
    seededPortProfile();

    $this->getJson('/api/v1/port-profile')->assertUnauthorized();
    $this->actingAs(makeUserWithRole(Role::MarinePilot), 'web')->getJson('/api/v1/port-profile')->assertForbidden();
});

it('answers 404 for any other profile id', function () {
    seededPortProfile();

    $this->actingAs($this->admin, 'web')->putJson('/api/v1/port-profile/2', ['name' => 'x'])->assertNotFound();
});
