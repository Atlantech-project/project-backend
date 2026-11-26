<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\MasterData\Models\TugMasterProfile;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Validation\ValidationException;

/**
 * Tug masters of the Tug Manager (P2-F3, FR-MD-08): create (user, `TUG_MASTER` role and profile in one transaction),
 * update, link to a tug and deactivate. A tug has at most one tug master: every link locks the tug row first, so two
 * links to the same tug run one after the other (the UNIQUE index is the last guard). Deactivating clears the link in
 * the same transaction. P2-F7 dispatches `ResourceChanged` as TUG with the tug id (CT-10) after a saved change here.
 */
class TugMasterService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly CrewMemberService $crew,
    ) {}

    /**
     * @param  array{name: string, username: string, phone?: string|null, tugboat_id?: int|null}  $data
     * @return array{tug_master: TugMasterProfile, one_time_password: string}
     *
     * @throws ValidationException 422 `errors.tug_masters.tug_taken` on `tugboat_id`.
     */
    public function create(array $data, int $actorId): array
    {
        return $this->audit->transaction(function () use ($data, $actorId): array {
            $tugboatId = $data['tugboat_id'] ?? null;

            if ($tugboatId !== null) {
                $this->assertTugboatFree($tugboatId, null);
            }

            $created = $this->crew->createUser($data, Role::TugMaster, $actorId);

            $master = TugMasterProfile::query()->create(['user_id' => $created['user']->id, 'assigned_tugboat_id' => $tugboatId]);
            $this->audit->recordCreated($master, $actorId);

            return ['tug_master' => $master->refresh(), 'one_time_password' => $created['one_time_password']];
        });
    }

    /**
     * @param  array{name?: string, phone?: string|null}  $data
     */
    public function update(TugMasterProfile $target, array $data, string $version, int $actorId): TugMasterProfile
    {
        return $this->audit->transaction(function () use ($target, $data, $version, $actorId): TugMasterProfile {
            $master = VersionToken::lockAndAssert($target, $version);

            if ($this->crew->updateUser($this->crew->lockUser($master->user_id), $data, $actorId)) {
                $master->touch();
            }

            return $master->refresh();
        });
    }

    /**
     * Link the tug master to a tug, move them from their current tug (which becomes free), or unlink them with null.
     *
     * @throws ValidationException 422 `errors.tug_masters.tug_taken` on `tugboat_id`.
     */
    public function assignTugboat(TugMasterProfile $target, ?int $tugboatId, string $version, int $actorId): TugMasterProfile
    {
        return $this->audit->transaction(function () use ($target, $tugboatId, $version, $actorId): TugMasterProfile {
            $master = VersionToken::lockAndAssert($target, $version);

            if ($master->assigned_tugboat_id === $tugboatId) {
                return $master;
            }

            if ($tugboatId !== null) {
                $this->assertTugboatFree($tugboatId, $master->user_id);
            }

            $master->update(['assigned_tugboat_id' => $tugboatId]);
            $this->audit->recordUpdated($master, $actorId);

            return $master->refresh();
        });
    }

    /**
     * Deactivate also clears the tug link (FR-MD-08) and keeps the schedule rows (Q4); activate checks the plan limit
     * and leaves the tug master unassigned.
     */
    public function setActive(TugMasterProfile $target, bool $active, string $version, int $actorId): TugMasterProfile
    {
        return $this->audit->transaction(function () use ($target, $active, $version, $actorId): TugMasterProfile {
            $master = VersionToken::lockAndAssert($target, $version);
            $userChanged = $this->crew->setActive($this->crew->lockUser($master->user_id), $active, $actorId);

            if (! $active && $master->assigned_tugboat_id !== null) {
                $master->update(['assigned_tugboat_id' => null]);
                $this->audit->recordUpdated($master, $actorId);
            } elseif ($userChanged) {
                $master->touch();
            }

            return $master->refresh();
        });
    }

    /**
     * @throws ValidationException 422 `errors.tug_masters.tug_taken` on `tugboat_id`.
     */
    private function assertTugboatFree(int $tugboatId, ?int $exceptUserId): void
    {
        Tugboat::query()->whereKey($tugboatId)->lockForUpdate()->firstOrFail();

        $taken = TugMasterProfile::query()
            ->where('assigned_tugboat_id', $tugboatId)
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['tugboat_id' => ['errors.tug_masters.tug_taken']]);
        }
    }
}
