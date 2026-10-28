<?php

namespace App\Modules\Yard\Models;

use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\MasterData\Models\StorageFacilityCargoCompatibility;
use App\Modules\Shared\Enums\CapacityUnit;
use App\Modules\Yard\Enums\TerminalRecommendationType;
use Database\Factories\Yard\StorageAllocationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * On ACCEPTED, backend adds quantity to storage_facility.reserved_capacity (D-46).
 */
#[Table(name: 'storage_allocation_items', timestamps: false)]
#[Fillable(['terminal_recommendation_id', 'rec_type', 'storage_facility_id', 'cargo_type_id', 'quantity', 'quantity_unit'])]
#[UseFactory(StorageAllocationItemFactory::class)]
class StorageAllocationItem extends Model
{
    /** @use HasFactory<StorageAllocationItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rec_type' => TerminalRecommendationType::class,
            'quantity' => 'decimal:2',
            'quantity_unit' => CapacityUnit::class,
        ];
    }

    /**
     * @return BelongsTo<StorageFacilityCargoCompatibility, $this>
     */
    public function storageFacility(): BelongsTo
    {
        return $this->belongsTo(StorageFacilityCargoCompatibility::class, 'storage_facility_id', 'storage_facility_id');
    }

    /**
     * @return BelongsTo<TerminalRecommendation, $this>
     */
    public function terminalRecommendation(): BelongsTo
    {
        return $this->belongsTo(TerminalRecommendation::class, 'terminal_recommendation_id');
    }

    /**
     * @return BelongsTo<StorageFacility, $this>
     */
    public function storageFacility2(): BelongsTo
    {
        return $this->belongsTo(StorageFacility::class, 'storage_facility_id');
    }
}
