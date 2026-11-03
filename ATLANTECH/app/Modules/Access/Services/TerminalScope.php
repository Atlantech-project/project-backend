<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Enums\AccessRefusalReason;
use App\Modules\Access\Models\User;
use App\Modules\Shared\Errors\ApiProblemException;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Terminal isolation (FR-AC-15, AIBD-024): a `TERMINAL_*` user reaches only their own terminal's records, every other
 * role is port-wide. For ids in a body or query, and for lists. Another terminal's record answers 403, not 404 (lead, Q2).
 */
class TerminalScope
{
    public function __construct(private readonly AccessRefusal $refusal) {}

    /**
     * @throws ApiProblemException 403 FORBIDDEN (`terminal_out_of_scope`, or `no_active_role`).
     */
    public function authorize(Request $request, User $user, int $terminalId): void
    {
        $scope = $user->accessScope();

        if ($scope->role === null) {
            $this->refusal->refuse($request, $user, AccessRefusalReason::NoActiveRole);
        }

        if (! $scope->canReachTerminal($terminalId)) {
            $this->refusal->refuse($request, $user, AccessRefusalReason::TerminalOutOfScope);
        }
    }

    /**
     * Limit a list query to the user's terminals; port-wide roles are not limited. A user with no role sees nothing.
     *
     * @template TBuilder of EloquentBuilder|Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function restrict(EloquentBuilder|Builder $query, string $column, User $user): EloquentBuilder|Builder
    {
        $scope = $user->accessScope();

        if ($scope->role === null) {
            return $query->whereRaw('1 = 0');
        }

        return $scope->isTerminalScoped() ? $query->whereIn($column, $scope->terminalIds) : $query;
    }
}
