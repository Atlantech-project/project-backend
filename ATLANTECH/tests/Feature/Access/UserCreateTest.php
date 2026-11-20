<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\License\Models\LicenseState;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/*
 * P2-E1 `POST /users` (FR-AC-09): the one-time password is shown once and kept only as a hash; the plan limit (FR-LIC-04). Rolled back after each test.
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

function createUserBody(array $overrides = []): array
{
    return [...['name' => 'Salma Hassan', 'username' => 'salma.hassan', 'phone' => '+201000000001'], ...$overrides];
}

it('creates an active user with a one-time password shown once and stored only as a hash', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/v1/users', createUserBody(['username' => '  Salma.HASSAN ']))
        ->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('username', 'salma.hassan')
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('must_change_password', true)
        ->assertJsonPath('active_role', null);

    $password = $response->json('one_time_password');
    $user = User::query()->where('username', 'salma.hassan')->sole();

    expect($password)->toBeString()->toHaveLength(20)->toMatch('/^[A-Za-z2-9]+$/')
        ->and($password)->not->toMatch('/[0OIl1]/')
        ->and($user->password_hash)->not->toBe($password)
        ->and(Hash::check($password, $user->password_hash))->toBeTrue()
        ->and($response->json('version'))->toBeString();

    $this->actingAs($this->admin, 'web')->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->assertJsonMissingPath('one_time_password');
});

it('audits the creation without the password, in any audit row or log line', function () {
    Log::spy();

    $password = $this->actingAs($this->admin, 'web')->postJson('/api/v1/users', createUserBody())->json('one_time_password');

    $row = AuditLog::query()->where('action', AuditLogAction::Create)->where('entity_type', 'users')->sole();

    expect($row->actor_id)->toBe($this->admin->id)
        ->and($row->before_state)->toBeNull()
        ->and($row->after_state)->toMatchArray(['username' => 'salma.hassan'])
        ->and(json_encode(AuditLog::query()->get()->toArray()))->not->toContain($password);

    Log::shouldNotHaveReceived('error');
});

it('refuses a bad username or a duplicate with 422 and saves nothing', function (array $overrides, string $field) {
    makeUserWithRole(null, ['username' => 'taken.name']);
    $before = User::query()->count();

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/users', createUserBody($overrides))
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['errors' => [$field]]);

    expect(User::query()->count())->toBe($before);
})->with([
    'too short' => [['username' => 'ab'], 'username'],
    'spaces and symbols' => [['username' => 'sal ma!'], 'username'],
    'duplicate, any case' => [['username' => 'TAKEN.name'], 'username'],
    'missing name' => [['name' => ''], 'name'],
]);

it('answers 403 to every role except the Port Admin and 401 to a guest', function (Role $role) {
    $this->actingAs(makeUserWithRole($role), 'web')->postJson('/api/v1/users', createUserBody())
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
})->with(array_values(array_filter(Role::cases(), fn (Role $role) => $role !== Role::PortGm)));

it('answers 401 to a guest', function () {
    $this->postJson('/api/v1/users', createUserBody())->assertUnauthorized();
});

it('refuses with 422 PLAN_LIMIT_EXCEEDED at the limit, counting every active user, and saves nothing', function () {
    LicenseState::factory()->create(['max_users' => 3]);
    makeUserWithRole(Role::MarinePilot);
    makeUserWithRole(Role::TugMaster);
    makeUserWithRole(null, ['is_active' => false]);
    $before = User::query()->count();

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/users', createUserBody())
        ->assertStatus(422)
        ->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');

    expect(User::query()->count())->toBe($before)
        ->and(AuditLog::query()->where('action', AuditLogAction::Create)->count())->toBe(0);
});

it('allows a user below the limit', function () {
    LicenseState::factory()->create(['max_users' => 5]);

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/users', createUserBody())->assertCreated();
});

it('has no limit without a licence row', function () {
    $this->actingAs($this->admin, 'web')->postJson('/api/v1/users', createUserBody())->assertCreated();
});

it('has no limit when max_users is null', function () {
    LicenseState::factory()->create(['max_users' => null]);

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/users', createUserBody())->assertCreated();
});
