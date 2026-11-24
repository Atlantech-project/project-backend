<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Services\TerminalService;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-F2 terminals (FR-MD-03, FR-MD-05): created with their TERMINAL unit, code and type fixed, deactivation as a no-op
 * when already inactive, the version token, and the terminal scope on reads (Q4). Rolled back after each test.
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

function terminalBody(array $overrides = []): array
{
    return [
        'code' => 'CT-1',
        'name' => 'Container Terminal One',
        'terminal_type' => 'CONTAINER',
        'boundary_coordinates' => ['type' => 'Polygon', 'coordinates' => [[[31.80, 31.40], [31.81, 31.40], [31.81, 31.41], [31.80, 31.40]]]],
        ...$overrides,
    ];
}

it('creates a terminal and its TERMINAL unit in one transaction, and audits both', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/v1/terminals', terminalBody())
        ->assertCreated()
        ->assertJsonPath('code', 'CT-1')
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('berths', []);

    $terminal = Terminal::query()->findOrFail($response->json('id'));
    $unit = OrganizationUnit::query()->where('terminal_id', $terminal->id)->sole();

    expect($unit->unit_type)->toBe(UnitType::Terminal)
        ->and($response->json('version'))->toBe(VersionToken::of($terminal))
        ->and(AuditLog::query()->where('action', AuditLogAction::Create)->pluck('entity_type')->sort()->values()->all())
        ->toBe(['organization_units', 'terminals']);
});

it('refuses a duplicate code or name, a bad type and a bad boundary with 422', function (array $overrides, string $field) {
    Terminal::factory()->create(['code' => 'TAKEN', 'name' => 'Taken Name']);

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/terminals', terminalBody($overrides))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['errors' => [$field]]);

    expect(Terminal::query()->count())->toBe(1);
})->with([
    'duplicate code' => [['code' => 'TAKEN'], 'code'],
    'duplicate name' => [['name' => 'Taken Name'], 'name'],
    'code too long' => [['code' => str_repeat('C', 21)], 'code'],
    'unknown type' => [['terminal_type' => 'BULK'], 'terminal_type'],
    'open ring' => [['boundary_coordinates' => ['type' => 'Polygon', 'coordinates' => [[[31, 31], [32, 31], [32, 32], [31, 32]]]]], 'boundary_coordinates'],
]);

it('updates the name and boundary under the version, and refuses code, type and is_active', function () {
    $terminal = Terminal::factory()->create();
    $before = VersionToken::of($terminal);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/terminals/{$terminal->id}", ['name' => 'Renamed', 'version' => $before])
        ->assertOk()
        ->assertJsonPath('name', 'Renamed')
        ->assertJsonPath('version', fn (string $version) => $version !== $before);

    foreach (['code' => 'NEW', 'terminal_type' => 'NON_CONTAINER', 'is_active' => false] as $field => $value) {
        $this->actingAs($this->admin, 'web')
            ->putJson("/api/v1/terminals/{$terminal->id}", [$field => $value, 'version' => VersionToken::of($terminal->fresh())])
            ->assertUnprocessable()
            ->assertJsonPath("errors.{$field}", ['validation.prohibited']);
    }

    expect($terminal->fresh()->terminal_type->value)->toBe('CONTAINER');
});

it('answers 409 VERSION_CONFLICT to a stale version and changes nothing', function () {
    $terminal = Terminal::factory()->create(['name' => 'Original']);
    $stale = VersionToken::of($terminal);
    DB::table('terminals')->where('id', $terminal->id)->update(['updated_at' => now()->addSecond()]);

    $this->actingAs($this->admin, 'web')
        ->putJson("/api/v1/terminals/{$terminal->id}", ['name' => 'Lost update', 'version' => $stale])
        ->assertConflict()
        ->assertJsonPath('code', 'VERSION_CONFLICT');

    expect($terminal->fresh()->name)->toBe('Original');
});

it('deactivates and reactivates a terminal, leaving its berths unchanged; the same state again is a no-op', function () {
    $terminal = Terminal::factory()->create();
    $berth = Berth::factory()->create();
    $berth->terminals()->attach($terminal->id);

    $this->actingAs($this->admin, 'web')
        ->postJson("/api/v1/terminals/{$terminal->id}/deactivate", ['version' => VersionToken::of($terminal)])
        ->assertOk()
        ->assertJsonPath('is_active', false);

    $inactiveVersion = VersionToken::of($terminal->fresh());
    $auditRows = AuditLog::query()->count();

    $this->actingAs($this->admin, 'web')
        ->postJson("/api/v1/terminals/{$terminal->id}/deactivate", ['version' => $inactiveVersion])
        ->assertOk()
        ->assertJsonPath('version', $inactiveVersion);

    expect(AuditLog::query()->count())->toBe($auditRows)
        ->and($berth->fresh()->status->value)->toBe('ACTIVE');

    $this->actingAs($this->admin, 'web')
        ->postJson("/api/v1/terminals/{$terminal->id}/activate", ['version' => $inactiveVersion])
        ->assertOk()
        ->assertJsonPath('is_active', true);
});

it('answers 403 to writes from every role except the Port Admin, and 401 to a guest', function () {
    $terminal = Terminal::factory()->create();

    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')->postJson('/api/v1/terminals', terminalBody())->assertForbidden();
    $this->actingAs(makeUserWithRole(Role::TerminalAdmin), 'web')
        ->postJson("/api/v1/terminals/{$terminal->id}/deactivate", ['version' => VersionToken::of($terminal)])
        ->assertForbidden();
    auth()->forgetGuards();
    $this->postJson('/api/v1/terminals', terminalBody())->assertUnauthorized();
});

it('shows a terminal-scoped user only their terminal, in the list and by id', function () {
    $own = OrganizationUnit::factory()->create();
    $other = OrganizationUnit::factory()->create();
    $user = makeUserWithRole(Role::TerminalOfficer, unit: $own);

    $this->actingAs($user, 'web')->getJson('/api/v1/terminals')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$own->terminal_id]);

    $this->actingAs($user, 'web')->getJson("/api/v1/terminals/{$other->terminal_id}")->assertForbidden();

    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')->getJson('/api/v1/terminals')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('never changes code or type through the service, even if they reach it', function () {
    $terminal = Terminal::factory()->create(['code' => 'KEEP', 'terminal_type' => 'CONTAINER']);

    $updated = app(TerminalService::class)->update($terminal, ['name' => 'New name', 'code' => 'CHANGED', 'terminal_type' => 'NON_CONTAINER'], VersionToken::of($terminal), $this->admin->id);

    expect($updated->fresh()->only(['code', 'name']))->toBe(['code' => 'KEEP', 'name' => 'New name'])
        ->and($updated->fresh()->terminal_type->value)->toBe('CONTAINER');
});
