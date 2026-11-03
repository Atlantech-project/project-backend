<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Http\Middleware\EnsureAccountUsable;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Plan\Models\PlanComponent;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Enums\ComponentType;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * P2-D1 / FR-GL-02: every refused role, terminal or own-task check writes one REJECTED_ATTEMPT row (route, 403, FORBIDDEN,
 * reason, actor) and a successful call writes none. Test routes are registered here; every test is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);

    Route::middleware(['api', 'auth:web', EnsureAccountUsable::class])->prefix('api/v1')->group(function () {
        Route::get('test/ability', fn () => response()->json(['ok' => true]))->middleware('ability:users.manage');
        Route::get('test/terminals/{terminal}', fn () => response()->json(['ok' => true]))->middleware('terminal.scope');
        Route::get('test/tasks/{task}', function (Request $request, int $task) {
            PlanComponent::query()->findOrFail($task)->authorizeTask($request, $request->user());

            return response()->json(['ok' => true]);
        });
    });
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function assertRefusalRow(string $route, string $reason, int $actorId): void
{
    $rows = AuditLog::query()->get();

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    expect($row->action)->toBe(AuditLogAction::RejectedAttempt)
        ->and($row->actor_id)->toBe($actorId)
        ->and($row->after_state)->toMatchArray(['route' => $route, 'status' => 403, 'code' => 'FORBIDDEN', 'reason' => $reason]);
}

it('audits a role that lacks the ability as role_not_allowed', function () {
    $user = makeUserWithRole(Role::PortStaff);

    $this->actingAs($user, 'web')->getJson('/api/v1/test/ability')->assertForbidden();

    assertRefusalRow('GET /api/v1/test/ability', 'role_not_allowed', $user->id);
});

it('audits a user with no active role as no_active_role', function () {
    $user = makeUserWithRole(null);

    $this->actingAs($user, 'web')->getJson('/api/v1/test/ability')->assertForbidden();

    assertRefusalRow('GET /api/v1/test/ability', 'no_active_role', $user->id);
});

it('audits another terminal as terminal_out_of_scope', function () {
    $other = OrganizationUnit::factory()->create();
    $user = makeUserWithRole(Role::TerminalOfficer);

    $this->actingAs($user, 'web')->getJson("/api/v1/test/terminals/{$other->terminal_id}")->assertForbidden();

    assertRefusalRow("GET /api/v1/test/terminals/{$other->terminal_id}", 'terminal_out_of_scope', $user->id);
});

it('audits another users task as not_own_task', function () {
    $user = makeUserWithRole(Role::MarinePilot);
    $task = PlanComponent::factory()->create([
        'component_type' => ComponentType::Pilot,
        'berth_id' => null,
        'assigned_user_id' => makeUserWithRole(Role::MarinePilot)->id,
        'assigned_at' => now(),
    ]);

    $this->actingAs($user, 'web')->getJson("/api/v1/test/tasks/{$task->id}")->assertForbidden();

    assertRefusalRow("GET /api/v1/test/tasks/{$task->id}", 'not_own_task', $user->id);
});

it('writes one row per refusal and never a row on success', function () {
    $user = makeUserWithRole(Role::PortGm);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($user, 'web')->getJson('/api/v1/test/ability')->assertOk();
    $this->actingAs($user, 'web')->getJson("/api/v1/test/terminals/{$unit->terminal_id}")->assertOk();

    expect(AuditLog::query()->count())->toBe(0);

    $staff = makeUserWithRole(Role::PortStaff);
    $this->actingAs($staff, 'web')->getJson('/api/v1/test/ability')->assertForbidden();
    $this->actingAs($staff, 'web')->getJson('/api/v1/test/ability')->assertForbidden();

    expect(AuditLog::query()->count())->toBe(2);
});
