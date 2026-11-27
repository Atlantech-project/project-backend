<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Arr;

/**
 * The pilot roster of the Pilotage Manager (P2-F3, FR-MD-09): create (user, `MARINE_PILOT` role and profile in one
 * transaction), update, promote and deactivate. The profile's `updated_at` is the version of the whole pilot, so a
 * change to the user alone also moves it. P2-F7 dispatches `ResourceChanged` (PILOT) after a saved change here.
 */
class PilotService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly CrewMemberService $crew,
    ) {}

    /**
     * @param  array{name: string, username: string, phone?: string|null, pilot_rank: string}  $data
     * @return array{pilot: PilotProfile, one_time_password: string}
     */
    public function create(array $data, int $actorId): array
    {
        return $this->audit->transaction(function () use ($data, $actorId): array {
            $created = $this->crew->createUser($data, Role::MarinePilot, $actorId);

            $pilot = PilotProfile::query()->create(['user_id' => $created['user']->id, 'pilot_rank' => $data['pilot_rank']]);
            $this->audit->recordCreated($pilot, $actorId);

            return ['pilot' => $pilot->refresh(), 'one_time_password' => $created['one_time_password']];
        });
    }

    /**
     * @param  array{name?: string, phone?: string|null, pilot_rank?: string}  $data
     */
    public function update(PilotProfile $target, array $data, string $version, int $actorId): PilotProfile
    {
        return $this->audit->transaction(function () use ($target, $data, $version, $actorId): PilotProfile {
            $pilot = VersionToken::lockAndAssert($target, $version);
            $userChanged = $this->crew->updateUser($this->crew->lockMember($pilot->user_id, Role::MarinePilot), $data, $actorId);

            $pilot->fill(Arr::only($data, ['pilot_rank']));

            if ($pilot->isDirty()) {
                $pilot->save();
                $this->audit->recordUpdated($pilot, $actorId);
            } elseif ($userChanged) {
                $pilot->touch();
            }

            return $pilot->refresh();
        });
    }

    /**
     * Deactivate keeps the profile and the schedule rows (Q4); activate checks the active-user plan limit.
     */
    public function setActive(PilotProfile $target, bool $active, string $version, int $actorId): PilotProfile
    {
        return $this->audit->transaction(function () use ($target, $active, $version, $actorId): PilotProfile {
            $pilot = VersionToken::lockAndAssert($target, $version);

            if ($this->crew->setActive($this->crew->lockMember($pilot->user_id, Role::MarinePilot), $active, $actorId)) {
                $pilot->touch();
            }

            return $pilot->refresh();
        });
    }
}
