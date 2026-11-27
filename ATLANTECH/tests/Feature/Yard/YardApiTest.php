<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\MasterData\Models\ImdgClass;
use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\MasterData\Models\StorageType;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Enums\RunStatus;
use App\Modules\Shared\Enums\StorageDetailKind;
use App\Modules\Shared\Enums\TerminalType;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use App\Modules\Yard\Enums\YardSlotStatus;
use App\Modules\Yard\Enums\ZoneDetail;
use App\Modules\Yard\Models\DgZoneAllowedClass;
use App\Modules\Yard\Models\ReeferZoneDetail;
use App\Modules\Yard\Models\TerminalRecommendation;
use App\Modules\Yard\Models\YardBlock;
use App\Modules\Yard\Models\YardRun;
use App\Modules\Yard\Models\YardSlot;
use App\Modules\Yard\Models\YardZone;
use App\Modules\Yard\Models\YardZoneType;
use App\Modules\Yard\Services\YardRunGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * P2-F4 yard (FR-MD-12, DEC-29 G-03 and rule 11): the container yard record, zones with their detail, blocks and their
 * size, slot and stack status, deleting unused records, and the terminal isolation. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $unit = OrganizationUnit::factory()->create();
    $this->unit = $unit;
    $this->terminal = Terminal::query()->findOrFail($unit->terminal_id);
    $this->terminal->update(['terminal_type' => TerminalType::Container, 'is_active' => true]);
    $this->admin = makeUserWithRole(Role::TerminalAdmin, unit: $unit);
    $this->officer = makeUserWithRole(Role::TerminalOfficer, unit: $unit);
    $this->yardType = StorageType::query()->where('detail_kind', StorageDetailKind::ContainerYard)->first()
        ?? StorageType::factory()->create(['detail_kind' => StorageDetailKind::ContainerYard]);
    $this->types = collect([ZoneDetail::None, ZoneDetail::Reefer, ZoneDetail::DangerousGoods, ZoneDetail::Oog])
        ->mapWithKeys(fn (ZoneDetail $detail) => [$detail->value => YardZoneType::factory()->create(['zone_detail' => $detail])]);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function yardUrl(string $suffix = '', ?Terminal $terminal = null): string
{
    return '/api/v1/terminals/'.($terminal ?? test()->terminal)->id.'/'.$suffix;
}

function makeYard(?Terminal $terminal = null): StorageFacility
{
    return StorageFacility::factory()->create([
        'terminal_id' => ($terminal ?? test()->terminal)->id,
        'terminal_type' => TerminalType::Container,
        'storage_type_id' => test()->yardType->id,
        'detail_kind' => StorageDetailKind::ContainerYard,
        'capacity' => null,
        'capacity_unit' => null,
    ])->refresh();
}

function makeZone(?StorageFacility $yard = null, ZoneDetail $detail = ZoneDetail::None): YardZone
{
    return YardZone::factory()->create([
        'container_yard_id' => ($yard ?? makeYard())->id,
        'yard_zone_type_id' => test()->types[$detail->value]->id,
        'zone_detail' => $detail,
    ])->refresh();
}

function makeBlock(?YardZone $zone = null, int $bays = 0, int $rows = 1, int $tiers = 1): YardBlock
{
    $block = YardBlock::factory()->create(['yard_zone_id' => ($zone ?? makeZone())->id])->refresh();

    for ($bay = 1; $bay <= $bays; $bay++) {
        for ($row = 1; $row <= $rows; $row++) {
            for ($tier = 1; $tier <= $tiers; $tier++) {
                YardSlot::factory()->create(['yard_block_id' => $block->id, 'bay' => $bay, 'row_no' => $row, 'tier' => $tier]);
            }
        }
    }

    return $block;
}

function slotAt(YardBlock $block, int $bay, int $row = 1, int $tier = 1): YardSlot
{
    return YardSlot::query()->where(['yard_block_id' => $block->id, 'bay' => $bay, 'row_no' => $row, 'tier' => $tier])->sole()->refresh();
}

