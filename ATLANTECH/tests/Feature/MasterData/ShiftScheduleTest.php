<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\MasterData\Models\PilotSchedule;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\TugMasterSchedule;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
 * P2-F3 pilot and tug master schedules (FR-MD-08, FR-MD-09, Q4, Q7): a batch is saved whole or not at all; refused for a
 * date before today in port time, an inactive person or one without the role, an inactive shift, or an existing entry.
 * The clock is 23:30 UTC on 1 December, already 01:30 on 2 December in Cairo, so "today" is the 2nd. Each case runs for
 * pilots and for tug masters. Rolled back after each test.
 */

dataset('schedule kinds', [
    'pilot' => ['pilot-schedules', Role::PilotageManager, PilotSchedule::class, 'pilot_user_id', fn () => makePilot(), Role::TugManager],
    'tug master' => ['tug-master-schedules', Role::TugManager, TugMasterSchedule::class, 'tug_master_user_id', fn () => makeTugMaster(), Role::PilotageManager],
]);

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    usePortProfile('Africa/Cairo');
    $this->travelTo(CarbonImmutable::parse('2026-12-01 23:30:00', 'UTC'));
    $this->shift = PortShift::factory()->create(['start_time' => '08:00:00', 'end_time' => '16:00:00']);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('adds a batch of entries from today in port time, audits each, and lists them', function (string $path, Role $managerRole, string $model, string $column, Closure $makePerson) {
    $manager = makeUserWithRole($managerRole);
    $person = $makePerson();
    $entries = [
        ['user_id' => $person->user_id, 'shift_date' => '2026-12-02', 'port_shift_id' => $this->shift->id],
        ['user_id' => $person->user_id, 'shift_date' => '2026-12-03', 'port_shift_id' => $this->shift->id],
    ];

    $this->actingAs($manager, 'web')->postJson("/api/v1/{$path}", ['entries' => $entries])
        ->assertCreated()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.user_id', $person->user_id)
        ->assertJsonPath('data.0.is_active', true)
        ->assertJsonPath('data.0.port_shift.start_time', '08:00');

    expect($model::query()->where($column, $person->user_id)->value('created_by'))->toBe($manager->id)
        ->and(AuditLog::query()->where('action', AuditLogAction::Create)->where('entity_type', (new $model)->getTable())->count())->toBe(2);

    $this->actingAs($manager, 'web')->getJson("/api/v1/{$path}?from=2026-12-03&to=2026-12-10")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.shift_date', '2026-12-03');
})->with('schedule kinds');

it('refuses the whole batch when one entry breaks a rule, naming the entry', function (string $path, Role $managerRole, string $model, string $column, Closure $makePerson, Role $otherManager, string $case) {
    $manager = makeUserWithRole($managerRole);
    $person = $makePerson();
    $good = ['user_id' => $person->user_id, 'shift_date' => '2026-12-04', 'port_shift_id' => $this->shift->id];
    [$bad, $field, $key] = match ($case) {
        'yesterday in port time' => [[...$good, 'shift_date' => '2026-12-01'], 'shift_date', 'errors.schedules.past_date'],
        'inactive person' => [tap($good, fn () => $person->user->update(['is_active' => false])), 'user_id', 'errors.schedules.person_unavailable'],
        'role ended' => [tap($good, fn () => UserRoleAssignment::query()->where('user_id', $person->user_id)->update(['active_from' => now()->subDay(), 'active_to' => now()->subMinute()])), 'user_id', 'errors.schedules.person_unavailable'],
        'inactive shift' => [[...$good, 'port_shift_id' => PortShift::factory()->create(['start_time' => '16:00:00', 'end_time' => '23:00:00', 'is_active' => false])->id], 'port_shift_id', 'errors.schedules.shift_inactive'],
        'already scheduled' => [tap($good, fn () => $model::query()->create([...[$column => $person->user_id, 'shift_date' => '2026-12-04', 'port_shift_id' => $this->shift->id], 'created_by' => $manager->id])), 'shift_date', 'errors.schedules.duplicate'],
        'twice in the batch' => [$good, 'shift_date', 'errors.schedules.duplicate'],
    };
    $before = $model::query()->count();
    $entries = $case === 'twice in the batch' ? [$good, $bad] : [[...$good, 'shift_date' => '2026-12-05'], $bad];

    $response = $this->actingAs($manager, 'web')->postJson("/api/v1/{$path}", ['entries' => $entries])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');

    expect($response->json('errors')["entries.1.{$field}"])->toContain($key)
        ->and($model::query()->count())->toBe($before);
})->with('schedule kinds')->with([
    'yesterday in port time',
    'inactive person',
    'role ended',
    'inactive shift',
    'already scheduled',
    'twice in the batch',
]);

