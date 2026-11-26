<?php

namespace App\Modules\Shared\Events;

use App\Modules\Shared\Enums\ResourceKind;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A berth, tug, pilot or equipment row changed (P2-F7, AIBD-004). The listener (P4-K5) finds the pending recommendations that use the
 * resource and enqueues a C-02 `RESOURCE_CHANGE` job.
 *
 * Dispatch it inside the write transaction; listeners run only after the outermost commit and never after a rollback:
 *
 * ```php
 * $audit->transaction(function () use ($audit, $berth, $actorId) {
 *     $berth->save();
 *     $audit->recordUpdated($berth, $actorId);
 *     ResourceChanged::dispatch(ResourceKind::Berth, $berth->id, $actorId);
 * });
 * ```
 *
 * The resource id is `berths.id`, `tugboats.id`, the pilot's `users.id` or `equipment.id`. No "what changed" field (CT-10): the
 * listener reads the current row.
 */
final class ResourceChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly ResourceKind $kind,
        public readonly int $resourceId,
        public readonly ?int $actorId,
    ) {}
}
