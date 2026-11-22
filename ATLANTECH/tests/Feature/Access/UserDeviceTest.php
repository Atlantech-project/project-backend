<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\PersonalAccessToken;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserDevice;
use App\Modules\License\Models\LicenseState;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/*
 * P2-E3 list, revoke, revoke-all and restore of a user's devices (FR-AC-13). Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $this->admin = makeUserWithRole(Role::PortGm);
    $this->owner = makeUserWithRole(Role::MarinePilot);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

/**
 * A device of the user with push token and `$liveTokens` live tokens (one is enough for the sliding rule; more show the count).
 */
function deviceWithTokens(User $user, int $liveTokens = 1, array $attributes = []): UserDevice
{
    $device = UserDevice::factory()->for($user)->create(['push_token' => 'fcm-secret-'.uniqid(), 'push_token_updated_at' => now(), ...$attributes]);

    for ($i = 0; $i < $liveTokens; $i++) {
        $user->tokens()->create([
            'name' => $device->device_id,
            'token' => hash('sha256', uniqid('', true)),
            'abilities' => ['*'],
            'expires_at' => now()->addDays(30),
            'user_device_id' => $device->id,
        ]);
    }

    return $device;
}

it('lists the devices with the push flag and the live token count, and never the push token', function () {
    $device = deviceWithTokens($this->owner, 2);
    $expired = deviceWithTokens($this->owner, 0, ['push_token' => null]);
    $this->owner->tokens()->create(['name' => 'x', 'token' => hash('sha256', 'old'), 'abilities' => ['*'], 'expires_at' => now()->subDay(), 'user_device_id' => $expired->id]);
    $other = deviceWithTokens(makeUserWithRole(Role::TugMaster));

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/v1/users/{$this->owner->id}/devices")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.1.id', $device->id)
        ->assertJsonPath('data.1.has_push_token', true)
        ->assertJsonPath('data.1.live_token_count', 2)
        ->assertJsonPath('data.0.has_push_token', false)
        ->assertJsonPath('data.0.live_token_count', 0);

    expect($response->getContent())->not->toContain('fcm-secret')->not->toContain((string) $other->device_id);
});

it('answers 403 to every role except the Port Admin, and 404 for an unknown user', function () {
    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')->getJson("/api/v1/users/{$this->owner->id}/devices")->assertForbidden();
    $this->actingAs($this->admin, 'web')->getJson('/api/v1/users/999999/devices')->assertNotFound();
});

it('answers 403 before 404 to a role that may not manage devices, so it cannot learn whether a row exists', function () {
    $stranger = deviceWithTokens(makeUserWithRole(Role::TugMaster));
    $staff = makeUserWithRole(Role::PortStaff);

    $this->actingAs($staff, 'web')->getJson('/api/v1/users/999999/devices')->assertForbidden();
    $this->actingAs($staff, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$stranger->id}/revoke")->assertForbidden();
    $this->actingAs($staff, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$stranger->id}/restore")->assertForbidden();
});

it('pages the list with per_page and meta, and refuses a bad per_page with 422', function () {
    deviceWithTokens($this->owner);
    deviceWithTokens($this->owner);
    deviceWithTokens($this->owner);

    $this->actingAs($this->admin, 'web')->getJson("/api/v1/users/{$this->owner->id}/devices?per_page=2&page=2")
        ->assertOk()
        ->assertJsonPath('meta', ['page' => 2, 'per_page' => 2, 'total' => 3])
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->admin, 'web')->getJson("/api/v1/users/{$this->owner->id}/devices?per_page=0")->assertStatus(422);
});

it('revokes one device: flag, cleared push fields, its tokens revoked, others untouched, audited without the push token', function () {
    $device = deviceWithTokens($this->owner, 2);
    $untouched = deviceWithTokens($this->owner);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$device->id}/revoke")
        ->assertOk()
        ->assertJsonPath('revoked_at', fn ($value) => $value !== null)
        ->assertJsonPath('has_push_token', false)
        ->assertJsonPath('live_token_count', 0);

    $device->refresh();
    $row = AuditLog::query()->where('entity_type', 'user_devices')->sole();

    expect($device->push_token)->toBeNull()->and($device->push_token_updated_at)->toBeNull()
        ->and(PersonalAccessToken::query()->where('user_device_id', $device->id)->whereNull('revoked_at')->count())->toBe(0)
        ->and(PersonalAccessToken::query()->where('user_device_id', $untouched->id)->whereNull('revoked_at')->count())->toBe(1)
        ->and($untouched->refresh()->revoked_at)->toBeNull()
        ->and($row->action)->toBe(AuditLogAction::Update)
        ->and($row->actor_id)->toBe($this->admin->id)
        ->and($row->before_state)->toBe(['revoked_at' => null])
        ->and($row->after_state)->toHaveKeys(['revoked_at', 'tokens_revoked'])->and($row->after_state['tokens_revoked'])->toBe(2)
        ->and(json_encode(AuditLog::query()->get()->toArray()))->not->toContain('fcm-secret');
});

