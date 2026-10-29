<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\Access\Services\SignInService;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * P1-C1 web sign-in (CT-03, FR-AC-01, FR-AC-02). Requests come from the web app's origin so Sanctum treats
 * them as stateful (session + CSRF); the MySQL schema database is used because the CHECKs and the
 * one-active-role key matter, and every test is rolled back.
 */

const SPA_ORIGIN = 'http://localhost:5173';

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

/**
 * @param  array<string, mixed>  $body
 */
function signIn(array $body): TestResponse
{
    return test()->withHeaders(['Origin' => SPA_ORIGIN, 'Referer' => SPA_ORIGIN.'/'])->postJson('/api/v1/auth/login', $body);
}

function userWithRole(Role $role, OrganizationUnit $unit, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    UserRoleAssignment::factory()->for($user)->for($unit)->create(['role' => $role, 'unit_type' => $unit->unit_type]);

    return $user;
}

function rejectedAttempts(): Collection
{
    return AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->get();
}

it('signs in, starts a new session and returns the profile', function () {
    $unit = OrganizationUnit::factory()->create();
    $user = userWithRole(Role::TerminalOfficer, $unit, ['username' => 'staff.ahmed', 'name' => 'Ahmed']);

    signIn(['username' => 'staff.ahmed', 'password' => 'password'])
        ->assertOk()
        ->assertCookie(config('session.cookie'))
        ->assertExactJson([
            'id' => $user->id,
            'name' => 'Ahmed',
            'role' => 'TERMINAL_OFFICER',
            'terminal_ids' => [$unit->terminal_id],
            'must_change_password' => false,
        ]);

    $this->assertAuthenticatedAs($user, 'web');
    expect(config('session.cookie'))->toBe('atlantech_session')
        ->and(config('session.lifetime'))->toBe(120)
        ->and(rejectedAttempts())->toBeEmpty();
});

it('trims and lowercases the username before matching it', function () {
    User::factory()->create(['username' => 'staff.ahmed']);

    signIn(['username' => '  Staff.Ahmed ', 'password' => 'password'])->assertOk();
});

it('returns the role and terminal scope of the one active role', function (Closure $makeUnit, Role $role, bool $hasTerminal) {
    $unit = $makeUnit();
    userWithRole($role, $unit, ['username' => 'scoped.user']);

    signIn(['username' => 'scoped.user', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('role', $role->value)
        ->assertJsonPath('terminal_ids', $hasTerminal ? [$unit->terminal_id] : []);
})->with([
    'Harbour Authority' => [fn () => OrganizationUnit::factory()->harbourAuthority()->create(), Role::PortStaff, false],
    'Marine Services' => [fn () => OrganizationUnit::factory()->marineServices()->create(), Role::TugMaster, false],
    'Terminal' => [fn () => OrganizationUnit::factory()->create(), Role::TerminalAdmin, true],
])->group('schema');

it('signs in a user with no active role with role null and no terminals', function () {
    $unit = OrganizationUnit::factory()->create();
    $user = User::factory()->create(['username' => 'no.role']);
    UserRoleAssignment::factory()->for($user)->for($unit)->ended()->create(['unit_type' => UnitType::Terminal]);

    signIn(['username' => 'no.role', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('role', null)
        ->assertJsonPath('terminal_ids', []);
});

it('signs in a user who must change the password, with the flag set', function () {
    User::factory()->create(['username' => 'first.login', 'must_change_password' => true]);

    signIn(['username' => 'first.login', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('must_change_password', true);
});

it('answers 422 for a missing or invalid field, without an audit row', function (array $body, string $field) {
    signIn($body)
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['errors' => [$field]]);

    expect(rejectedAttempts())->toBeEmpty();
})->with([
    'no username' => [['password' => 'password'], 'username'],
    'no password' => [['username' => 'staff.ahmed'], 'password'],
    'username too short' => [['username' => 'ab', 'password' => 'password'], 'username'],
]);

it('answers the same 401 for an unknown user and a wrong password, and audits it', function (string $username) {
    User::factory()->create(['username' => 'staff.ahmed']);

    signIn(['username' => $username, 'password' => 'wrong-password'])
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'UNAUTHENTICATED');

    $this->assertGuest('web');
    $row = rejectedAttempts()->sole();
    expect($row->actor_id)->toBeNull()
        ->and($row->entity_type)->toBe('users')
        ->and($row->entity_id)->toBeNull()
        // MySQL JSON stores object keys in its own order; toEqual compares by key.
        ->and($row->after_state)->toEqual([
            'route' => 'POST /api/v1/auth/login',
            'code' => 'UNAUTHENTICATED',
            'reason' => 'invalid_credentials',
            'username' => $username,
        ])
        ->and(json_encode($row->after_state))->not->toContain('wrong-password');
})->with(['unknown user' => 'nobody.here', 'wrong password' => 'staff.ahmed']);

it('answers 403 ACCOUNT_DISABLED to a deactivated user with the right password, and audits it', function () {
    User::factory()->inactive()->create(['username' => 'gone.user']);

    signIn(['username' => 'gone.user', 'password' => 'password'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'ACCOUNT_DISABLED');

    $this->assertGuest('web');
    expect(rejectedAttempts()->sole()->after_state)->toMatchArray(['code' => 'ACCOUNT_DISABLED', 'reason' => 'account_disabled']);
});

it('throttles after 5 failed attempts per username and IP, with Retry-After and no audit row', function () {
    User::factory()->create(['username' => 'staff.ahmed']);

    foreach (range(1, SignInService::MAX_ATTEMPTS_PER_USERNAME) as $attempt) {
        signIn(['username' => 'staff.ahmed', 'password' => 'wrong-password'])->assertStatus(401);
    }

    signIn(['username' => 'staff.ahmed', 'password' => 'password'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS')
        ->assertHeader('Retry-After');

    expect(rejectedAttempts())->toHaveCount(SignInService::MAX_ATTEMPTS_PER_USERNAME);
});

it('throttles after 20 failed attempts from one IP across usernames', function () {
    foreach (range(1, SignInService::MAX_ATTEMPTS_PER_IP) as $attempt) {
        signIn(['username' => "guess.{$attempt}", 'password' => 'wrong-password'])->assertStatus(401);
    }

    signIn(['username' => 'another.guess', 'password' => 'wrong-password'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('clears the username throttle after a successful sign-in', function () {
    User::factory()->create(['username' => 'staff.ahmed']);

    foreach (range(1, SignInService::MAX_ATTEMPTS_PER_USERNAME - 1) as $attempt) {
        signIn(['username' => 'staff.ahmed', 'password' => 'wrong-password'])->assertStatus(401);
    }
    signIn(['username' => 'staff.ahmed', 'password' => 'password'])->assertOk();

    signIn(['username' => 'staff.ahmed', 'password' => 'wrong-password'])->assertStatus(401);
});

it('answers 419 SESSION_EXPIRED to a stateful request without a valid XSRF token', function () {
    User::factory()->create(['username' => 'staff.ahmed']);
    // CSRF checks are skipped while running unit tests; leave the testing environment for this request.
    $this->app['env'] = 'local';

    signIn(['username' => 'staff.ahmed', 'password' => 'password'])
        ->assertStatus(419)
        ->assertJsonPath('code', 'SESSION_EXPIRED');

    $this->assertGuest('web');
});
