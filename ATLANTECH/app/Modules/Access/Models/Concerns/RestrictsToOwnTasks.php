<?php

namespace App\Modules\Access\Models\Concerns;

use App\Modules\Access\Enums\AccessRefusalReason;
use App\Modules\Access\Models\User;
use App\Modules\Access\Services\AccessRefusal;
use App\Modules\Shared\Errors\ApiProblemException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * "My tasks" for a model with `assigned_user_id` and `terminal_id` (`PlanComponent`; P2-D1, extended by P5-O1).
 * A field user (pilot, tug master, terminal supervisor) reaches only the rows assigned to them, a supervisor only on their own
 * terminal. Other roles are not restricted here; their access is the `ability:` and terminal checks. A local scope on purpose:
 * workers, jobs and the dispatcher must still read every row. Another user's row answers 403, not 404 (lead, Q2).
 */
trait RestrictsToOwnTasks
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = $user->accessScope();

        if ($scope->role === null) {
            return $query->whereRaw('1 = 0');
        }

        if (! $scope->role->isFieldRole()) {
            return $query;
        }

        $query->where($query->qualifyColumn('assigned_user_id'), $user->id);

        return $scope->isTerminalScoped()
            ? $query->whereIn($query->qualifyColumn('terminal_id'), $scope->terminalIds)
            : $query;
    }

    /**
     * @throws ApiProblemException 403 FORBIDDEN (`not_own_task`, `terminal_out_of_scope` or `no_active_role`).
     */
    public function authorizeTask(Request $request, User $user): void
    {
        $scope = $user->accessScope();
        $refusal = app(AccessRefusal::class);

        if ($scope->role === null) {
            $refusal->refuse($request, $user, AccessRefusalReason::NoActiveRole);
        }

        if (! $scope->role->isFieldRole()) {
            return;
        }

        if ((int) $this->getAttribute('assigned_user_id') !== $user->id) {
            $refusal->refuse($request, $user, AccessRefusalReason::NotOwnTask);
        }

        if ($scope->isTerminalScoped() && ! in_array((int) $this->getAttribute('terminal_id'), $scope->terminalIds, true)) {
            $refusal->refuse($request, $user, AccessRefusalReason::TerminalOutOfScope);
        }
    }
}