it('revoking again is a no-op without a new audit row, and a device of another user is 404', function () {
    $device = deviceWithTokens($this->owner);
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$device->id}/revoke")->assertOk();
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$device->id}/revoke")->assertOk();

    expect(AuditLog::query()->where('entity_type', 'user_devices')->count())->toBe(1);

    $stranger = deviceWithTokens(makeUserWithRole(Role::TugMaster));

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$stranger->id}/revoke")->assertNotFound();
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$stranger->id}/restore")->assertNotFound();
    expect($stranger->refresh()->revoked_at)->toBeNull();
});

it('revokes all devices of the user and returns the counts; a user with no devices gets zeros; other users are untouched', function () {
    deviceWithTokens($this->owner, 2);
    deviceWithTokens($this->owner, 1);
    $already = deviceWithTokens($this->owner, 0, ['revoked_at' => now()->subDay(), 'push_token' => null]);
    $bystander = deviceWithTokens(makeUserWithRole(Role::TugMaster));

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/revoke-all")
        ->assertOk()
        ->assertExactJson(['devices_revoked' => 2, 'tokens_revoked' => 3]);

    expect(UserDevice::query()->where('user_id', $this->owner->id)->whereNull('revoked_at')->count())->toBe(0)
        ->and($already->refresh()->revoked_at)->not->toBeNull()
        ->and($bystander->refresh()->revoked_at)->toBeNull()
        ->and(AuditLog::query()->where('entity_type', 'user_devices')->count())->toBe(2);

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/users/'.makeUserWithRole(null)->id.'/devices/revoke-all')
        ->assertOk()->assertExactJson(['devices_revoked' => 0, 'tokens_revoked' => 0]);
});

it('restores a revoked device without bringing back any token, and a device that is not revoked is a no-op', function () {
    $device = deviceWithTokens($this->owner, 2);
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$device->id}/revoke")->assertOk();
    $tokensBefore = PersonalAccessToken::query()->orderBy('id')->get()->map->only(['id', 'revoked_at'])->toArray();

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$device->id}/restore")
        ->assertOk()
        ->assertJsonPath('revoked_at', null)
        ->assertJsonPath('has_push_token', false)
        ->assertJsonPath('live_token_count', 0);

    $row = AuditLog::query()->where('entity_type', 'user_devices')->orderByDesc('id')->first();

    expect(PersonalAccessToken::query()->orderBy('id')->get()->map->only(['id', 'revoked_at'])->toArray())->toEqual($tokensBefore)
        ->and($row->actor_id)->toBe($this->admin->id)
        ->and($row->after_state)->toMatchArray(['revoked_at' => null])
        ->and($row->before_state['revoked_at'])->not->toBeNull()
        ->and(json_encode($row->toArray()))->not->toContain('fcm-secret');

    $count = AuditLog::query()->count();
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$device->id}/restore")->assertOk();

    expect(AuditLog::query()->count())->toBe($count);
});

it('answers 403 to other roles on revoke, revoke-all and restore', function () {
    $device = deviceWithTokens($this->owner);
    $staff = makeUserWithRole(Role::PortStaff);

    foreach (['revoke', 'restore'] as $action) {
        $this->actingAs($staff, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$device->id}/{$action}")->assertForbidden();
    }
    $this->actingAs($staff, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/revoke-all")->assertForbidden();
});

it('rolls the revoke and its audit row back together when the audit write fails', function () {
    $device = deviceWithTokens($this->owner);

    app()->instance(AuditService::class, new class extends AuditService
    {
        public function record(AuditLogAction $action, string $entityType, ?int $entityId, ?int $actorId, ?array $before, ?array $after): AuditLog
        {
            throw new RuntimeException('forced failure');
        }
    });

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$device->id}/revoke")->assertStatus(500);

    expect($device->refresh()->revoked_at)->toBeNull()
        ->and($device->push_token)->toStartWith('fcm-secret-')
        ->and(PersonalAccessToken::query()->whereNull('revoked_at')->count())->toBe(1);
});

it('revoke and revoke-all are allowed while the licence is READ_ONLY (pass-through until P2-D5), and restore is a normal write', function () {
    LicenseState::factory()->create();
    $device = deviceWithTokens($this->owner);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$device->id}/revoke")->assertOk();
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/revoke-all")->assertOk();
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$this->owner->id}/devices/{$device->id}/restore")->assertOk();
});
