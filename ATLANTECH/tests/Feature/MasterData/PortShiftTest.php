<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Models\PilotSchedule;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\TugMasterSchedule;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
 * P2-F3 port shifts (AIBD-016, Q2, Q6, Q8): active shifts never overlap, back-to-back and across midnight are fine, and a
 * shift used by schedules from today on keeps its times and stays active. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    usePortProfile('Africa/Cairo');
    $this->travelTo(CarbonImmutable::parse('2026-12-01 23:30:00', 'UTC'));
    PortShift::query()->update(['is_active' => false]);
    $this->admin = makeUserWithRole(Role::PortGm);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function portShiftBody(string $start, string $end, array $overrides = []): array
{
    return [...['code' => 'S'.str_replace(':', '', $start), 'name' => "Shift {$start}", 'start_time' => $start, 'end_time' => $end], ...$overrides];
}

function patchPortShift(object $test, PortShift $shift, array $changes)
{
    return $test->actingAs($test->admin, 'web')->patchJson("/api/v1/port-shifts/{$shift->id}", [...$changes, 'version' => VersionToken::of($shift)]);
}

it('creates a shift with HH:MM times, marks a shift that ends the next day, and audits it', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/v1/port-shifts', portShiftBody('22:00', '06:00'))
        ->assertCreated()
        ->assertJsonPath('start_time', '22:00')
        ->assertJsonPath('end_time', '06:00')
        ->assertJsonPath('ends_next_day', true)
        ->assertJsonPath('is_active', true);

    expect(PortShift::query()->find($response->json('id'))->start_time)->toBe('22:00:00')
        ->and(AuditLog::query()->where('action', AuditLogAction::Create)->where('entity_type', 'port_shifts')->count())->toBe(1);
});

it('accepts three back-to-back shifts that cover the day, one crossing midnight', function () {
    foreach ([['06:00', '14:00'], ['14:00', '22:00'], ['22:00', '06:00']] as [$start, $end]) {
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/port-shifts', portShiftBody($start, $end))->assertCreated();
    }

    expect(PortShift::query()->where('is_active', true)->count())->toBe(3);
});

it('refuses an active shift that overlaps another active shift, also across midnight', function (array $existing, array $new) {
    PortShift::factory()->create(['start_time' => $existing[0].':00', 'end_time' => $existing[1].':00']);

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/port-shifts', portShiftBody($new[0], $new[1]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonPath('errors.start_time', ['errors.port_shifts.overlap']);
})->with([
    'inside the day' => [['08:00', '16:00'], ['15:00', '23:00']],
    'new one crosses midnight' => [['05:00', '09:00'], ['22:00', '06:00']],
    'existing one crosses midnight' => [['22:00', '06:00'], ['23:00', '01:00']],
    'same times' => [['08:00', '16:00'], ['08:00', '16:00']],
]);

it('ignores inactive shifts and accepts an inactive overlapping shift', function () {
    PortShift::factory()->create(['start_time' => '08:00:00', 'end_time' => '16:00:00', 'is_active' => false]);
    $this->actingAs($this->admin, 'web')->postJson('/api/v1/port-shifts', portShiftBody('10:00', '18:00'))->assertCreated();

    $this->actingAs($this->admin, 'web')->postJson('/api/v1/port-shifts', portShiftBody('12:00', '13:00', ['is_active' => false]))
        ->assertCreated()
        ->assertJsonPath('is_active', false);
});

it('refuses equal start and end times, on create and on a one-sided update', function () {
    $this->actingAs($this->admin, 'web')->postJson('/api/v1/port-shifts', portShiftBody('08:00', '08:00'))
        ->assertStatus(422)
        ->assertJsonPath('errors.end_time', ['validation.different']);

    $shift = PortShift::factory()->create(['start_time' => '08:00:00', 'end_time' => '16:00:00']);

    patchPortShift($this, $shift, ['end_time' => '08:00'])
        ->assertStatus(422)
        ->assertJsonPath('errors.end_time', ['validation.different']);
});

