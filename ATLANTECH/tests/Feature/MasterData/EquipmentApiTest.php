<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\MasterData\Models\CapabilityType;
use App\Modules\MasterData\Models\Equipment;
use App\Modules\MasterData\Models\EquipmentCapability;
use App\Modules\MasterData\Models\EquipmentCategory;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Enums\TerminalType;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * P2-F4 equipment and capabilities (FR-MD-11): the CT Administrator manages their own container terminal, the available
 * quantity is computed, and the equipment version covers the capabilities. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $unit = OrganizationUnit::factory()->create();
    $this->terminal = Terminal::query()->findOrFail($unit->terminal_id);
    $this->terminal->update(['terminal_type' => TerminalType::Container, 'is_active' => true]);
    $this->admin = makeUserWithRole(Role::TerminalAdmin, unit: $unit);
    $this->category = EquipmentCategory::factory()->create();
    $this->lifting = CapabilityType::factory()->create();
    $this->outreach = CapabilityType::factory()->create();
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function equipmentPath(Terminal $terminal, ?Equipment $equipment = null): string
{
    return "/api/v1/terminals/{$terminal->id}/equipment".($equipment === null ? '' : "/{$equipment->id}");
}

function equipmentBody(array $overrides = []): array
{
    return [
        'quantity' => 5,
        'maintenance_quantity' => 1,
        'blocked_quantity' => 1,
        'capabilities' => [['capability_type_id' => test()->lifting->id, 'value' => 65]],
        ...$overrides,
    ];
}

function storedEquipment(): Equipment
{
    $equipment = Equipment::factory()->create(['terminal_id' => test()->terminal->id, 'quantity' => 5, 'maintenance_quantity' => 1, 'blocked_quantity' => 1]);
    EquipmentCapability::factory()->create(['equipment_id' => $equipment->id, 'capability_type_id' => test()->lifting->id, 'capability_value' => 65]);

    return $equipment->refresh();
}

it('creates equipment with its capabilities, computes the available quantity and audits each row', function () {
    $response = $this->actingAs($this->admin, 'web')
        ->postJson(equipmentPath($this->terminal), equipmentBody(['equipment_category_id' => $this->category->id]))
        ->assertCreated()
        ->assertJsonPath('available_quantity', 3)
        ->assertJsonPath('equipment_category.id', $this->category->id)
        ->assertJsonPath('capabilities.0.value', 65);

    $equipment = Equipment::query()->findOrFail($response->json('id'));

    expect($response->json('version'))->toBe(VersionToken::of($equipment))
        ->and(AuditLog::query()->where('action', AuditLogAction::Create)->pluck('entity_type')->sort()->values()->all())
        ->toBe(['equipment', 'equipment_capabilities']);
});

it('refuses a bad body with 422 and stores nothing', function (array $overrides, string $field) {
    $this->actingAs($this->admin, 'web')
        ->postJson(equipmentPath($this->terminal), equipmentBody(['equipment_category_id' => $this->category->id, ...$overrides]))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrorFor($field, 'errors');

    expect(Equipment::query()->count())->toBe(0);
})->with([
    'units above the quantity' => [['maintenance_quantity' => 3, 'blocked_quantity' => 3], 'quantity'],
    'zero quantity' => [['quantity' => 0, 'maintenance_quantity' => 0, 'blocked_quantity' => 0], 'quantity'],
    'typed available quantity' => [['available_quantity' => 3], 'available_quantity'],
    'unknown category' => [['equipment_category_id' => 999999], 'equipment_category_id'],
    'unknown capability type' => [['capabilities' => [['capability_type_id' => 999999, 'value' => 1]]], 'capabilities.0.capability_type_id'],
    'repeated capability type' => [['capabilities' => [['capability_type_id' => 999999, 'value' => 1], ['capability_type_id' => 999999, 'value' => 2]]], 'capabilities.0.capability_type_id'],
    'negative value' => [['capabilities' => [['capability_type_id' => 999999, 'value' => -1]]], 'capabilities.0.value'],
]);

