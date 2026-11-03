<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Enums\AccessRefusalReason;
use App\Modules\Access\Models\User;
use App\Modules\Access\Services\AccessRefusal;
use App\Modules\Access\Services\TerminalScope;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `->middleware('terminal.scope')`: a `TERMINAL_*` user whose route `{terminal}` is not their terminal is refused (403).
 * Port-wide roles pass. Runs after `ability:` (FR-GL-01). Ids in a body or query go through `TerminalScope::authorize`.
 */
class EnsureTerminalScope
{
    public function __construct(
        private readonly TerminalScope $terminalScope,
        private readonly AccessRefusal $refusal,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws ApiProblemException 401 UNAUTHENTICATED for a guest, 403 FORBIDDEN out of scope.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new ApiProblemException(ErrorCode::Unauthenticated);
        }

        $parameter = $request->route('terminal');

        if ($parameter === null) {
            return $next($request);
        }

        $terminalId = is_object($parameter) && method_exists($parameter, 'getKey') ? $parameter->getKey() : $parameter;

        if (! is_int($terminalId) && ! (is_string($terminalId) && ctype_digit($terminalId))) {
            if ($user->accessScope()->isTerminalScoped()) {
                $this->refusal->refuse($request, $user, AccessRefusalReason::TerminalOutOfScope);
            }

            return $next($request);
        }

        $this->terminalScope->authorize($request, $user, (int) $terminalId);

        return $next($request);
    }
}