it('refuses activating a shift that overlaps an active one', function () {
    PortShift::factory()->create(['start_time' => '08:00:00', 'end_time' => '16:00:00']);
    $inactive = PortShift::factory()->create(['start_time' => '12:00:00', 'end_time' => '20:00:00', 'is_active' => false]);

    patchPortShift($this, $inactive, ['is_active' => true])
        ->assertStatus(422)
        ->assertJsonPath('errors.start_time', ['errors.port_shifts.overlap']);
});

it('updates a shift without comparing it with itself', function () {
    $shift = PortShift::factory()->create(['start_time' => '08:00:00', 'end_time' => '16:00:00']);

    patchPortShift($this, $shift, ['end_time' => '17:00', 'name' => 'Day'])
        ->assertOk()
        ->assertJsonPath('end_time', '17:00')
        ->assertJsonPath('name', 'Day');

    expect(AuditLog::query()->where('action', AuditLogAction::Update)->where('entity_type', 'port_shifts')->count())->toBe(1);
});

it('refuses with 409 in_use a time change or a deactivation while a schedule from today (port time) uses the shift', function (string $scheduleClass, array $changes) {
    $shift = PortShift::factory()->create(['start_time' => '08:00:00', 'end_time' => '16:00:00']);
    $person = $scheduleClass === PilotSchedule::class ? makePilot() : makeTugMaster();
    $column = $scheduleClass === PilotSchedule::class ? 'pilot_user_id' : 'tug_master_user_id';
    $scheduleClass::query()->create([$column => $person->user_id, 'shift_date' => '2026-12-02', 'port_shift_id' => $shift->id, 'created_by' => $this->admin->id]);

    patchPortShift($this, $shift, $changes)
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT')
        ->assertJsonPath('detail', __('errors.port_shifts.in_use'));

    expect($shift->refresh()->start_time)->toBe('08:00:00')
        ->and($shift->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->where('entity_type', 'port_shifts')->sole()->after_state)
        ->toMatchArray(['code' => 'CONFLICT', 'reason' => 'in_use']);
})->with([
    'pilot schedule, new times' => [PilotSchedule::class, ['start_time' => '07:00']],
    'tug master schedule, deactivate' => [TugMasterSchedule::class, ['is_active' => false]],
]);

it('allows a rename while the shift is in use, and a time change when only dates before today in port time use it', function () {
    $shift = PortShift::factory()->create(['start_time' => '08:00:00', 'end_time' => '16:00:00']);
    $pilot = makePilot();
    $schedule = PilotSchedule::query()->create(['pilot_user_id' => $pilot->user_id, 'shift_date' => '2026-12-02', 'port_shift_id' => $shift->id, 'created_by' => $this->admin->id]);

    patchPortShift($this, $shift, ['code' => 'DAY', 'name' => 'Day shift'])->assertOk()->assertJsonPath('code', 'DAY');
    patchPortShift($this, $shift->refresh(), ['start_time' => '07:00'])->assertStatus(409);

    $schedule->update(['shift_date' => '2026-12-01']);

    patchPortShift($this, $shift->refresh(), ['start_time' => '07:00'])->assertOk()->assertJsonPath('start_time', '07:00');
});

it('answers 409 VERSION_CONFLICT to a stale version', function () {
    $shift = PortShift::factory()->create(['start_time' => '08:00:00', 'end_time' => '16:00:00']);
    $stale = VersionToken::of($shift);
    $this->travel(1)->seconds();
    $shift->touch();

    $this->actingAs($this->admin, 'web')->patchJson("/api/v1/port-shifts/{$shift->id}", ['name' => 'X', 'version' => $stale])
        ->assertStatus(409)
        ->assertJsonPath('code', 'VERSION_CONFLICT');
});

it('lets every office role read the shifts but only the Port Admin write them', function (Role $role) {
    PortShift::factory()->create(['start_time' => '08:00:00', 'end_time' => '16:00:00']);
    $user = makeUserWithRole($role);

    $this->actingAs($user, 'web')->getJson('/api/v1/port-shifts')->assertOk()->assertJsonStructure(['data' => [['id', 'code', 'start_time', 'end_time', 'ends_next_day', 'is_active', 'version']]]);
    $this->actingAs($user, 'web')->postJson('/api/v1/port-shifts', portShiftBody('17:00', '20:00'))->assertForbidden();
})->with([Role::PilotageManager, Role::TugManager, Role::PilotageOfficer, Role::PortStaff]);
