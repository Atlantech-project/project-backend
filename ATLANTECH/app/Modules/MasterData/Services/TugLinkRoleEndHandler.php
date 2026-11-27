<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Services\RoleEndHandler;
use App\Modules\MasterData\Models\TugMasterProfile;
use App\Modules\Shared\Audit\AuditService;

/**
 * FR-MD-08 on the `/users` routes (TUG-LINK-01): when the Port Admin deactivates a tug master, or ends or replaces the
 * role, the tug link is cleared in the same transaction, with its own audit row. The schedule rows are kept (P2-F3 Q4).
 * Other roles touch nothing: a locking read of a missing profile would lock a gap that new profiles are inserted into.
 */
class TugLinkRoleEndHandler implements RoleEndHandler
{
    public function __construct(private readonly AuditService $audit) {}

    public function roleEnded(int $userId, Role $role, int $actorId): void
    {
        if ($role !== Role::TugMaster) {
            return;
        }

        $master = TugMasterProfile::query()->whereKey($userId)->lockForUpdate()->first();

        if ($master === null || $master->assigned_tugboat_id === null) {
            return;
        }

        $master->update(['assigned_tugboat_id' => null]);
        $this->audit->recordUpdated($master, $actorId);
    }
}
