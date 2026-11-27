<?php

namespace App\Modules\Yard\Services;

use App\Modules\Access\Models\User;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\VersionToken;
use App\Modules\Yard\Enums\YardSlotStatus;
use App\Modules\Yard\Models\YardBlock;
use App\Modules\Yard\Models\YardSlot;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The administrative status of yard slots (P2-F4, DEC-29 rule 11): ACTIVE or BLOCKED, one slot or a whole stack (every
 * tier of a bay and row). A slot that holds a container cannot be blocked. container_id is never written here. The block
 * row is locked first, like a size change, so the two cannot interleave or deadlock.
 */
class YardSlotService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly YardRefusal $refusal,
    ) {}

    public function setStatus(YardSlot $slot, YardSlotStatus $status, string $version, User $actor, string $route): YardSlot
    {
        $outcome = $this->audit->transaction(function () use ($slot, $status, $version, $actor): YardSlot|string {
            $this->lockBlock($slot->yard_block_id);
            $locked = VersionToken::lockAndAssert($slot, $version);

            if ($this->wouldBlockAContainer(collect([$locked]), $status)) {
                return 'slot_occupied';
            }

            $this->change($locked, $status, $actor->id);

            return $locked->refresh();
        });

        if (is_string($outcome)) {
            $this->refusal->refuse($outcome, $slot, $actor, $route);
        }

        return $outcome;
    }

    /**
     * All tiers of the stack or none: one occupied slot refuses the whole request.
     *
     * @return Collection<int, YardSlot> The stack ordered by tier.
     *
     * @throws ValidationException 422 when the block has no slot in that bay and row.
     */
    public function setStackStatus(YardBlock $block, int $bay, int $rowNo, YardSlotStatus $status, User $actor, string $route): Collection
    {
        $outcome = $this->audit->transaction(function () use ($block, $bay, $rowNo, $status, $actor): Collection|string {
            $this->lockBlock($block->id);
            $stack = YardSlot::query()
                ->where('yard_block_id', $block->id)->where('bay', $bay)->where('row_no', $rowNo)
                ->orderBy('tier')->lockForUpdate()->get();

            if ($stack->isEmpty()) {
                throw ValidationException::withMessages(['bay' => ['errors.yard.stack_not_found']]);
            }

            if ($this->wouldBlockAContainer($stack, $status)) {
                return 'slot_occupied';
            }

            $stack->each(fn (YardSlot $slot) => $this->change($slot, $status, $actor->id));

            return $stack->map(fn (YardSlot $slot): YardSlot => $slot->refresh());
        });

        if (is_string($outcome)) {
            $this->refusal->refuse($outcome, $block, $actor, $route);
        }

        return $outcome;
    }

    private function lockBlock(int $blockId): void
    {
        YardBlock::query()->lockForUpdate()->findOrFail($blockId);
    }

    /**
     * @param  Collection<int, YardSlot>  $slots
     */
    private function wouldBlockAContainer(Collection $slots, YardSlotStatus $status): bool
    {
        return $status === YardSlotStatus::Blocked && $slots->contains(fn (YardSlot $slot): bool => $slot->container_id !== null);
    }

    private function change(YardSlot $slot, YardSlotStatus $status, int $actorId): void
    {
        if ($slot->status === $status) {
            return;
        }

        $slot->update(['status' => $status]);
        $this->audit->recordUpdated($slot, $actorId);
    }
}
