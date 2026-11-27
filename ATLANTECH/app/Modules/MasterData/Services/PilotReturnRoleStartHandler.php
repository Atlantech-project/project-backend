<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Access\Services\RoleStartHandler;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\Shared\Enums\ResourceKind;
use App\Modules\Shared\Events\ResourceChanged;

/**
 * P2-F7 on the `/users` routes: a pilot who returns keeps their schedules, so becomes available again and is announced.
 * Only an active user with a pilot profile counts. A returning tug master has no tug link, so nothing is announced.
 */
class PilotReturnRoleStartHandler implements RoleStartHandler
{
    public function roleStarted(int $userId, Role $role, int $actorId): void
    {
        if ($role !== Role::MarinePilot) {
            return;
        }

        if (! PilotProfile::query()->whereKey($userId)->exists() || ! User::query()->whereKey($userId)->where('is_active', true)->exists()) {
            return;
        }

        ResourceChanged::dispatch(ResourceKind::Pilot, $userId, $actorId);
    }
}
