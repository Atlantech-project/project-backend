<?php

namespace App\Modules\Dispatch\Models;

use App\Modules\Access\Models\User;
use App\Modules\Dispatch\Enums\NotificationLogChannel;
use App\Modules\Dispatch\Enums\NotificationLogRelatedEntityType;
use App\Modules\Dispatch\Enums\NotificationLogStatus;
use App\Modules\Dispatch\Enums\NotificationLogType;
use Database\Factories\Dispatch\NotificationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Each retry, escalation and push attempt is its own row.
 */
#[Table(name: 'notification_logs', timestamps: false)]
#[Fillable(['user_id', 'related_entity_type', 'related_entity_id', 'type', 'channel', 'status', 'failure_reason', 'sent_at', 'acknowledged_at'])]
#[UseFactory(NotificationLogFactory::class)]
class NotificationLog extends Model
{
    /** @use HasFactory<NotificationLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'related_entity_type' => NotificationLogRelatedEntityType::class,
            'type' => NotificationLogType::class,
            'channel' => NotificationLogChannel::class,
            'status' => NotificationLogStatus::class,
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
