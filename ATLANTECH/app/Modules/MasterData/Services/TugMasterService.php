<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
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
                $this->claimTugboat($tugboatId, null, $actorId);
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

            if ($this->crew->updateUser($this->crew->lockMember($master->user_id, Role::TugMaster), $data, $actorId)) {
                $master->touch();
            }

            return $master->refresh();
        });
    }

    /**
     * Link the tug master to a tug, move them from their current tug (which becomes free), or unlink them with null. Linking
     * needs the user to still hold `TUG_MASTER` (404 otherwise); unlinking does not, so a stale link of a former tug
     * master can still be cleared.
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
                $this->crew->lockMember($master->user_id, Role::TugMaster);
                $this->claimTugboat($tugboatId, $master->user_id, $actorId);
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
            $userChanged = $this->crew->setActive($this->crew->lockMember($master->user_id, Role::TugMaster), $active, $actorId);

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
     * Make the tug free for a new link. It stays taken only while its holder is an active user who still holds
     * `TUG_MASTER`. The `/users` routes leave the link of a user they deactivate or move to another role, so such a
     * stale link is cleared here, with its own audit row, in the caller's transaction (TUG-LINK-01).
     *
     * Every read is a locking read. A plain read here would fix the transaction's snapshot (REPEATABLE READ) before the
     * plan limit counts active users in `create()`, so two creates at once could both pass the limit.
     *
     * @throws ValidationException 422 `errors.tug_masters.tug_taken` on `tugboat_id`.
     */
    private function claimTugboat(int $tugboatId, ?int $exceptUserId, int $actorId): void
    {
        Tugboat::query()->whereKey($tugboatId)->lockForUpdate()->firstOrFail();

        $holder = TugMasterProfile::query()
            ->where('assigned_tugboat_id', $tugboatId)
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->lockForUpdate()
            ->first();

        if ($holder === null) {
            return;
        }

        $user = User::query()->whereKey($holder->user_id)->lockForUpdate()->firstOrFail();

        if ($user->is_active && $user->activeRoleAssignment()->lockForUpdate()->first()?->role === Role::TugMaster) {
            throw ValidationException::withMessages(['tugboat_id' => ['errors.tug_masters.tug_taken']]);
        }

        $holder->update(['assigned_tugboat_id' => null]);
        $this->audit->recordUpdated($holder, $actorId);
    }
}
