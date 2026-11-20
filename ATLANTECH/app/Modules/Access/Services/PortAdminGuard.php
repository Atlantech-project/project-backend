<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The "at least one active Port Admin must remain" rule (FR-AC-12), shared by user deactivation (P2-E1) and role changes (P2-E2).
 * Both lock the same rows in the same order, so two admins acting on each other at once are serialised.
 */
class PortAdminGuard
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Lock the target and every user who holds an active PORT_GM role, in id order. Call it first in the transaction.
     *
     * @return Collection<int, User> The locked rows, keyed by id.
     */
    public function lockAdminSet(User $target): Collection
    {
        $ids = $this->activeAdminAssignments()->pluck('user_id')->push($target->id)->unique();

        return User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * True when the target is an active Port Admin and no other locked user is one, so removing them would leave nobody.
     *
     * @param  Collection<int, User>  $lockedUsers  The result of lockAdminSet().
     */
    public function isLastActiveAdmin(Collection $lockedUsers, User $target): bool
    {
        $adminIds = $this->activeAdminAssignments()->lockForUpdate()->pluck('user_id');

        if (! $adminIds->contains($target->id) || ! $lockedUsers->get($target->id)?->is_active) {
            return false;
        }

        return $lockedUsers->only($adminIds->all())->except($target->id)->where('is_active', true)->isEmpty();
    }

    /**
     * Audit the refused attempt after the transaction ended, then answer 409 CONFLICT.
     *
     * @param  string  $reason  `cannot_deactivate_self` or `last_port_admin`; also the last part of the message key.
     *
     * @throws ApiProblemException 409 CONFLICT.
     */
    public function refuse(string $reason, User $actor, User $target, string $route): never
    {
        $this->audit->rejectedAttempt($route, ErrorCode::Conflict, $reason, $actor->id, $actor->username, 'users', $target->id);

        throw ApiProblemException::conflict('errors.users.'.$reason);
    }

    /**
     * @return Builder<UserRoleAssignment>
     */
    private function activeAdminAssignments(): Builder
    {
        return UserRoleAssignment::query()->where('role', Role::PortGm)->whereNull('active_to');
    }
}
