<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Http\Middleware\EnsureAccountUsable;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
 * P2-D1 `ability:<name>` middleware: 403 FORBIDDEN without the ability, 401 first for a guest, after the password gate.
 * The test route is registered here; real routes get their ability from the endpoint tasks. Every test is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);

    Route::middleware(['api', 'auth:web', EnsureAccountUsable::class, 'ability:users.manage'])
        ->prefix('api/v1')
        ->get('test/ability', fn () => response()->json(['ok' => true]));
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('lets the role that holds the ability through', function () {
    $this->actingAs(makeUserWithRole(Role::PortGm), 'web')
        ->getJson('/api/v1/test/ability')
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(AuditLog::query()->count())->toBe(0);
});

it('answers 403 FORBIDDEN with a problem body to a role that lacks the ability', function () {
    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')
        ->getJson('/api/v1/test/ability')
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('answers 401 UNAUTHENTICATED to a guest before any role check', function () {
    $this->getJson('/api/v1/test/ability')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('answers the password gate first: PASSWORD_CHANGE_REQUIRED wins over the role check', function () {
    $this->actingAs(makeUserWithRole(Role::PortStaff, ['must_change_password' => true]), 'web')
        ->getJson('/api/v1/test/ability')
        ->assertForbidden()
        ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
});

it('refuses a user with no active role, but auth/me answers 200', function () {
    $user = makeUserWithRole(null);

    $this->actingAs($user, 'web')->getJson('/api/v1/test/ability')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->actingAs($user, 'web')->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('role', null);
});

it('takes the abilities away when the role assignment has ended', function () {
    $user = makeUserWithRole(Role::PortGm);
    UserRoleAssignment::query()->where('user_id', $user->id)->update(['active_from' => now()->subMonth(), 'active_to' => now()->subDay()]);

    $this->actingAs($user->fresh(), 'web')->getJson('/api/v1/test/ability')->assertForbidden();
});

it('refuses the marine safety officer, who holds no ability yet', function () {
    $this->actingAs(makeUserWithRole(Role::MarineSafetyOfficer), 'web')
        ->getJson('/api/v1/test/ability')
        ->assertForbidden();
});

it('writes one REJECTED_ATTEMPT row per refusal with route, status, code and reason', function () {
    $user = makeUserWithRole(Role::PortStaff, ['username' => 'staff.a']);

    $this->actingAs($user, 'web')->getJson('/api/v1/test/ability')->assertForbidden();

    $row = AuditLog::query()->sole();

    expect($row->action)->toBe(AuditLogAction::RejectedAttempt)
        ->and($row->actor_id)->toBe($user->id)
        ->and($row->after_state)->toMatchArray([
            'route' => 'GET /api/v1/test/ability',
            'status' => 403,
            'code' => 'FORBIDDEN',
            'reason' => 'role_not_allowed',
            'username' => 'staff.a',
        ]);
});

it('audits a user with no active role as no_active_role', function () {
    $this->actingAs(makeUserWithRole(null), 'web')->getJson('/api/v1/test/ability')->assertForbidden();

    expect(AuditLog::query()->sole()->after_state['reason'])->toBe('no_active_role');
});

it('defines one Gate per ability that follows the matrix', function () {
    $gm = makeUserWithRole(Role::PortGm);
    $staff = makeUserWithRole(Role::PortStaff);

    expect(Gate::forUser($gm)->allows('users.manage'))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('users.manage'))->toBeFalse()
        ->and(Gate::forUser($staff)->allows('plans.confirm'))->toBeTrue()
        ->and(Gate::forUser(makeUserWithRole(null))->allows('users.manage'))->toBeFalse();
});
