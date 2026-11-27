<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Models\User;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Validation\ValidationException;

/**
 * A 422 from a business rule, audited as REJECTED_ATTEMPT (FR-GL-02, Coding Notes §6.4). The write is run through
 * `audited()`, so the audit row is written after its transaction has rolled back and is kept.
 */
class RuleRefusal
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @template TReturn
     *
     * @param  \Closure(): TReturn  $write  Runs its own transaction.
     * @param  string  $route  Method and path of the request, for the audit row.
     * @return TReturn
     *
     * @throws ValidationException The write's own refusal, after it is audited; its first message key is the reason.
     */
    public function audited(\Closure $write, User $actor, string $route, string $table, ?int $recordId): mixed
    {
        try {
            return $write();
        } catch (ValidationException $refusal) {
            $key = (string) collect($refusal->errors())->flatten()->first();

            $this->audit->rejectedAttempt($route, ErrorCode::ValidationFailed, str($key)->afterLast('.')->value(), $actor->id, $actor->username, $table, $recordId);

            throw $refusal;
        }
    }
}
