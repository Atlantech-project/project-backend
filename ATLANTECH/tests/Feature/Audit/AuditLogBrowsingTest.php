<?php

use App\Modules\Access\Models\User;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/*
 * P2-F8 audit log browsing (FR-MD-16). `ability:audit.read` arrives with P2-D1, so these tests register a pass-through
 * `ability` alias; the 403 for the other roles is tested when P2-D1 is merged. Every test is rolled back.
 */

class PassThroughAbility
{
    public function handle($request, Closure $next, string $ability)
    {
        return $next($request);
    }
}

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    app('router')->aliasMiddleware('ability', PassThroughAbility::class);
    $this->admin = User::factory()->create(['name' => 'Amal Admin', 'username' => 'amal.admin']);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function auditRow(array $attributes = []): AuditLog
{
    return AuditLog::factory()->create(['occurred_at' => '2026-11-01 10:00:00', ...$attributes]);
}

it('refuses a guest with 401', function () {
    $this->getJson('/api/v1/audit-logs')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('lists rows newest first with the actor and the states', function () {
    $old = auditRow(['entity_type' => 'berths', 'entity_id' => 3, 'action' => AuditLogAction::Update, 'before_state' => ['name' => 'A'], 'after_state' => ['name' => 'B'], 'actor_id' => $this->admin->id, 'occurred_at' => '2026-11-01 09:00:00']);
    $new = auditRow(['entity_type' => 'berths', 'entity_id' => 3, 'action' => AuditLogAction::Create, 'actor_id' => null, 'occurred_at' => '2026-11-02 09:00:00']);

    $this->actingAs($this->admin, 'web')->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonPath('meta', ['page' => 1, 'per_page' => 25, 'total' => 2])
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.0.actor', null)
        ->assertJsonPath('data.0.occurred_at', '2026-11-02T09:00:00.000000Z')
        ->assertJsonPath('data.1.id', $old->id)
        ->assertJsonPath('data.1.actor', ['id' => $this->admin->id, 'name' => 'Amal Admin', 'username' => 'amal.admin'])
        ->assertJsonPath('data.1.action', 'UPDATE')
        ->assertJsonPath('data.1.before_state', ['name' => 'A'])
        ->assertJsonPath('data.1.after_state', ['name' => 'B']);
});

it('orders rows with the same time by id, newest first', function () {
    $first = auditRow();
    $second = auditRow();

    $this->actingAs($this->admin, 'web')->getJson('/api/v1/audit-logs')
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.1.id', $first->id);
});

it('paginates', function () {
    auditRow();
    auditRow();
    auditRow();

    $this->actingAs($this->admin, 'web')->getJson('/api/v1/audit-logs?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta', ['page' => 2, 'per_page' => 2, 'total' => 3]);
});

it('filters by entity, actor, action and dates', function () {
    $other = User::factory()->create();
    $history = auditRow(['entity_type' => 'berths', 'entity_id' => 3, 'actor_id' => $this->admin->id, 'action' => AuditLogAction::Update, 'before_state' => [], 'after_state' => [], 'occurred_at' => '2026-11-01 10:00:00']);
    auditRow(['entity_type' => 'berths', 'entity_id' => 4, 'actor_id' => $other->id, 'occurred_at' => '2026-10-01 10:00:00']);
    auditRow(['entity_type' => 'terminals', 'entity_id' => 3, 'actor_id' => $this->admin->id, 'occurred_at' => '2026-12-01 10:00:00']);

    $ids = fn (string $query) => collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/audit-logs?'.$query)->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('entity_type=berths&entity_id=3'))->toBe([$history->id])
        ->and($ids('actor_id='.$other->id))->toHaveCount(1)
        ->and($ids('action=UPDATE'))->toBe([$history->id])
        ->and($ids('from=2026-10-15T00:00:00Z&to=2026-11-15T00:00:00Z'))->toBe([$history->id])
        ->and($ids('from=2026-11-01T12:00:00%2B02:00&to=2026-11-01T10:00:00Z'))->toBe([$history->id])
        ->and($ids('entity_type=berths&actor_id='.$this->admin->id))->toBe([$history->id])
        ->and($ids('entity_type=nothing'))->toBe([]);
});

it('searches the actor by username or name', function () {
    $other = User::factory()->create(['name' => 'Omar Officer', 'username' => 'omar.officer']);
    $mine = auditRow(['actor_id' => $this->admin->id]);
    $theirs = auditRow(['actor_id' => $other->id]);
    auditRow(['actor_id' => null]);

    $ids = fn (string $q) => collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/audit-logs?q='.urlencode($q))->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('amal'))->toBe([$mine->id])
        ->and($ids('Officer'))->toBe([$theirs->id])
        ->and($ids('%'))->toBe([]);
});

it('answers 422 for a bad filter', function (string $query, string $field) {
    $this->actingAs($this->admin, 'web')->getJson('/api/v1/audit-logs?'.$query)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['errors' => [$field]]);
})->with([
    'unknown action' => ['action=EXPLODE', 'action'],
    'naive from' => ['from=2026-11-01T10:00:00', 'from'],
    'from after to' => ['from=2026-11-02T00:00:00Z&to=2026-11-01T00:00:00Z', 'from'],
    'per_page 101' => ['per_page=101', 'per_page'],
    'entity_id 0' => ['entity_id=0', 'entity_id'],
]);
