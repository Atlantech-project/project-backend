<?php

namespace App\Modules\Shared\Audit;

use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The one writer of `audit_logs` (FR-GL-02, DEC-02, AIBD-025): a change and its audit row are saved in one transaction, and a refused attempt is saved as
 * REJECTED_ATTEMPT. Only the Backend writes audit rows; workers have no access to the table (DEC-08).
 *
 * The actor is a user id, or NULL for the system or a worker. It has no default, so a caller states it.
 *
 * ```php
 * $audit->transaction(function () use ($audit, $actorId) {
 *     $berth = Berth::query()->create($data);
 *     $audit->recordCreated($berth, $actorId);
 * });
 * ```
 */
class AuditService
{
    /**
     * Run a write and its audit calls in one transaction: if either fails, neither is stored.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $write
     * @return TReturn
     */
    public function transaction(Closure $write): mixed
    {
        return DB::transaction($write);
    }

    /**
     * Append one row. Fails outside a transaction, so a change cannot be saved with its audit row "later".
     *
     * @param  array<string, mixed>|null  $before  Null for CREATE (`chk_al_states`).
     * @param  array<string, mixed>|null  $after  Null for DELETE (`chk_al_states`).
     *
     * @throws LogicException Without an open transaction.
     */
    public function record(AuditLogAction $action, string $entityType, ?int $entityId, ?int $actorId, ?array $before, ?array $after): AuditLog
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('An audit row is written in the same transaction as the change: use AuditService::transaction().');
        }

        return AuditLog::query()->create([
            'actor_id' => $actorId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'before_state' => $before,
            'after_state' => $after,
        ]);
    }

    /**
     * Audit a model that was just created (after `save()` or `create()`).
     */
    public function recordCreated(Model $model, ?int $actorId): AuditLog
    {
        return $this->record(AuditLogAction::Create, $model->getTable(), $model->getKey(), $actorId, null, $this->state($model));
    }

    /**
     * Audit a model that was just updated (after the `save()` that changed it, on the same instance): only the changed fields, before and after. Nothing is written when nothing changed.
     */
    public function recordUpdated(Model $model, ?int $actorId): ?AuditLog
    {
        $changed = array_keys($model->getChanges());

        if ($changed === []) {
            return null;
        }

        $previous = $model->newInstance([], true)->setRawAttributes([...$model->getAttributes(), ...$model->getPrevious()]);

        return $this->record(
            AuditLogAction::Update,
            $model->getTable(),
            $model->getKey(),
            $actorId,
            Arr::only($this->state($previous), $changed),
            Arr::only($this->state($model), $changed),
        );
    }

    /**
     * Audit a model that was just deleted (after `delete()`).
     */
    public function recordDeleted(Model $model, ?int $actorId): AuditLog
    {
        return $this->record(AuditLogAction::Delete, $model->getTable(), $model->getKey(), $actorId, $this->state($model), null);
    }

    /**
     * Audit a refused request (Coding Notes §6.4, answer N6): `after_state` holds the route, the HTTP status and the error code, the reason and the input
     * username, never a password. Before sign-in there is no actor and no entity; a refused write names its target table and row.
     *
     * Runs in its own transaction, so call it after the refused write has been rolled back; a row written inside that transaction would be rolled back with it.
     */
    public function rejectedAttempt(
        string $route,
        ErrorCode $code,
        string $reason,
        ?int $actorId,
        ?string $username,
        string $entityType = 'users',
        ?int $entityId = null,
    ): AuditLog {
        return $this->transaction(fn () => $this->record(AuditLogAction::RejectedAttempt, $entityType, $entityId, $actorId, null, [
            'route' => $route,
            'status' => $code->status(),
            'code' => $code->value,
            'reason' => $reason,
            'username' => $username,
        ]));
    }

    /**
     * The model as stored in a state: attributes in `$hidden` and any password never reach the audit log.
     *
     * @return array<string, mixed>
     */
    private function state(Model $model): array
    {
        return Arr::except($model->attributesToArray(), ['password', 'password_hash']);
    }
}
