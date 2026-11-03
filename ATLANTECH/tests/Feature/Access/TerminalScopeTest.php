<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Http\Middleware\EnsureAccountUsable;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Services\TerminalScope;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * P2-D1 terminal scope: `terminal.scope` on a route `{terminal}`, `TerminalScope::authorize` for ids in a body or query,
 * `TerminalScope::restrict` for lists. Test routes are registered here; every test is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);

    Route::middleware(['api', 'auth:web', EnsureAccountUsable::class, 'ability:port-state.read', 'terminal.scope'])
        ->prefix('api/v1')
        ->get('test/terminals/{terminal}', fn () => response()->json(['ok' => true]));

    Route::middleware(['api', 'auth:web', EnsureAccountUsable::class, 'terminal.scope'])
        ->prefix('api/v1')
        ->get('test/supervisor/terminals/{terminal}', fn () => response()->json(['ok' => true]));

    Route::middleware(['api', 'auth:web', EnsureAccountUsable::class])
        ->prefix('api/v1')
        ->group(function () {
            Route::post('test/body-terminal', function (Request $request, TerminalScope $scope) {
                $scope->authorize($request, $request->user(), (int) $request->input('terminal_id'));

                return response()->json(['ok' => true]);
            });
            Route::get('test/list', fn (Request $request, TerminalScope $scope) => response()->json(
                $scope->restrict(OrganizationUnit::query(), 'terminal_id', $request->user())->orderBy('id')->pluck('terminal_id'),
            ));
        });
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('lets a terminal user reach their own terminal', function () {
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs(makeUserWithRole(Role::TerminalOfficer, unit: $unit), 'web')
        ->getJson("/api/v1/test/terminals/{$unit->terminal_id}")
        ->assertOk();
});

it('answers 403 FORBIDDEN, not 404, for another terminal, and audits terminal_out_of_scope', function () {
    $own = OrganizationUnit::factory()->create();
    $other = OrganizationUnit::factory()->create();
    $user = makeUserWithRole(Role::TerminalAdmin, unit: $own);

    $this->actingAs($user, 'web')
        ->getJson("/api/v1/test/terminals/{$other->terminal_id}")
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $row = AuditLog::query()->sole();

    expect($row->actor_id)->toBe($user->id)
        ->and($row->after_state)->toMatchArray(['status' => 403, 'code' => 'FORBIDDEN', 'reason' => 'terminal_out_of_scope']);
});

it('answers 403 for a terminal that does not exist, the same as for another terminal', function () {
    $this->actingAs(makeUserWithRole(Role::TerminalOfficer), 'web')
        ->getJson('/api/v1/test/terminals/999999')
        ->assertForbidden();
});

it('refuses a non-numeric terminal for a terminal user', function () {
    $this->actingAs(makeUserWithRole(Role::TerminalOfficer), 'web')
        ->getJson('/api/v1/test/terminals/abc')
        ->assertForbidden();
});

it('lets port-wide roles reach any terminal', function (Role $role) {
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs(makeUserWithRole($role), 'web')
        ->getJson("/api/v1/test/terminals/{$unit->terminal_id}")
        ->assertOk();
})->with([Role::PortGm, Role::PortStaff, Role::PilotageManager, Role::TugOfficer]);

it('holds a terminal supervisor to their own terminal too', function () {
    $own = OrganizationUnit::factory()->create();
    $other = OrganizationUnit::factory()->create();
    $user = makeUserWithRole(Role::TerminalSupervisor, unit: $own);

    $this->actingAs($user, 'web')->getJson("/api/v1/test/supervisor/terminals/{$own->terminal_id}")->assertOk();
    $this->actingAs($user, 'web')->getJson("/api/v1/test/supervisor/terminals/{$other->terminal_id}")->assertForbidden();
});

it('answers 401 to a guest and 403 to a user with no role', function () {
    $unit = OrganizationUnit::factory()->create();

    $this->getJson("/api/v1/test/supervisor/terminals/{$unit->terminal_id}")->assertUnauthorized();

    $this->actingAs(makeUserWithRole(null), 'web')
        ->getJson("/api/v1/test/supervisor/terminals/{$unit->terminal_id}")
        ->assertForbidden();

    expect(AuditLog::query()->sole()->after_state['reason'])->toBe('no_active_role');
});

it('checks a terminal id in a body the same way', function () {
    $own = OrganizationUnit::factory()->create();
    $other = OrganizationUnit::factory()->create();
    $user = makeUserWithRole(Role::TerminalOfficer, unit: $own);

    $this->actingAs($user, 'web')->postJson('/api/v1/test/body-terminal', ['terminal_id' => $own->terminal_id])->assertOk();
    $this->actingAs($user, 'web')->postJson('/api/v1/test/body-terminal', ['terminal_id' => $other->terminal_id])->assertForbidden();
});

it('restricts a list to the terminal user\'s terminal and leaves port-wide roles unrestricted', function () {
    $own = OrganizationUnit::factory()->create();
    $other = OrganizationUnit::factory()->create();

    $this->actingAs(makeUserWithRole(Role::TerminalOfficer, unit: $own), 'web')
        ->getJson('/api/v1/test/list')
        ->assertOk()
        ->assertExactJson([$own->terminal_id]);

    $all = $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')->getJson('/api/v1/test/list')->assertOk()->json();

    expect($all)->toContain($own->terminal_id, $other->terminal_id);
});

it('shows a user with no role an empty list', function () {
    OrganizationUnit::factory()->create();

    $this->actingAs(makeUserWithRole(null), 'web')->getJson('/api/v1/test/list')->assertOk()->assertExactJson([]);
});

it('keeps terminal_ids in the profile as before', function () {
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs(makeUserWithRole(Role::TerminalOfficer, unit: $unit), 'web')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('terminal_ids', [$unit->terminal_id]);
});
