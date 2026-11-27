<?php

namespace App\Modules\Yard\Services;

use App\Modules\Access\Models\User;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Support\VersionToken;
use App\Modules\Yard\Enums\YardSlotStatus;
use App\Modules\Yard\Models\YardBlock;
use App\Modules\Yard\Models\YardPlacementItem;
use App\Modules\Yard\Models\YardSlot;
use App\Modules\Yard\Models\YardZone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Yard blocks and their size (P2-F4, FR-MD-12, DEC-29 G-03). A block is regular: every bay and row has the same tiers.
 * Setting the size adds the missing slots and removes the extra ones; kept slots are not touched. A slot is removed only
 * if it holds no container, no placement item refers to it and it is not BLOCKED (it may stand for unrecorded old stock),
 * and no yard run of the terminal is in progress. Every change to a block's slots locks the block row first.
 */
class YardBlockService
{
    private const int CHUNK = 500;

    public function __construct(
        private readonly AuditService $audit,
        private readonly YardRunGuard $runs,
        private readonly YardRefusal $refusal,
    ) {}

    /**
     * The zone row is locked so the zone cannot be deleted while a block is added to it.
     */
    public function create(YardZone $zone, string $code, int $actorId): YardBlock
    {
        return $this->audit->transaction(function () use ($zone, $code, $actorId): YardBlock {
            $lockedZone = YardZone::query()->lockForUpdate()->findOrFail($zone->id);

            try {
                $block = $lockedZone->yardBlocks()->create(['code' => $code]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['code' => ['validation.unique']]);
            }

            $block->refresh();
            $this->audit->recordCreated($block, $actorId);

            return $block;
        });
    }

    public function update(YardBlock $block, string $code, string $version, int $actorId): YardBlock
    {
        return $this->audit->transaction(function () use ($block, $code, $version, $actorId): YardBlock {
            $locked = VersionToken::lockAndAssert($block, $version);
            $locked->fill(['code' => $code]);

            try {
                $locked->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['code' => ['validation.unique']]);
            }

            $this->audit->recordUpdated($locked, $actorId);

            return $locked->refresh();
        });
    }

    public function resize(YardBlock $block, int $bays, int $rows, int $tiers, string $version, User $actor, string $route): YardBlock
    {
        $outcome = $this->audit->transaction(function () use ($block, $bays, $rows, $tiers, $version, $actor): YardBlock|string {
            $locked = VersionToken::lockAndAssert($block, $version);
            $slots = $locked->yardSlots()->lockForUpdate()->get();

            $extra = $slots->filter(fn (YardSlot $slot): bool => $slot->bay > $bays || $slot->row_no > $rows || $slot->tier > $tiers);
            $reason = $this->reasonNotToRemove($locked, $extra);

            if ($reason !== null) {
                return $reason;
            }

            $missing = $this->missingPositions($locked->id, $slots, $bays, $rows, $tiers);

            if ($extra->isEmpty() && $missing === []) {
                return $locked;
            }

            $this->removeSlots($extra);

            foreach (array_chunk($missing, self::CHUNK) as $chunk) {
                YardSlot::query()->insert($chunk);
            }

            $locked->touch();
            $this->audit->record(
                AuditLogAction::Update,
                'yard_blocks',
                $locked->id,
                $actor->id,
                ['slots' => $slots->count()],
                [
                    'bays' => $bays, 'rows' => $rows, 'tiers' => $tiers,
                    'slots' => $bays * $rows * $tiers, 'added' => count($missing), 'removed' => $extra->count(),
                ],
            );

            return $locked->refresh();
        });

        if (is_string($outcome)) {
            $this->refusal->refuse($outcome, $block, $actor, $route);
        }

        return $outcome;
    }

    /**
     * Delete a block that is not used, with its slots, under the same rules as removing slots.
     */
    public function delete(YardBlock $block, string $version, User $actor, string $route): void
    {
        $refused = $this->audit->transaction(function () use ($block, $version, $actor): ?string {
            $locked = VersionToken::lockAndAssert($block, $version);
            $slots = $locked->yardSlots()->lockForUpdate()->get();

            if (YardPlacementItem::query()->where('yard_block_id', $locked->id)->exists()) {
                return 'slots_in_use';
            }

            if ($this->runs->isRunInProgress($locked->yardZone->containerYard->terminal_id)) {
                return 'run_in_progress';
            }

            $reason = $this->reasonNotToRemove($locked, $slots);

            if ($reason !== null) {
                return $reason;
            }

            $this->removeSlots($slots);
            $locked->delete();
            $this->audit->record(AuditLogAction::Delete, 'yard_blocks', $locked->id, $actor->id, [
                'id' => $locked->id, 'yard_zone_id' => $locked->yard_zone_id, 'code' => $locked->code, 'slots' => $slots->count(),
            ], null);

            return null;
        });

        if ($refused !== null) {
            $this->refusal->refuse($refused, $block, $actor, $route);
        }
    }

    /**
     * @param  Collection<int, YardSlot>  $slots  Locked rows that would be removed.
     */
    private function reasonNotToRemove(YardBlock $block, Collection $slots): ?string
    {
        if ($slots->isEmpty()) {
            return null;
        }

        if ($this->runs->isRunInProgress($block->yardZone->containerYard->terminal_id)) {
            return 'run_in_progress';
        }

        $held = $slots->contains(fn (YardSlot $slot): bool => $slot->container_id !== null || $slot->status === YardSlotStatus::Blocked);

        if ($held || YardPlacementItem::query()->whereIn('yard_slot_id', $slots->pluck('id'))->exists()) {
            return 'slots_in_use';
        }

        return null;
    }

    /**
     * @param  Collection<int, YardSlot>  $slots
     */
    private function removeSlots(Collection $slots): void
    {
        foreach ($slots->pluck('id')->chunk(self::CHUNK) as $ids) {
            YardSlot::query()->whereIn('id', $ids->all())->delete();
        }
    }

    /**
     * @param  Collection<int, YardSlot>  $slots
     * @return list<array{yard_block_id: int, bay: int, row_no: int, tier: int, status: string}>
     */
    private function missingPositions(int $blockId, Collection $slots, int $bays, int $rows, int $tiers): array
    {
        $existing = $slots->mapWithKeys(fn (YardSlot $slot): array => ["{$slot->bay}-{$slot->row_no}-{$slot->tier}" => true]);
        $missing = [];

        for ($bay = 1; $bay <= $bays; $bay++) {
            for ($row = 1; $row <= $rows; $row++) {
                for ($tier = 1; $tier <= $tiers; $tier++) {
                    if (! $existing->has("{$bay}-{$row}-{$tier}")) {
                        $missing[] = ['yard_block_id' => $blockId, 'bay' => $bay, 'row_no' => $row, 'tier' => $tier, 'status' => YardSlotStatus::Active->value];
                    }
                }
            }
        }

        return $missing;
    }
}
