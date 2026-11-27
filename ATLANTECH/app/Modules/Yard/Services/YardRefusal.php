<?php

namespace App\Modules\Yard\Services;

use App\Modules\Access\Models\User;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Database\Eloquent\Model;

/**
 * A yard business rule refused a write: audit it as REJECTED_ATTEMPT (outside the rolled-back transaction, FR-GL-02) and
 * answer 409 CONFLICT with the message of the rule.
 */
class YardRefusal
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  string  $reason  The key under errors.yard.
     * @param  string  $route  Method and path of the request.
     *
     * @throws ApiProblemException 409 CONFLICT.
     */
    public function refuse(string $reason, Model $record, User $actor, string $route): never
    {
        $this->audit->rejectedAttempt($route, ErrorCode::Conflict, $reason, $actor->id, $actor->username, $record->getTable(), $record->getKey());

        throw ApiProblemException::conflict('errors.yard.'.$reason);
    }
}
