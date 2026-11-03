<?php

use App\Modules\Access\Enums\Ability;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Http\Middleware\EnsureAccountUsable;
use App\Modules\Access\Services\RolePermissions;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * P2-D1 / NFR-SEC-2: a test for every role. One test route per ability; every role calls every route. A role that holds
 * the ability gets 200, every other call is an unauthorized cross-role call and must be 403 FORBIDDEN (and audited).
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);

    foreach (Ability::cases() as $ability) {
        Route::middleware(['api', 'auth:web', EnsureAccountUsable::class, "ability:{$ability->value}"])
            ->prefix('api/v1')
            ->get("test/abilities/{$ability->value}", fn () => response()->json(['ok' => true]));
    }
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('answers 200 to the roles that hold each ability and 403 to every other role', function () {
    $denied = 0;
    $allowed = 0;

    foreach (Role::cases() as $role) {
        $user = makeUserWithRole($role);

        foreach (Ability::cases() as $ability) {
            $response = $this->actingAs($user, 'web')->getJson("/api/v1/test/abilities/{$ability->value}");

            if (RolePermissions::allows($role, $ability)) {
                $response->assertOk();
                $allowed++;
            } else {
                $response->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
                $denied++;
            }
        }
    }

    expect($denied)->toBeGreaterThanOrEqual(30)
        ->and($allowed)->toBeGreaterThan(0)
        ->and(AuditLog::query()->count())->toBe($denied);
});

it('keeps every role signed in on auth/me, the marine safety officer included', function (Role $role) {
    $this->actingAs(makeUserWithRole($role), 'web')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('role', $role->value);
})->with(Role::cases());

it('gives the field roles 403 on every web ability except their own tasks', function (Role $role) {
    $user = makeUserWithRole($role);
    $ownTask = RolePermissions::abilitiesFor($role);

    foreach (Ability::cases() as $ability) {
        if (in_array($ability, $ownTask, true)) {
            continue;
        }

        $this->actingAs($user, 'web')->getJson("/api/v1/test/abilities/{$ability->value}")->assertForbidden();
    }
})->with([Role::MarinePilot, Role::TugMaster, Role::TerminalSupervisor]);
