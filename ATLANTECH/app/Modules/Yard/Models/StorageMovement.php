<?php

namespace App\Modules\Yard\Models;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\Plan\Models\PlanComponent;
use App\Modules\Yard\Enums\StorageMovementDirection;
use App\Modules\Yard\Enums\StorageMovementSource;
use Database\Factories\Yard\StorageMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `storage_movements` table.
 */
#[Table(name: 'storage_movements', timestamps: false)]
#[Fillable(['storage_facility_id', 'cargo_type_id', 'direction', 'quantity', 'source', 'plan_component_id', 'occurred_at', 'recorded_by'])]
#[UseFactory(StorageMovementFactory::class)]
class StorageMovement extends Model
{
    /** @use HasFactory<StorageMovementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => StorageMovementDirection::class,
            'quantity' => 'decimal:2',
            'source' => StorageMovementSource::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StorageFacility, $this>
     */
    public function storageFacility(): BelongsTo
    {
        return $this->belongsTo(StorageFacility::class, 'storage_facility_id');
    }

    /**
     * @return BelongsTo<PlanComponent, $this>
     */
    public function planComponent(): BelongsTo
    {
        return $this->belongsTo(PlanComponent::class, 'plan_component_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
