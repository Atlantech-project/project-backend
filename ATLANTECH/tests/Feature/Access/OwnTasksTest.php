<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Http\Middleware\EnsureAccountUsable;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Plan\Models\PlanComponent;
use App\Modules\Shared\Enums\ComponentType;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * P2-D1 own tasks: a field user sees only the plan components assigned to them (`PlanComponent::visibleTo`), another user's
 * component answers 403, and the supervisor is also held to their terminal. Test routes are registered here; rolled back.
 * Foreign-key checks are off, as in ModelLayerTest: the plan factories point at recommendation rows of later modules.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);

    Route::middleware(['api', 'auth:web', EnsureAccountUsable::class, 'ability:tasks.own.read'])
        ->prefix('api/v1')
        ->group(function () {
            Route::get('test/tasks', fn (Request $request) => response()->json(
                PlanComponent::query()->visibleTo($request->user())->orderBy('id')->pluck('id'),
            ));
            Route::get('test/tasks/{task}', function (Request $request, int $task) {
                $component = PlanComponent::query()->findOrFail($task);
                $component->authorizeTask($request, $request->user());

                return response()->json(['id' => $component->id]);
            });
        });
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function pilotTask(int $userId): PlanComponent
{
    return PlanComponent::factory()->create([
        'component_type' => ComponentType::Pilot,
        'berth_id' => null,
        'assigned_user_id' => $userId,
        'assigned_at' => now(),
    ]);
}

function terminalTask(int $userId, int $terminalId): PlanComponent
{
    return PlanComponent::factory()->create([
        'component_type' => ComponentType::TerminalOps,
        'berth_id' => null,
        'terminal_id' => $terminalId,
        'assigned_user_id' => $userId,
        'assigned_at' => now(),
    ]);
}

it('lists only the components assigned to the field user', function (Role $role) {
    $user = makeUserWithRole($role);
    $other = makeUserWithRole($role);
    $mine = pilotTask($user->id);
    pilotTask($other->id);
    PlanComponent::factory()->create();

    $this->actingAs($user, 'web')->getJson('/api/v1/test/tasks')
        ->assertOk()
        ->assertExactJson([$mine->id]);
})->with([Role::MarinePilot, Role::TugMaster]);

it('opens the own task and answers 403 for another user\'s task, audited as not_own_task', function () {
    $user = makeUserWithRole(Role::MarinePilot);
    $other = makeUserWithRole(Role::MarinePilot);
    $mine = pilotTask($user->id);
    $theirs = pilotTask($other->id);

    $this->actingAs($user, 'web')->getJson("/api/v1/test/tasks/{$mine->id}")->assertOk();

    $this->actingAs($user, 'web')->getJson("/api/v1/test/tasks/{$theirs->id}")
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    expect(AuditLog::query()->sole()->after_state)->toMatchArray(['status' => 403, 'code' => 'FORBIDDEN', 'reason' => 'not_own_task']);
});

it('answers 403 for an unassigned task too', function () {
    $user = makeUserWithRole(Role::TugMaster);
    $task = PlanComponent::factory()->create();

    $this->actingAs($user, 'web')->getJson("/api/v1/test/tasks/{$task->id}")->assertForbidden();
});

it('holds the terminal supervisor to their own tasks and their own terminal', function () {
    $own = OrganizationUnit::factory()->create();
    $other = OrganizationUnit::factory()->create();
    $supervisor = makeUserWithRole(Role::TerminalSupervisor, unit: $own);
    $colleague = makeUserWithRole(Role::TerminalSupervisor, unit: $own);

    $mine = terminalTask($supervisor->id, $own->terminal_id);
    $colleagues = terminalTask($colleague->id, $own->terminal_id);
    $wrongTerminal = terminalTask($supervisor->id, $other->terminal_id);

    $this->actingAs($supervisor, 'web')->getJson('/api/v1/test/tasks')->assertOk()->assertExactJson([$mine->id]);
    $this->actingAs($supervisor, 'web')->getJson("/api/v1/test/tasks/{$mine->id}")->assertOk();
    $this->actingAs($supervisor, 'web')->getJson("/api/v1/test/tasks/{$colleagues->id}")->assertForbidden();
    $this->actingAs($supervisor, 'web')->getJson("/api/v1/test/tasks/{$wrongTerminal->id}")->assertForbidden();

    expect(AuditLog::query()->latest('id')->first()->after_state['reason'])->toBe('terminal_out_of_scope');
});

it('leaves office roles unrestricted: the scope is not applied', function () {
    $pilot = makeUserWithRole(Role::MarinePilot);
    pilotTask($pilot->id);
    PlanComponent::factory()->create();

    $staff = makeUserWithRole(Role::PortStaff);

    expect(PlanComponent::query()->visibleTo($staff)->count())->toBe(2);
});

it('is a local scope: workers and jobs still read every component', function () {
    $pilot = makeUserWithRole(Role::MarinePilot);
    pilotTask($pilot->id);
    PlanComponent::factory()->create();

    expect(PlanComponent::query()->count())->toBe(2)
        ->and(PlanComponent::query()->visibleTo($pilot)->count())->toBe(1);
});

it('shows a user with no role nothing and refuses them on a task', function () {
    $user = makeUserWithRole(null);
    $task = pilotTask(makeUserWithRole(Role::MarinePilot)->id);

    expect(PlanComponent::query()->visibleTo($user)->count())->toBe(0);

    $this->actingAs($user, 'web')->getJson("/api/v1/test/tasks/{$task->id}")->assertForbidden();
});

it('refuses a role without the own-task ability before the scope runs', function () {
    $this->actingAs(makeUserWithRole(Role::MarineSafetyOfficer), 'web')->getJson('/api/v1/test/tasks')->assertForbidden();
});