it('refuses a second row of the same category on a terminal with 422', function () {
    $existing = storedEquipment();

    $this->actingAs($this->admin, 'web')
        ->postJson(equipmentPath($this->terminal), equipmentBody(['equipment_category_id' => $existing->equipment_category_id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('equipment_category_id', 'errors');
});

it('refuses equipment on a non-container terminal with 422', function () {
    $this->terminal->update(['terminal_type' => TerminalType::NonContainer]);

    $this->actingAs($this->admin, 'web')
        ->postJson(equipmentPath($this->terminal), equipmentBody(['equipment_category_id' => $this->category->id]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.terminal', ['errors.equipment.not_container_terminal']);

    expect(Equipment::query()->count())->toBe(0);
});

it('replaces the capability list, audits removed and added rows, and moves the version when only capabilities change', function () {
    $equipment = storedEquipment();
    $before = VersionToken::of($equipment);

    $this->actingAs($this->admin, 'web')
        ->putJson(equipmentPath($this->terminal, $equipment), equipmentBody([
            'capabilities' => [['capability_type_id' => $this->outreach->id, 'value' => 40.5]],
            'version' => $before,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'capabilities')
        ->assertJsonPath('capabilities.0.capability_type_id', $this->outreach->id)
        ->assertJsonPath('version', fn (string $version) => $version !== $before);

    expect(EquipmentCapability::query()->where('equipment_id', $equipment->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('entity_type', 'equipment_capabilities')->get()->map(fn (AuditLog $row) => $row->action->value)->sort()->values()->all())
        ->toBe(['CREATE', 'DELETE']);
});

it('writes nothing and keeps the version when nothing changed', function () {
    $equipment = storedEquipment();
    $version = VersionToken::of($equipment);

    $this->actingAs($this->admin, 'web')
        ->putJson(equipmentPath($this->terminal, $equipment), equipmentBody(['version' => $version]))
        ->assertOk()
        ->assertJsonPath('version', $version);

    expect(AuditLog::query()->count())->toBe(0);
});

it('answers 409 for a stale version and changes nothing', function () {
    $equipment = storedEquipment();

    $this->actingAs($this->admin, 'web')
        ->putJson(equipmentPath($this->terminal, $equipment), equipmentBody(['quantity' => 9, 'version' => '2020-01-01T00:00:00.000000Z']))
        ->assertConflict()
        ->assertJsonPath('code', 'VERSION_CONFLICT');

    expect($equipment->refresh()->quantity)->toBe(5);
});

it('refuses a change of category or terminal with 422', function (string $field) {
    $equipment = storedEquipment();

    $this->actingAs($this->admin, 'web')
        ->putJson(equipmentPath($this->terminal, $equipment), equipmentBody([$field => 1, 'version' => VersionToken::of($equipment)]))
        ->assertUnprocessable()
        ->assertJsonPath("errors.{$field}", ['validation.prohibited']);
})->with(['equipment_category_id', 'terminal_id']);

it('keeps another terminal out with 403 and an audit row, and hides a child of another terminal with 404', function () {
    $other = Terminal::factory()->create();
    $foreign = Equipment::factory()->create(['terminal_id' => $other->id]);

    $this->actingAs($this->admin, 'web')->getJson(equipmentPath($other))->assertForbidden();
    $this->actingAs($this->admin, 'web')->postJson(equipmentPath($other), [])->assertForbidden();
    $this->actingAs($this->admin, 'web')->getJson(equipmentPath($this->terminal, $foreign))->assertNotFound();
    $this->actingAs($this->admin, 'web')->putJson(equipmentPath($this->terminal, $foreign), [])->assertNotFound();

    expect(AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->count())->toBe(2);
});

it('lets the terminal officer and port-wide roles read but not write', function (Role $role) {
    $equipment = storedEquipment();
    $user = $role->isTerminalScoped()
        ? makeUserWithRole($role, unit: OrganizationUnit::query()->where('terminal_id', $this->terminal->id)->sole())
        : makeUserWithRole($role);

    $this->actingAs($user, 'web')->getJson(equipmentPath($this->terminal))->assertOk()->assertJsonPath('meta.total', 1);
    $this->actingAs($user, 'web')->getJson(equipmentPath($this->terminal, $equipment))->assertOk();
    $this->actingAs($user, 'web')->postJson(equipmentPath($this->terminal), [])->assertForbidden();
    $this->actingAs($user, 'web')->putJson(equipmentPath($this->terminal, $equipment), [])->assertForbidden();
})->with([Role::TerminalOfficer, Role::PortGm, Role::PortStaff]);

it('refuses guests with 401 and field roles with 403', function () {
    $this->getJson(equipmentPath($this->terminal))->assertUnauthorized();
    $this->actingAs(makeUserWithRole(Role::MarinePilot), 'web')->getJson(equipmentPath($this->terminal))->assertForbidden();
});

it('lets a terminal administrator read an inactive terminal but answers 409 to a write', function () {
    $this->terminal->update(['is_active' => false]);

    $this->actingAs($this->admin, 'web')->getJson(equipmentPath($this->terminal))->assertOk();
    $this->actingAs($this->admin, 'web')
        ->postJson(equipmentPath($this->terminal), equipmentBody(['equipment_category_id' => $this->category->id]))
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');
});

it('puts both write routes in port.api, behind the ability, the scope and the version', function () {
    $store = Route::getRoutes()->getByName('terminals.equipment.store')->gatherMiddleware();
    $update = Route::getRoutes()->getByName('terminals.equipment.update')->gatherMiddleware();

    expect($store)->toContain('port.api', 'ability:equipment.manage', 'terminal.scope')
        ->and($update)->toContain('port.api', 'ability:equipment.manage', 'terminal.scope', 'version.token:equipment');
});

it('lists the equipment categories and capability types, ordered by code', function () {
    $this->actingAs($this->admin, 'web')->getJson('/api/v1/equipment-categories')
        ->assertOk()->assertJsonStructure(['data' => [['id', 'code', 'name', 'equipment_role']], 'meta' => ['page', 'per_page', 'total']]);

    $codes = $this->actingAs($this->admin, 'web')->getJson('/api/v1/capability-types?per_page=100')
        ->assertOk()->assertJsonStructure(['data' => [['id', 'code', 'name', 'unit']]])->json('data.*.code');

    expect($codes)->toBe(collect($codes)->sort()->values()->all());
});