it('refuses a person of the other department with 422', function (string $path, Role $managerRole, string $model, string $column, Closure $makePerson) {
    $wrongPerson = $path === 'pilot-schedules' ? makeTugMaster() : makePilot();

    $this->actingAs(makeUserWithRole($managerRole), 'web')->postJson("/api/v1/{$path}", ['entries' => [['user_id' => $wrongPerson->user_id, 'shift_date' => '2026-12-04', 'port_shift_id' => $this->shift->id]]])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['entries.0.user_id']]);
})->with('schedule kinds');

it('removes an entry from today on, and refuses removing a past one', function (string $path, Role $managerRole, string $model, string $column, Closure $makePerson) {
    $manager = makeUserWithRole($managerRole);
    $person = $makePerson();
    $today = $model::query()->create([$column => $person->user_id, 'shift_date' => '2026-12-02', 'port_shift_id' => $this->shift->id, 'created_by' => $manager->id]);
    $past = $model::query()->create([$column => $person->user_id, 'shift_date' => '2026-12-01', 'port_shift_id' => $this->shift->id, 'created_by' => $manager->id]);

    $this->actingAs($manager, 'web')->deleteJson("/api/v1/{$path}/{$today->id}")->assertNoContent();
    $this->actingAs($manager, 'web')->deleteJson("/api/v1/{$path}/{$past->id}")
        ->assertStatus(422)
        ->assertJsonPath('errors.shift_date', ['errors.schedules.past_date']);

    expect($model::query()->whereKey($today->id)->exists())->toBeFalse()
        ->and($model::query()->whereKey($past->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditLogAction::Delete)->count())->toBe(1);
})->with('schedule kinds');

it('refuses a list range over 62 days or reversed', function (string $path, Role $managerRole, string $model, string $column, Closure $makePerson, Role $otherManager) {
    $manager = makeUserWithRole($managerRole);

    $this->actingAs($manager, 'web')->getJson("/api/v1/{$path}?from=2026-12-01&to=2027-02-01")->assertStatus(422)->assertJsonStructure(['errors' => ['to']]);
    $this->actingAs($manager, 'web')->getJson("/api/v1/{$path}?from=2026-12-05&to=2026-12-01")->assertStatus(422)->assertJsonStructure(['errors' => ['to']]);
    $this->actingAs($manager, 'web')->getJson("/api/v1/{$path}?from=2026-12-01&to=2027-01-31")->assertOk();
})->with('schedule kinds');

it('lets officers read but only the department manager write', function (string $path, Role $managerRole, string $model, string $column, Closure $makePerson, Role $otherManager) {
    $person = $makePerson();
    $body = ['entries' => [['user_id' => $person->user_id, 'shift_date' => '2026-12-04', 'port_shift_id' => $this->shift->id]]];

    foreach ([Role::PilotageOfficer, Role::TugOfficer] as $officer) {
        $this->actingAs(makeUserWithRole($officer), 'web')->getJson("/api/v1/{$path}?from=2026-12-01&to=2026-12-07")->assertOk();
        $this->actingAs(makeUserWithRole($officer), 'web')->postJson("/api/v1/{$path}", $body)->assertForbidden();
    }

    $this->actingAs(makeUserWithRole($otherManager), 'web')->postJson("/api/v1/{$path}", $body)->assertForbidden();
})->with('schedule kinds');

it('answers 404 to a second removal of the same entry and audits the delete once', function (string $path, Role $managerRole, string $model, string $column, Closure $makePerson) {
    $manager = makeUserWithRole($managerRole);
    $entry = $model::query()->create([$column => $makePerson()->user_id, 'shift_date' => '2026-12-03', 'port_shift_id' => $this->shift->id, 'created_by' => $manager->id]);

    $this->actingAs($manager, 'web')->deleteJson("/api/v1/{$path}/{$entry->id}")->assertNoContent();
    $this->actingAs($manager, 'web')->deleteJson("/api/v1/{$path}/{$entry->id}")->assertNotFound();

    expect(AuditLog::query()->where('action', AuditLogAction::Delete)->count())->toBe(1);
})->with('schedule kinds');
