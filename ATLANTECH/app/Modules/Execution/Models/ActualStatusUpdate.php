<?php

namespace App\Modules\Execution\Models;

use App\Modules\Access\Models\User;
use App\Modules\Execution\Enums\ActualStatusUpdateSyncSource;
use App\Modules\MasterData\Models\StatusType;
use App\Modules\Plan\Models\PlanComponent;
use App\Modules\Shared\Enums\ComponentType;
use Database\Factories\Execution\ActualStatusUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `actual_status_updates` table.
 */
#[Table(name: 'actual_status_updates', timestamps: false)]
#[Fillable(['client_event_id', 'plan_component_id', 'component_type', 'reported_by', 'status', 'reported_at', 'client_sent_at', 'received_at', 'clock_skew_suspect', 'sync_source'])]
#[UseFactory(ActualStatusUpdateFactory::class)]
class ActualStatusUpdate extends Model
{
    /** @use HasFactory<ActualStatusUpdateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'component_type' => ComponentType::class,
            'reported_at' => 'datetime',
            'client_sent_at' => 'datetime',
            'received_at' => 'datetime',
            'clock_skew_suspect' => 'boolean',
            'sync_source' => ActualStatusUpdateSyncSource::class,
        ];
    }

    /**
     * @return BelongsTo<PlanComponent, $this>
     */
    public function planComponent(): BelongsTo
    {
        return $this->belongsTo(PlanComponent::class, 'plan_component_id');
    }

    /**
     * @return BelongsTo<StatusType, $this>
     */
    public function statusType(): BelongsTo
    {
        return $this->belongsTo(StatusType::class, 'component_type', 'component_type');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return HasMany<DeviationLog, $this>
     */
    public function deviationLogsByStatusUpdate(): HasMany
    {
        return $this->hasMany(DeviationLog::class, 'status_update_id');
    }
}
