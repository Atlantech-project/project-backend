<?php

namespace App\Modules\Shared\Models;

use App\Modules\Shared\Enums\AuditLogAction;
use Database\Factories\Shared\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use LogicException;

/**
 * Append-only: the application DB user gets INSERT + SELECT only on this table (P21-03).
 */
#[Table(name: 'audit_logs', timestamps: false)]
#[Fillable(['actor_id', 'entity_type', 'entity_id', 'action', 'before_state', 'after_state', 'occurred_at'])]
#[UseFactory(AuditLogFactory::class)]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /**
     * Append-only (NFR-SEC-6): a saved row cannot be updated or deleted through the model.
     */
    protected function performUpdate(\Illuminate\Database\Eloquent\Builder $query): never
    {
        throw new LogicException('audit_logs is append-only (NFR-SEC-6): rows are never updated.');
    }

    protected function incrementOrDecrement(...$arguments): never
    {
        throw new LogicException('audit_logs is append-only (NFR-SEC-6): rows are never updated.');
    }

    protected function performDeleteOnModel(): never
    {
        throw new LogicException('audit_logs is append-only (NFR-SEC-6): rows are never deleted.');
    }

    /**
     * @param  Builder  $query
     * @return AuditLogBuilder
     */
    public function newEloquentBuilder($query)
    {
        return new AuditLogBuilder($query);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditLogAction::class,
            'before_state' => 'array',
            'after_state' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
