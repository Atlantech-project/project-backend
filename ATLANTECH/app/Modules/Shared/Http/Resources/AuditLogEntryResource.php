<?php

namespace App\Modules\Shared\Http\Resources;

use App\Modules\Shared\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One audit row (P2-F8). The actor columns come from the left join in `AuditLogController`; they are NULL for the system or a worker.
 *
 * @mixin AuditLog
 */
class AuditLogEntryResource extends JsonResource
{
    /**
     * @return array{id: int, actor: array{id: int, name: string, username: string}|null, entity_type: string, entity_id: int|null, action: string, before_state: array<string, mixed>|null, after_state: array<string, mixed>|null, occurred_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->actor_id === null ? null : [
                'id' => $this->actor_id,
                'name' => $this->getAttribute('actor_name'),
                'username' => $this->getAttribute('actor_username'),
            ],
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'action' => $this->action->value,
            'before_state' => $this->before_state,
            'after_state' => $this->after_state,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
