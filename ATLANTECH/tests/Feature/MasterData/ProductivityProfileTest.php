<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Models\TerminalProductivityProfile;
use App\Modules\MasterData\Models\VesselType;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Enums\TerminalType;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-F6 terminal productivity profiles (FR-MD-13, P-48): the container terminal's admin writes its own, periods of one
 * vessel type (the default apart) never share a day, and every change is versioned and audited. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $this->terminal = Terminal::factory()->create(['terminal_type' => TerminalType::Container, 'is_active' => true]);
    $this->unit = OrganizationUnit::factory()->create(['terminal_id' => $this->terminal->id]);
    $this->admin = makeUserWithRole(Role::TerminalAdmin, unit: $this->unit);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function profileBody(array $overrides = []): array
{
    return [
        'productivity_rate' => 28.5,
        'rate_unit' => 'MOVES_PER_HOUR',
        'fixed_overhead_minutes' => 90,
        'valid_from' => '2027-01-01',
        'valid_to' => '2027-06-30',
        ...$overrides,
    ];
}

function profileRow(Terminal $terminal, array $overrides = []): TerminalProductivityProfile
{
    return TerminalProductivityProfile::factory()->create([
        'terminal_id' => $terminal->id,
        'terminal_type' => $terminal->terminal_type,
        'vessel_type_id' => null,
        'valid_from' => '2027-01-01',
        'valid_to' => '2027-06-30',
        ...$overrides,
    ])->refresh();
}

function profilesUrl(Terminal $terminal, ?TerminalProductivityProfile $profile = null): string
{
    return "/api/v1/terminals/{$terminal->id}/productivity-profiles".($profile === null ? '' : "/{$profile->id}");
}

it('creates a profile with the terminal and its type taken from the path, and audits it', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson(profilesUrl($this->terminal), profileBody())
        ->assertCreated()
        ->assertJsonPath('terminal_id', $this->terminal->id)
        ->assertJsonPath('vessel_type_id', null)
        ->assertJsonPath('productivity_rate', 28.5)
        ->assertJsonPath('valid_from', '2027-01-01')
        ->assertJsonPath('valid_to', '2027-06-30');

    $profile = TerminalProductivityProfile::query()->sole();

    expect($profile->terminal_type)->toBe(TerminalType::Container)
        ->and($response->json('version'))->toBe(VersionToken::of($profile))
        ->and(AuditLog::query()->where('action', AuditLogAction::Create)->where('entity_type', 'terminal_productivity_profiles')->sole()->actor_id)->toBe($this->admin->id);
});

it('lists the profiles of the terminal by start date, to its staff and to office roles', function () {
    profileRow($this->terminal, ['valid_from' => '2027-07-01', 'valid_to' => null]);
    profileRow($this->terminal);
    profileRow(Terminal::factory()->create(['terminal_type' => TerminalType::Container]));

    foreach ([$this->admin, makeUserWithRole(Role::PortStaff), makeUserWithRole(Role::TerminalOfficer, unit: $this->unit)] as $reader) {
        $this->actingAs($reader, 'web')->getJson(profilesUrl($this->terminal))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.valid_from', '2027-01-01')
            ->assertJsonPath('data.1.valid_to', null);
    }
});

it('refuses a period that shares a day with another of the same vessel type, with 422', function (string $from, ?string $to) {
    profileRow($this->terminal, ['valid_from' => '2027-03-01', 'valid_to' => '2027-03-31']);

    $this->actingAs($this->admin, 'web')->postJson(profilesUrl($this->terminal), profileBody(['valid_from' => $from, 'valid_to' => $to]))
        ->assertStatus(422)
        ->assertJsonPath('errors.valid_from.0', 'errors.productivity_profiles.overlap');

    expect(TerminalProductivityProfile::query()->count())->toBe(1);
})->with([
    'inside' => ['2027-03-10', '2027-03-20'],
    'around' => ['2027-02-01', '2027-04-30'],
    'same last day' => ['2027-03-31', '2027-04-30'],
    'same first day' => ['2027-02-01', '2027-03-01'],
    'open from before' => ['2027-01-01', null],
]);

it('refuses any period after an open one of the same level', function () {
    profileRow($this->terminal, ['valid_from' => '2027-01-01', 'valid_to' => null]);

    $this->actingAs($this->admin, 'web')->postJson(profilesUrl($this->terminal), profileBody(['valid_from' => '2030-01-01', 'valid_to' => null]))
        ->assertStatus(422);
});

it('allows the next day, another vessel type, the default beside a type, and another terminal', function () {
    $type = VesselType::factory()->create();
    profileRow($this->terminal);
    profileRow(Terminal::factory()->create(['terminal_type' => TerminalType::Container]));

    foreach ([
        ['valid_from' => '2027-07-01', 'valid_to' => '2027-12-31'],
        ['vessel_type_id' => $type->id],
        ['vessel_type_id' => VesselType::factory()->create()->id],
    ] as $body) {
        $this->actingAs($this->admin, 'web')->postJson(profilesUrl($this->terminal), profileBody($body))->assertCreated();
    }

    expect(TerminalProductivityProfile::query()->where('terminal_id', $this->terminal->id)->count())->toBe(4);
});

