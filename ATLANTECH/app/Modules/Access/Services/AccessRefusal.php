<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Enums\AccessRefusalReason;
use App\Modules\Access\Models\User;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Http\Request;

/**
 * The one place a refused role, terminal or own-task check is audited and answered with 403 FORBIDDEN (FR-GL-02).
 * It writes through `SignInService::recordRejectedAttempt`; P2-D3 replaces that call with the audit service here.
 */
class AccessRefusal
{
    public function __construct(private readonly SignInService $signIn) {}

    /**
     * @throws ApiProblemException 403 FORBIDDEN, always.
     */
    public function refuse(Request $request, User $user, AccessRefusalReason $reason): never
    {
        $this->signIn->recordRejectedAttempt(
            $request->method().' /'.$request->path(),
            ErrorCode::Forbidden,
            $reason->value,
            $user->username,
            $user,
        );

        throw new ApiProblemException(ErrorCode::Forbidden);
    }
}
