<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Services\RoleEndHandler;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\MasterData\Models\TugMasterSchedule;
use App\Modules\MasterData\Services\TugLinkRoleEndHandler;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * TUG-LINK-01 (FR-MD-08): the three `/users` writes clear a tug master's tug link in their own transaction. Rolled back
 * after each test.
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

dataset('user writes', [
    'deactivate' => ['deactivate', []],
    'replace the role' => ['roles', ['role' => 'PORT_STAFF']],
    'end the role' => ['roles/end', []],
]);

function profileUpdates(int $userId): int
{
    return AuditLog::query()->where('entity_type', 'tug_master_profiles')->where('entity_id', $userId)
        ->where('action', AuditLogAction::Update)->count();
}

it('clears the tug link with one audit row, keeps the schedules and frees the tug', function (string $suffix, array $body) {
    usePortProfile();
    $tugboat = Tugboat::factory()->create();
    $master = makeTugMaster($tugboat);
    $oldVersion = VersionToken::of($master);
    $shift = PortShift::factory()->create();
    TugMasterSchedule::query()->create(['tug_master_user_id' => $master->user_id, 'shift_date' => now()->addDays(3)->toDateString(), 'port_shift_id' => $shift->id, 'created_by' => $this->admin->id]);
    $this->travel(1)->seconds();

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$master->user_id}/{$suffix}", [...$body, 'version' => VersionToken::of($master->user)])
        ->assertOk();

    $row = AuditLog::query()->where('entity_type', 'tug_master_profiles')->where('entity_id', $master->user_id)->where('action', AuditLogAction::Update)->sole();

    expect($master->refresh()->assigned_tugboat_id)->toBeNull()
        ->and(VersionToken::of($master))->not->toBe($oldVersion)
        ->and($row->actor_id)->toBe($this->admin->id)
        ->and($row->before_state['assigned_tugboat_id'])->toBe($tugboat->id)
        ->and($row->after_state['assigned_tugboat_id'])->toBeNull()
        ->and(TugMasterSchedule::query()->where('tug_master_user_id', $master->user_id)->count())->toBe(1);

    $other = makeTugMaster();
    $this->actingAs(makeUserWithRole(Role::TugManager), 'web')->putJson("/api/v1/tug-masters/{$other->user_id}/tugboat", ['tugboat_id' => $tugboat->id, 'version' => VersionToken::of($other)])
        ->assertOk()
        ->assertJsonPath('tugboat.id', $tugboat->id);
})->with('user writes');

it('saves nothing when clearing the link fails', function (string $suffix, array $body) {
    $tugboat = Tugboat::factory()->create();
    $master = makeTugMaster($tugboat);
    $audits = AuditLog::query()->count();

    app()->bind(RoleEndHandler::class, fn () => new class(app(TugLinkRoleEndHandler::class)) implements RoleEndHandler
    {
        public function __construct(private readonly TugLinkRoleEndHandler $real) {}

        public function roleEnded(int $userId, Role $role, int $actorId): void
        {
            $this->real->roleEnded($userId, $role, $actorId);

            throw new RuntimeException('failed after clearing the link');
        }
    });

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$master->user_id}/{$suffix}", [...$body, 'version' => VersionToken::of($master->user)])
        ->assertStatus(500);

    expect($master->refresh()->assigned_tugboat_id)->toBe($tugboat->id)
        ->and($master->user->refresh()->is_active)->toBeTrue()
        ->and($master->user->activeRoleAssignment()->sole()->role)->toBe(Role::TugMaster)
        ->and(AuditLog::query()->count())->toBe($audits);
})->with('user writes');

it('writes no profile row for a tug master without a tug', function (string $suffix, array $body) {
    $master = makeTugMaster();

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$master->user_id}/{$suffix}", [...$body, 'version' => VersionToken::of($master->user)])
        ->assertOk();

    expect(profileUpdates($master->user_id))->toBe(0);
})->with('user writes');

it('keeps the link on a stale version and on the same role again', function () {
    $tugboat = Tugboat::factory()->create();
    $master = makeTugMaster($tugboat);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$master->user_id}/deactivate", ['version' => '2026-01-01T00:00:00.000000Z'])
        ->assertStatus(409);
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$master->user_id}/roles", ['role' => 'TUG_MASTER', 'version' => VersionToken::of($master->user)])
        ->assertOk();

    expect($master->refresh()->assigned_tugboat_id)->toBe($tugboat->id)
        ->and(profileUpdates($master->user_id))->toBe(0);
});

it('does not give the link back when the user is activated again', function () {
    $master = makeTugMaster(Tugboat::factory()->create());

    $deactivated = $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$master->user_id}/deactivate", ['version' => VersionToken::of($master->user)])
        ->assertOk();
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/users/{$master->user_id}/activate", ['version' => $deactivated->json('version')])
        ->assertOk();

    expect($master->refresh()->assigned_tugboat_id)->toBeNull()
        ->and(profileUpdates($master->user_id))->toBe(1);
});
