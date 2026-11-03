<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Enums\Ability;
use App\Modules\Access\Enums\AccessRefusalReason;
use App\Modules\Access\Models\User;
use App\Modules\Access\Services\AccessRefusal;
use App\Modules\Access\Services\RolePermissions;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `->middleware('ability:users.manage')`: the caller's one active role must hold the ability (FR-GL-01, P2-D1).
 * Runs after `auth` and the password gate (`EnsureAccountUsable`) and before `terminal.scope`.
 */
class EnsureAbility
{
    public function __construct(private readonly AccessRefusal $refusal) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws ApiProblemException 401 UNAUTHENTICATED for a guest, 403 FORBIDDEN without an active role or the ability.
     * @throws \ValueError When a route names an ability that does not exist.
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new ApiProblemException(ErrorCode::Unauthenticated);
        }

        $role = $user->accessScope()->role;

        if ($role === null) {
            $this->refusal->refuse($request, $user, AccessRefusalReason::NoActiveRole);
        }

        if (! RolePermissions::allows($role, Ability::from($ability))) {
            $this->refusal->refuse($request, $user, AccessRefusalReason::RoleNotAllowed);
        }

        return $next($request);
    }
}