function runInProgress(bool $running = true): void
{
    app()->instance(YardRunGuard::class, new class($running) extends YardRunGuard
    {
        public function __construct(private bool $running) {}

        public function isRunInProgress(int $terminalId): bool
        {
            return $this->running;
        }
    });
}

it('creates a container yard with no capacity of its own, and audits it', function () {
    $this->actingAs($this->admin, 'web')
        ->postJson(yardUrl('yards'), ['code' => 'CY-1', 'name' => 'Main yard', 'area' => 1200.5])
        ->assertCreated()
        ->assertJsonPath('code', 'CY-1')
        ->assertJsonPath('status', 'ACTIVE');

    $yard = StorageFacility::query()->where('code', 'CY-1')->sole();

    expect($yard->detail_kind)->toBe(StorageDetailKind::ContainerYard)
        ->and($yard->capacity)->toBeNull()
        ->and(AuditLog::query()->where('action', AuditLogAction::Create)->pluck('entity_type')->all())->toBe(['storage_facilities']);
});

it('refuses a capacity, a repeated code and a non-container terminal with 422', function () {
    $yard = makeYard();

    $this->actingAs($this->admin, 'web')->postJson(yardUrl('yards'), ['code' => 'X', 'name' => 'X', 'capacity' => 5])
        ->assertUnprocessable()->assertJsonValidationErrorFor('capacity', 'errors');
    $this->actingAs($this->admin, 'web')->postJson(yardUrl('yards'), ['code' => $yard->code, 'name' => 'X'])
        ->assertUnprocessable()->assertJsonValidationErrorFor('code', 'errors');

    $other = OrganizationUnit::factory()->create();
    $nct = Terminal::query()->findOrFail($other->terminal_id);
    $nct->update(['terminal_type' => TerminalType::NonContainer, 'is_active' => true]);

    $this->actingAs(makeUserWithRole(Role::TerminalAdmin, unit: $other), 'web')
        ->postJson(yardUrl('yards', $nct), ['code' => 'N', 'name' => 'N'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.terminal', ['errors.yard.not_container_terminal']);
});

it('updates a yard under the version and refuses a stale one', function () {
    $yard = makeYard();

    $this->actingAs($this->admin, 'web')
        ->putJson(yardUrl("yards/{$yard->id}"), ['status' => 'INACTIVE', 'version' => VersionToken::of($yard)])
        ->assertOk()->assertJsonPath('status', 'INACTIVE');
    $this->actingAs($this->admin, 'web')
        ->putJson(yardUrl("yards/{$yard->id}"), ['name' => 'Y', 'version' => VersionToken::of($yard)])
        ->assertConflict()->assertJsonPath('code', 'VERSION_CONFLICT');
});

it('creates each kind of zone with its detail', function (ZoneDetail $detail, array $body, ?array $expected) {
    $yard = makeYard();
    if ($detail === ZoneDetail::DangerousGoods) {
        ImdgClass::query()->firstOrCreate(['code' => '3'], ['name' => 'Flammable liquids']);
        ImdgClass::query()->firstOrCreate(['code' => '8'], ['name' => 'Corrosives']);
    }

    $this->actingAs($this->admin, 'web')
        ->postJson(yardUrl("yards/{$yard->id}/zones"), ['code' => 'Z1', 'yard_zone_type_id' => $this->types[$detail->value]->id, ...$body])
        ->assertCreated()
        ->assertJsonPath('zone_detail', $detail->value)
        ->assertJsonPath('detail', $expected)
        ->assertJsonPath('capacity.slots', 0);
})->with([
    'general' => [ZoneDetail::None, [], null],
    'reefer' => [ZoneDetail::Reefer, ['total_power_plugs' => 40, 'power_type' => '380V'], ['total_power_plugs' => 40, 'power_type' => '380V']],
    'dangerous goods' => [ZoneDetail::DangerousGoods, ['imdg_classes' => ['8', '3']], ['imdg_classes' => ['3', '8']]],
    'out of gauge' => [ZoneDetail::Oog, ['max_length' => 12.5, 'max_width' => null, 'max_height' => 3, 'max_weight' => null], ['max_length' => 12.5, 'max_width' => null, 'max_height' => 3, 'max_weight' => null]],
]);

it('refuses a missing detail, a detail of another kind and a changed zone type with 422', function () {
    $yard = makeYard();
    $url = yardUrl("yards/{$yard->id}/zones");

    $this->actingAs($this->admin, 'web')->postJson($url, ['code' => 'R', 'yard_zone_type_id' => $this->types['REEFER']->id])
        ->assertUnprocessable()->assertJsonValidationErrorFor('total_power_plugs', 'errors');
    $this->actingAs($this->admin, 'web')->postJson($url, ['code' => 'G', 'yard_zone_type_id' => $this->types['NONE']->id, 'total_power_plugs' => 4])
        ->assertUnprocessable()->assertJsonValidationErrorFor('total_power_plugs', 'errors');

    $zone = makeZone($yard);
    $this->actingAs($this->admin, 'web')
        ->putJson(yardUrl("yard-zones/{$zone->id}"), ['code' => 'G', 'yard_zone_type_id' => $this->types['REEFER']->id, 'version' => VersionToken::of($zone)])
        ->assertUnprocessable()->assertJsonValidationErrorFor('yard_zone_type_id', 'errors');
});

it('moves the zone version when only its detail changes', function () {
    $zone = makeZone(detail: ZoneDetail::Reefer);
    ReeferZoneDetail::factory()->create(['yard_zone_id' => $zone->id, 'total_power_plugs' => 80, 'power_type' => null]);
    $before = VersionToken::of($zone);

    $this->actingAs($this->admin, 'web')
        ->putJson(yardUrl("yard-zones/{$zone->id}"), ['code' => $zone->code, 'total_power_plugs' => 100, 'power_type' => null, 'version' => $before])
        ->assertOk()
        ->assertJsonPath('detail.total_power_plugs', 100)
        ->assertJsonPath('version', fn (string $version) => $version !== $before);
});

it('replaces the allowed IMDG classes and audits the list', function () {
    ImdgClass::query()->firstOrCreate(['code' => '3'], ['name' => 'Flammable liquids']);
    ImdgClass::query()->firstOrCreate(['code' => '8'], ['name' => 'Corrosives']);
    $zone = makeZone(detail: ZoneDetail::DangerousGoods);
    DgZoneAllowedClass::query()->insert([['yard_zone_id' => $zone->id, 'zone_detail' => 'DANGEROUS_GOODS', 'imdg_class' => '3']]);

    $this->actingAs($this->admin, 'web')
        ->putJson(yardUrl("yard-zones/{$zone->id}"), ['code' => $zone->code, 'imdg_classes' => ['8'], 'version' => VersionToken::of($zone)])
        ->assertOk()->assertJsonPath('detail.imdg_classes', ['8']);

    $row = AuditLog::query()->where('entity_type', 'dg_zone_allowed_classes')->sole();

    expect($row->action)->toBe(AuditLogAction::Update)
        ->and($row->before_state)->toBe(['imdg_classes' => ['3']])
        ->and($row->after_state)->toBe(['imdg_classes' => ['8']]);
});

it('creates every slot of a block, then grows and shrinks it without touching the kept slots', function () {
    $block = makeBlock();
    $url = yardUrl("yard-blocks/{$block->id}/layout");

    $this->actingAs($this->admin, 'web')->putJson($url, ['bays' => 3, 'rows' => 2, 'tiers' => 2, 'version' => VersionToken::of($block)])
        ->assertOk()->assertJsonPath('slots_count', 12)->assertJsonPath('bays', 3);
    DB::table('yard_slots')->where('id', slotAt($block, 1)->id)->update(['status' => 'BLOCKED']);
    $kept = slotAt($block, 1)->id;

    $this->actingAs($this->admin, 'web')->putJson($url, ['bays' => 2, 'rows' => 2, 'tiers' => 3, 'version' => VersionToken::of($block->refresh())])
        ->assertOk()->assertJsonPath('slots_count', 12)->assertJsonPath('bays', 2)->assertJsonPath('tiers', 3);

    expect(slotAt($block, 1)->id)->toBe($kept)
        ->and(slotAt($block, 1)->status)->toBe(YardSlotStatus::Blocked)
        ->and(AuditLog::query()->where('entity_type', 'yard_blocks')->latest('id')->first()->after_state)
        ->toMatchArray(['added' => 4, 'removed' => 4, 'slots' => 12]);
});

it('refuses to remove a blocked slot or slots while a yard run is in progress, changes nothing and audits the refusal', function (string $reason, Closure $arrange) {
    $block = makeBlock(bays: 3);
    $arrange($block);

    $this->actingAs($this->admin, 'web')
        ->putJson(yardUrl("yard-blocks/{$block->id}/layout"), ['bays' => 2, 'rows' => 1, 'tiers' => 1, 'version' => VersionToken::of($block->refresh())])
        ->assertConflict()->assertJsonPath('code', 'CONFLICT');

    expect(YardSlot::query()->where('yard_block_id', $block->id)->count())->toBe(3)
        ->and(AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->sole()->after_state['reason'])->toBe($reason);
})->with([
    'a blocked slot' => ['slots_in_use', fn (YardBlock $block) => DB::table('yard_slots')->where('id', slotAt($block, 3)->id)->update(['status' => 'BLOCKED'])],
    'a yard run' => ['run_in_progress', fn () => runInProgress()],
]);

it('lets a block grow while a yard run is in progress', function () {
    $block = makeBlock(bays: 1);
    runInProgress();

    $this->actingAs($this->admin, 'web')
        ->putJson(yardUrl("yard-blocks/{$block->id}/layout"), ['bays' => 2, 'rows' => 1, 'tiers' => 1, 'version' => VersionToken::of($block)])
        ->assertOk()->assertJsonPath('slots_count', 2);
});

it('refuses a size above the limit with 422', function () {
    $block = makeBlock();

    $this->actingAs($this->admin, 'web')
        ->putJson(yardUrl("yard-blocks/{$block->id}/layout"), ['bays' => 200, 'rows' => 200, 'tiers' => 2, 'version' => VersionToken::of($block)])
        ->assertUnprocessable()->assertJsonPath('errors.bays', ['errors.yard.layout_too_large']);
});

it('blocks and unblocks one slot and a whole stack, by the officer too', function () {
    $block = makeBlock(bays: 2, tiers: 3);
    $slot = slotAt($block, 2);

    $this->actingAs($this->officer, 'web')
        ->putJson(yardUrl("yard-slots/{$slot->id}"), ['status' => 'BLOCKED', 'version' => VersionToken::of($slot)])
        ->assertOk()->assertJsonPath('status', 'BLOCKED')->assertJsonPath('occupied', false);

    $this->actingAs($this->officer, 'web')
        ->postJson(yardUrl("yard-blocks/{$block->id}/stack-status"), ['bay' => 1, 'row_no' => 1, 'status' => 'BLOCKED'])
        ->assertOk()->assertJsonCount(3, 'data');

    expect(YardSlot::query()->where('yard_block_id', $block->id)->where('status', 'BLOCKED')->count())->toBe(4);

    $this->actingAs($this->officer, 'web')
        ->postJson(yardUrl("yard-blocks/{$block->id}/stack-status"), ['bay' => 9, 'row_no' => 1, 'status' => 'ACTIVE'])
        ->assertUnprocessable()->assertJsonValidationErrorFor('bay', 'errors');
});

it('refuses a container id', function () {
    $slot = slotAt(makeBlock(bays: 1), 1);

    $this->actingAs($this->admin, 'web')
        ->putJson(yardUrl("yard-slots/{$slot->id}"), ['status' => 'ACTIVE', 'container_id' => 5, 'version' => VersionToken::of($slot)])
        ->assertUnprocessable()->assertJsonValidationErrorFor('container_id', 'errors');
});

it('counts the zone capacity from the slots', function () {
    $zone = makeZone();
    $block = makeBlock($zone, bays: 2, tiers: 2);
    DB::table('yard_slots')->where('id', slotAt($block, 1)->id)->update(['status' => 'BLOCKED']);

    $this->actingAs($this->officer, 'web')->getJson(yardUrl("yard-zones/{$zone->id}"))
        ->assertOk()->assertJsonPath('capacity', ['slots' => 4, 'blocked' => 1, 'occupied' => 0])->assertJsonPath('blocks_count', 1);
});

it('lists the slots of a block with filters', function () {
    $block = makeBlock(bays: 3, rows: 2);

    $this->actingAs($this->officer, 'web')->getJson(yardUrl("yard-blocks/{$block->id}/slots?bay=2"))
        ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.bay', 2);
});

it('deletes an unused block and then its empty zone, with DELETE audit rows', function () {
    ImdgClass::query()->firstOrCreate(['code' => '3'], ['name' => 'Flammable liquids']);
    $zone = makeZone(detail: ZoneDetail::DangerousGoods);
    DgZoneAllowedClass::query()->insert([['yard_zone_id' => $zone->id, 'zone_detail' => 'DANGEROUS_GOODS', 'imdg_class' => '3']]);
    $block = makeBlock($zone, bays: 2);

    $this->actingAs($this->admin, 'web')->deleteJson(yardUrl("yard-zones/{$zone->id}"), ['version' => VersionToken::of($zone)])
        ->assertConflict();
    $this->actingAs($this->admin, 'web')->deleteJson(yardUrl("yard-blocks/{$block->id}"), ['version' => VersionToken::of($block)])
        ->assertNoContent();
    $this->actingAs($this->admin, 'web')->deleteJson(yardUrl("yard-zones/{$zone->id}"), ['version' => VersionToken::of($zone->refresh())])
        ->assertNoContent();

    expect(YardZone::query()->whereKey($zone->id)->exists())->toBeFalse()
        ->and(YardSlot::query()->where('yard_block_id', $block->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', AuditLogAction::Delete)->pluck('entity_type')->sort()->values()->all())
        ->toBe(['dg_zone_allowed_classes', 'yard_blocks', 'yard_zones']);
});

it('refuses to delete a block with a blocked slot, and a zone while a yard run is in progress', function () {
    $block = makeBlock(bays: 1);
    DB::table('yard_slots')->where('id', slotAt($block, 1)->id)->update(['status' => 'BLOCKED']);

    $this->actingAs($this->admin, 'web')->deleteJson(yardUrl("yard-blocks/{$block->id}"), ['version' => VersionToken::of($block)])
        ->assertConflict()->assertJsonPath('code', 'CONFLICT');

    $zone = makeZone();
    runInProgress();
    $this->actingAs($this->admin, 'web')->deleteJson(yardUrl("yard-zones/{$zone->id}"), ['version' => VersionToken::of($zone)])
        ->assertConflict()->assertJsonPath('code', 'CONFLICT');

    expect(YardBlock::query()->whereKey($block->id)->exists())->toBeTrue()
        ->and(YardZone::query()->whereKey($zone->id)->exists())->toBeTrue();
});

it('sees a queued or running yard run of the terminal only', function (RunStatus $status, bool $expected) {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $recommendation = TerminalRecommendation::factory()->create([
        'recommendation_id' => 1,
        'terminal_id' => $this->terminal->id,
        'lifecycle_status' => 'YARD_REC_REQUESTED',
    ]);
    YardRun::factory()->create([
        'terminal_recommendation_id' => $recommendation->id,
        'status' => $status,
        'finished_at' => $status === RunStatus::Replaced ? now() : null,
    ]);
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    expect((new YardRunGuard)->isRunInProgress($this->terminal->id))->toBe($expected)
        ->and((new YardRunGuard)->isRunInProgress($this->terminal->id + 1))->toBeFalse();
})->with([
    'queued' => [RunStatus::Queued, true],
    'running' => [RunStatus::Running, true],
    'replaced' => [RunStatus::Replaced, false],
]);

it('answers 404 for a record of another terminal under this one, and 403 under the other terminal', function () {
    $other = OrganizationUnit::factory()->create();
    $foreignTerminal = Terminal::query()->findOrFail($other->terminal_id);
    $foreignTerminal->update(['terminal_type' => TerminalType::Container]);
    $yard = makeYard($foreignTerminal);
    $zone = makeZone($yard);
    $block = makeBlock($zone, bays: 1);
    $slot = slotAt($block, 1);

    foreach (["yards/{$yard->id}", "yard-zones/{$zone->id}", "yard-blocks/{$block->id}", "yard-blocks/{$block->id}/slots", "yards/{$yard->id}/zones"] as $path) {
        $this->actingAs($this->admin, 'web')->getJson(yardUrl($path))->assertNotFound();
    }

    $this->actingAs($this->admin, 'web')->putJson(yardUrl("yard-slots/{$slot->id}"), ['status' => 'BLOCKED', 'version' => '2020-01-01T00:00:00.000000Z'])->assertNotFound();
    $this->actingAs($this->admin, 'web')->getJson(yardUrl("yard-zones/{$zone->id}", $foreignTerminal))->assertForbidden();

    expect(AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->count())->toBe(1);
});

it('lets the officer read the yard but not change its structure', function () {
    $block = makeBlock(bays: 1);

    $this->actingAs($this->officer, 'web')->getJson(yardUrl("yard-blocks/{$block->id}"))->assertOk();
    $this->actingAs($this->officer, 'web')->postJson(yardUrl('yards'), [])->assertForbidden();
    $this->actingAs($this->officer, 'web')->putJson(yardUrl("yard-blocks/{$block->id}/layout"), [])->assertForbidden();
    $this->actingAs($this->officer, 'web')->deleteJson(yardUrl("yard-blocks/{$block->id}"))->assertForbidden();
});

it('lets port-wide roles read and refuses their writes, and refuses guests', function () {
    $yard = makeYard();

    $this->getJson(yardUrl('yards'))->assertUnauthorized();
    $gm = makeUserWithRole(Role::PortGm);
    $this->actingAs($gm, 'web')->getJson(yardUrl('yards'))->assertOk()->assertJsonPath('meta.total', 1);
    $this->actingAs($gm, 'web')->putJson(yardUrl("yards/{$yard->id}"), [])->assertForbidden();
});

it('puts every yard write route in port.api behind its ability and the terminal scope', function () {
    $abilities = [
        'yard.manage' => ['yards.store', 'yards.update', 'yard-zones.store', 'yard-zones.update', 'yard-zones.destroy', 'yard-blocks.store', 'yard-blocks.update', 'yard-blocks.destroy', 'yard-blocks.layout'],
        'yard-slot-status.update' => ['yard-slots.update', 'yard-blocks.stack-status'],
    ];

    foreach ($abilities as $ability => $names) {
        foreach ($names as $name) {
            $middleware = Route::getRoutes()->getByName("terminals.{$name}")->gatherMiddleware();

            expect($middleware)->toContain('port.api', "ability:{$ability}", 'terminal.scope');
        }
    }
});

it('lists the zone types and IMDG classes', function () {
    $this->actingAs($this->officer, 'web')->getJson('/api/v1/yard-zone-types?per_page=100')
        ->assertOk()->assertJsonStructure(['data' => [['id', 'code', 'name', 'zone_detail']]]);
    ImdgClass::query()->firstOrCreate(['code' => '3'], ['name' => 'Flammable liquids']);
    $this->actingAs($this->officer, 'web')->getJson('/api/v1/imdg-classes?per_page=100')
        ->assertOk()->assertJsonStructure(['data' => [['code', 'name']]]);
});
