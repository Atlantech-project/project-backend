<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Enums\Role;

/**
 * What another module must change when a user stops serving in a role (TUG-LINK-01): the account is deactivated, or the
 * role is ended or replaced. Access calls this interface; MasterData implements it, so Access never imports MasterData.
 *
 * It is called inside the transaction of that change, after every refusal check, so the implementation's writes are saved
 * or rolled back with it: it must not queue the work or leave it for after the commit. A new path that deactivates a user
 * or ends a role must call it too.
 */
interface RoleEndHandler
{
    /**
     * @param  Role  $role  The role the user no longer serves in.
     */
    public function roleEnded(int $userId, Role $role, int $actorId): void;
}
