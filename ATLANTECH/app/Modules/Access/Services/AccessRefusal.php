<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Enums\AccessRefusalReason;
use App\Modules\Access\Models\User;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Http\Request;

/**
 * The one place a refused role, terminal or own-task check is audited and answered with 403 FORBIDDEN (FR-GL-02).
 * It writes `REJECTED_ATTEMPT` through the audit service (P2-D3), with the user as actor and entity like the password gate.
 */
class AccessRefusal
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @throws ApiProblemException 403 FORBIDDEN, always.
     */
    public function refuse(Request $request, User $user, AccessRefusalReason $reason): never
    {
        $this->audit->rejectedAttempt(
            $request->method().' /'.$request->path(),
            ErrorCode::Forbidden,
            $reason->value,
            $user->id,
            $user->username,
            'users',
            $user->id,
        );

        throw new ApiProblemException(ErrorCode::Forbidden);
    }

    /**
     * A terminal-scoped user writing inside an inactive terminal (P2-F2 Q8): the terminal is read-only for them.
     *
     * @throws ApiProblemException 409 CONFLICT, always.
     */
    public function refuseInactiveTerminal(Request $request, User $user, int $terminalId): never
    {
        $this->audit->rejectedAttempt(
            $request->method().' /'.$request->path(),
            ErrorCode::Conflict,
            AccessRefusalReason::TerminalInactive->value,
            $user->id,
            $user->username,
            'terminals',
            $terminalId,
        );

        throw ApiProblemException::conflict('errors.terminals.terminal_inactive');
    }
}
