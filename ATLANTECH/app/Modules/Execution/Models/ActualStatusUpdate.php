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
use LogicException;

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
     * The status type for this component (`fk_asu_status` is on (`component_type`, `status`)): matched on the code
     * and constrained to this row's current component type, so it lazy-loads the one right row.
     * Eager loading cannot carry the per-row component type, so it is refused instead of returning nothing; the same
     * applies to has() / whereHas() and to a select() without `component_type`. Query status types from StatusType instead.
     *
     * @return BelongsTo<StatusType, $this>
     *
     * @throws LogicException when called without a component type (eager loading)
     */
    public function statusType(): BelongsTo
    {
        $componentType = $this->getAttributes()['component_type'] ?? null;

        if ($componentType === null) {
            throw new LogicException('statusType() needs the row\'s component_type: load it per row, not with with() or load().');
        }

        return $this->belongsTo(StatusType::class, 'status', 'code')
            ->where('status_types.component_type', $componentType);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
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
