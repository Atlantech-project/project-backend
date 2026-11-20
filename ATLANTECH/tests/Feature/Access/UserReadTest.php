<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\UserRoleAssignment;
use Illuminate\Support\Facades\DB;

/*
 * P2-E1 `GET /users` and `GET /users/{user}` (P-23, P-24). Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $this->admin = makeUserWithRole(Role::PortGm, ['name' => 'Admin One', 'username' => 'admin.one']);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('lists users in the CT-01 shape with the active role, and never the password hash', function () {
    makeUserWithRole(Role::MarinePilot, ['name' => 'Pilot Karim', 'username' => 'pilot.karim', 'is_active' => false]);

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonPath('meta', ['page' => 1, 'per_page' => 25, 'total' => 2])
        ->assertJsonPath('data.0.username', 'admin.one')
        ->assertJsonPath('data.0.active_role.role', 'PORT_GM')
        ->assertJsonPath('data.1.is_active', false);

    expect($response->getContent())->not->toContain('password_hash');
});

it('filters by is_active and by a part of the name or username, and paginates', function () {
    makeUserWithRole(Role::MarinePilot, ['name' => 'Pilot Karim', 'username' => 'pilot.karim', 'is_active' => false]);
    makeUserWithRole(Role::TugMaster, ['name' => 'Master Omar', 'username' => 'master.omar']);

    $this->actingAs($this->admin, 'web')->getJson('/api/v1/users?is_active=0')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.username', 'pilot.karim');
    $this->actingAs($this->admin, 'web')->getJson('/api/v1/users?q=OMAR')->assertOk()->assertJsonPath('meta.total', 1);
    $this->actingAs($this->admin, 'web')->getJson('/api/v1/users?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3);
    $this->actingAs($this->admin, 'web')->getJson('/api/v1/users?per_page=0')->assertStatus(422);
});

it('shows the active role and the role history, newest first, with the terminal of a terminal role', function () {
    $user = makeUserWithRole(Role::TerminalOfficer);
    $terminalId = $user->activeRoleAssignment->organizationUnit->terminal_id;
    $harbour = OrganizationUnit::query()->where('unit_type', 'HARBOUR_AUTHORITY')->first();
    UserRoleAssignment::factory()->for($user)->for($harbour)->ended()->create(['role' => Role::PortStaff, 'unit_type' => $harbour->unit_type]);

    $this->actingAs($this->admin, 'web')->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('active_role.role', 'TERMINAL_OFFICER')
        ->assertJsonPath('active_role.terminal_id', $terminalId)
        ->assertJsonCount(2, 'role_history')
        ->assertJsonPath('role_history.0.role', 'TERMINAL_OFFICER')
        ->assertJsonPath('role_history.1.role', 'PORT_STAFF')
        ->assertJsonPath('role_history.1.active_to', fn ($value) => $value !== null);
});

it('answers 403 to other roles before 404, and 404 to the Port Admin for an unknown id', function () {
    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')->getJson('/api/v1/users/999999')->assertForbidden();
    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')->getJson('/api/v1/users')->assertForbidden();
    $this->actingAs($this->admin, 'web')->getJson('/api/v1/users/999999')->assertNotFound();
});
