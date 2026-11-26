<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Enums\PilotRank;
use App\Modules\MasterData\Models\PilotRankLimit;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Validation\ValidationException;

/**
 * The largest vessel LOA each pilot rank may handle (P2-F3, FR-MD-09, AI-051). A more senior rank is never limited
 * more than a junior one: FIRST ≥ SECOND ≥ THIRD, NULL (no limit) counting as the largest (Q9). The three rows are
 * locked together, so two changes cannot each pass the check against the other's old value. The key is the rank, not a
 * number, so the audit row has no `entity_id` and names the rank in both states.
 */
class PilotRankLimitService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @throws ValidationException 422 `errors.pilot_rank_limits.order` on `max_vessel_loa`.
     */
    public function update(PilotRankLimit $target, ?string $maxVesselLoa, string $version, int $actorId): PilotRankLimit
    {
        return $this->audit->transaction(function () use ($target, $maxVesselLoa, $version, $actorId): PilotRankLimit {
            $limits = PilotRankLimit::query()->lockForUpdate()->get()->keyBy(fn (PilotRankLimit $limit) => $limit->pilot_rank->value);

            $limit = $limits->get($target->pilot_rank->value);
            VersionToken::assertCurrent($limit, $version);
            $before = $limit->max_vessel_loa;
            $limit->max_vessel_loa = $maxVesselLoa;

            if (! $limit->isDirty()) {
                return $limit;
            }

            if (! $this->isInSeniorityOrder($limits->all())) {
                throw ValidationException::withMessages(['max_vessel_loa' => ['errors.pilot_rank_limits.order']]);
            }

            $limit->save();
            $limit->refresh();
            $this->audit->record(
                AuditLogAction::Update,
                $limit->getTable(),
                null,
                $actorId,
                ['pilot_rank' => $limit->pilot_rank->value, 'max_vessel_loa' => $before],
                ['pilot_rank' => $limit->pilot_rank->value, 'max_vessel_loa' => $limit->max_vessel_loa],
            );

            return $limit;
        });
    }

    /**
     * @param  array<string, PilotRankLimit>  $limits  Keyed by rank.
     */
    private function isInSeniorityOrder(array $limits): bool
    {
        $previous = INF;

        foreach ([PilotRank::First, PilotRank::Second, PilotRank::Third] as $rank) {
            $loa = $limits[$rank->value]->max_vessel_loa ?? null;
            $current = $loa === null ? INF : (float) $loa;

            if ($current > $previous) {
                return false;
            }

            $previous = $current;
        }

        return true;
    }
}
