<?php

namespace App\Modules\Execution\Models;

use App\Modules\Execution\Enums\DeviationType;
use App\Modules\Plan\Models\PlanComponent;
use Database\Factories\Execution\DeviationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P19-03 deferred (2026-10-08): kept as in source for now.
 */
#[Table(name: 'deviation_logs', timestamps: false)]
#[Fillable(['plan_component_id', 'status_update_id', 'deviation_type', 'planned_value', 'actual_value', 'delay_minutes', 'deviation_reason', 'detected_at'])]
#[UseFactory(DeviationLogFactory::class)]
class DeviationLog extends Model
{
    /** @use HasFactory<DeviationLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deviation_type' => DeviationType::class,
            'detected_at' => 'datetime',
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
     * @return BelongsTo<ActualStatusUpdate, $this>
     */
    public function statusUpdate(): BelongsTo
    {
        return $this->belongsTo(ActualStatusUpdate::class, 'status_update_id');
    }
}
