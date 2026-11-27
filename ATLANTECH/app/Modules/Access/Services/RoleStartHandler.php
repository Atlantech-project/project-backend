<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Enums\Role;

/**
 * What another module must do when a user starts serving in a role again (P2-F7): the account is activated, or the role is
 * given. Access calls this interface; MasterData implements it, so Access never imports MasterData.
 *
 * It is called inside the transaction of that change, after the writes. The user may still be inactive when only the role
 * is given. A new path that activates a user or gives a role to an existing user must call it too.
 */
interface RoleStartHandler
{
    /**
     * @param  Role  $role  The role the user now holds.
     */
    public function roleStarted(int $userId, Role $role, int $actorId): void;
}
