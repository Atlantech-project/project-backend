<?php

namespace App\Modules\Shared\Models;

use App\Modules\Shared\Enums\AuditLogAction;
use Database\Factories\Shared\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
