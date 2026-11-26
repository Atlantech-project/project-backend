<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Enums\FacilityStatus;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\NavigationChannel;
use App\Modules\MasterData\Models\SafetyConstraint;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating, editing and changing the status of berths (P2-F2, FR-MD-04, FR-MD-05, DEC-51). A berth keeps at least one
 * terminal in `berth_terminals`; a terminal is removed only when no safety constraint or recommendation option uses the
 * pair (their composite foreign keys would refuse it). Every change of the terminal list is audited and moves the version.
 */
class BerthService
{
    private const array ATTRIBUTES = ['name', 'length', 'water_depth', 'depth_datum', 'max_vessel_loa', 'latitude', 'longitude'];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{code: string, name?: string|null, length: numeric, water_depth: numeric, depth_datum: string, max_vessel_loa: numeric, latitude?: numeric|null, longitude?: numeric|null, terminal_ids: list<int|string>}  $data
     */
    public function create(array $data, int $actorId): Berth
    {
        return $this->audit->transaction(function () use ($data, $actorId): Berth {
            $terminalIds = $this->normalise($data['terminal_ids']);
            $this->assertActiveTerminals($terminalIds);
            $this->assertDatumMatchesChannels($data['depth_datum']);

            $berth = Berth::query()->create([
                'code' => $data['code'],
                ...Arr::only($data, self::ATTRIBUTES),
                'status' => FacilityStatus::Active,
            ]);
            $berth->terminals()->attach($terminalIds);

            $this->audit->record(AuditLogAction::Create, $berth->getTable(), $berth->id, $actorId, null, [
                ...$berth->attributesToArray(),
                'terminal_ids' => $terminalIds,
            ]);

            return $berth;
        });
    }

    /**
     * @param  array{name?: string|null, length?: numeric, water_depth?: numeric, depth_datum?: string, max_vessel_loa?: numeric, latitude?: numeric|null, longitude?: numeric|null, terminal_ids?: list<int|string>}  $data
     * @param  string  $route  Method and path of the request, for the audit row of a refusal.
     *
     * @throws ApiProblemException 409 CONFLICT when a removed terminal is still used by the pair.
     */
    public function update(Berth $berth, array $data, string $version, User $actor, string $route): Berth
    {
        $outcome = $this->audit->transaction(function () use ($berth, $data, $version, $actor): Berth|string {
            $locked = VersionToken::lockAndAssert($berth, $version);

            $locked->fill(Arr::only($data, self::ATTRIBUTES));
            $this->assertLoaWithinLength($locked);

            if ($locked->isDirty('depth_datum')) {
                $this->assertDatumMatchesChannels($locked->depth_datum);
            }

            $oldIds = $this->normalise($locked->terminals()->pluck('terminals.id')->all());
            $newIds = isset($data['terminal_ids']) ? $this->normalise($data['terminal_ids']) : $oldIds;
            $added = array_values(array_diff($newIds, $oldIds));
            $removed = array_values(array_diff($oldIds, $newIds));

            $this->assertActiveTerminals($added);

            if ($this->isPairInUse($locked->id, $removed)) {
                return 'terminal_in_use';
            }

            $locked->save();
            $this->audit->recordUpdated($locked, $actor->id);

            if ($added !== [] || $removed !== []) {
                $locked->terminals()->sync($newIds);

                if (! $locked->wasChanged()) {
                    $locked->touch();
                }

                $this->audit->record(AuditLogAction::Update, $locked->getTable(), $locked->id, $actor->id, ['terminal_ids' => $oldIds], ['terminal_ids' => $newIds]);
            }

            return $locked;
        });

        if (is_string($outcome)) {
            $this->audit->rejectedAttempt($route, ErrorCode::Conflict, $outcome, $actor->id, $actor->username, 'berths', $berth->id);

            throw ApiProblemException::conflict('errors.berths.'.$outcome);
        }

        return $outcome;
    }

    /**
     * Deactivate, activate or put under maintenance. Already in that status: a no-op (200, nothing written).
     */
    public function changeStatus(Berth $berth, FacilityStatus $status, string $version, int $actorId): Berth
    {
        return $this->audit->transaction(function () use ($berth, $status, $version, $actorId): Berth {
            $locked = VersionToken::lockAndAssert($berth, $version);

            if ($locked->status === $status) {
                return $locked;
            }

            $locked->update(['status' => $status]);
            $this->audit->recordUpdated($locked, $actorId);

            return $locked;
        });
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function normalise(array $ids): array
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<int>  $terminalIds  Only the terminals being linked now; links that already exist are not re-checked (Q6).
     *
     * @throws ValidationException 422 `errors.berths.terminal_inactive`.
     */
    private function assertActiveTerminals(array $terminalIds): void
    {
        if ($terminalIds !== [] && Terminal::query()->whereKey($terminalIds)->where('is_active', false)->exists()) {
            throw ValidationException::withMessages(['terminal_ids' => 'errors.berths.terminal_inactive']);
        }
    }

    /**
     * The berth's depth datum must equal every active navigation channel's (Q5); with no channel there is nothing to compare.
     *
     * @throws ValidationException 422 `errors.berths.datum_mismatch`.
     */
    private function assertDatumMatchesChannels(string $depthDatum): void
    {
        if (NavigationChannel::query()->where('is_active', true)->where('depth_datum', '!=', $depthDatum)->exists()) {
            throw ValidationException::withMessages(['depth_datum' => 'errors.berths.datum_mismatch']);
        }
    }

    /**
     * On update only one of the two may be sent: compare with the stored value (`chk_berth_loa_fits`).
     *
     * @throws ValidationException 422 `errors.berths.loa_exceeds_length`.
     */
    private function assertLoaWithinLength(Berth $berth): void
    {
        if ((float) $berth->max_vessel_loa > (float) $berth->length) {
            throw ValidationException::withMessages(['max_vessel_loa' => 'errors.berths.loa_exceeds_length']);
        }
    }

    /**
     * `recommendation_options` belongs to a later module, so it is read through the query builder (ModuleArchitectureTest).
     *
     * @param  list<int>  $terminalIds
     */
    private function isPairInUse(int $berthId, array $terminalIds): bool
    {
        if ($terminalIds === []) {
            return false;
        }

        return SafetyConstraint::query()->where('berth_id', $berthId)->whereIn('terminal_id', $terminalIds)->exists()
            || DB::table('recommendation_options')->where('berth_id', $berthId)->whereIn('terminal_id', $terminalIds)->exists();
    }
}