it('checks overlap on update too, without counting the profile itself', function () {
    $first = profileRow($this->terminal);
    $second = profileRow($this->terminal, ['valid_from' => '2027-07-01', 'valid_to' => '2027-12-31']);

    $this->actingAs($this->admin, 'web')
        ->putJson(profilesUrl($this->terminal, $second), ['valid_from' => '2027-06-30', 'version' => VersionToken::of($second)])
        ->assertStatus(422)
        ->assertJsonPath('errors.valid_from.0', 'errors.productivity_profiles.overlap');
    $this->actingAs($this->admin, 'web')
        ->putJson(profilesUrl($this->terminal, $first), ['valid_to' => '2027-05-31', 'version' => VersionToken::of($first)])
        ->assertOk()
        ->assertJsonPath('valid_to', '2027-05-31');

    expect($second->fresh()->valid_from->format('Y-m-d'))->toBe('2027-07-01');
});

it('refuses invalid fields with 422 and creates nothing', function (array $overrides, string $field) {
    $this->actingAs($this->admin, 'web')->postJson(profilesUrl($this->terminal), profileBody($overrides))
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => [$field]]);

    expect(TerminalProductivityProfile::query()->count())->toBe(0);
})->with([
    'zero rate' => [['productivity_rate' => 0], 'productivity_rate'],
    'rate with 3 decimals' => [['productivity_rate' => 1.555], 'productivity_rate'],
    'negative overhead' => [['fixed_overhead_minutes' => -1], 'fixed_overhead_minutes'],
    'tonnes at a container terminal' => [['rate_unit' => 'TONNES_PER_HOUR'], 'rate_unit'],
    'unknown vessel type' => [['vessel_type_id' => 999999], 'vessel_type_id'],
    'date with a time' => [['valid_from' => '2027-01-01T00:00:00Z'], 'valid_from'],
    'end before start' => [['valid_from' => '2027-02-01', 'valid_to' => '2027-01-31'], 'valid_to'],
    'terminal in the body' => [['terminal_id' => 1], 'terminal_id'],
    'type in the body' => [['terminal_type' => 'CONTAINER'], 'terminal_type'],
]);

it('updates the rate under the version, audits before and after, and refuses a stale version', function () {
    $profile = profileRow($this->terminal, ['productivity_rate' => 20]);
    $stale = VersionToken::of($profile);

    $this->actingAs($this->admin, 'web')->putJson(profilesUrl($this->terminal, $profile), ['productivity_rate' => 22, 'version' => $stale])
        ->assertOk()
        ->assertJsonPath('productivity_rate', 22);
    $this->actingAs($this->admin, 'web')->putJson(profilesUrl($this->terminal, $profile), ['productivity_rate' => 25, 'version' => $stale])
        ->assertStatus(409)
        ->assertJsonPath('code', 'VERSION_CONFLICT');

    $row = AuditLog::query()->where('action', AuditLogAction::Update)->where('entity_type', 'terminal_productivity_profiles')->sole();

    expect((float) $profile->fresh()->productivity_rate)->toBe(22.0)
        ->and((float) $row->before_state['productivity_rate'])->toBe(20.0)
        ->and((float) $row->after_state['productivity_rate'])->toBe(22.0);
});

it('answers 403 for a terminal that is not a container terminal, before validating, and audits it', function () {
    $nct = Terminal::factory()->create(['terminal_type' => TerminalType::NonContainer, 'is_active' => true]);
    $nctAdmin = makeUserWithRole(Role::TerminalAdmin, unit: OrganizationUnit::factory()->create(['terminal_id' => $nct->id]));

    $this->actingAs($nctAdmin, 'web')->postJson(profilesUrl($nct), [])->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    $this->actingAs($nctAdmin, 'web')->getJson(profilesUrl($nct))->assertOk();

    expect(AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->sole()->after_state['reason'])->toBe('role_not_allowed')
        ->and(TerminalProductivityProfile::query()->count())->toBe(0);
});

it('lets only the admin of this terminal write', function () {
    $otherUnit = OrganizationUnit::factory()->create();

    foreach ([makeUserWithRole(Role::PortGm), makeUserWithRole(Role::TerminalOfficer, unit: $this->unit), makeUserWithRole(Role::TerminalAdmin, unit: $otherUnit)] as $user) {
        $this->actingAs($user, 'web')->postJson(profilesUrl($this->terminal), profileBody())->assertForbidden();
    }

    $this->actingAs(makeUserWithRole(Role::TerminalOfficer, unit: $otherUnit), 'web')->getJson(profilesUrl($this->terminal))->assertForbidden();

    expect(TerminalProductivityProfile::query()->count())->toBe(0);
});

it('keeps an inactive terminal read-only for its own staff', function () {
    $this->terminal->update(['is_active' => false]);

    $this->actingAs($this->admin, 'web')->getJson(profilesUrl($this->terminal))->assertOk();
    $this->actingAs($this->admin, 'web')->postJson(profilesUrl($this->terminal), profileBody())
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');
});

it('answers 401 to a guest, 404 for a profile of another terminal, and has no delete', function () {
    $foreign = profileRow(Terminal::factory()->create(['terminal_type' => TerminalType::Container]));

    $this->getJson(profilesUrl($this->terminal))->assertUnauthorized();
    $this->actingAs($this->admin, 'web')->putJson(profilesUrl($this->terminal, $foreign), ['version' => VersionToken::of($foreign)])->assertNotFound();
    $this->actingAs($this->admin, 'web')->deleteJson(profilesUrl($this->terminal, profileRow($this->terminal)))->assertStatus(405);
});
