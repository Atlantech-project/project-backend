<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\StorageFacilityCargoCompatibilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `storage_facility_cargo_compatibilities` table.
 */
#[Table(name: 'storage_facility_cargo_compatibilities', timestamps: false, incrementing: false)]
#[Fillable(['storage_facility_id', 'terminal_id', 'cargo_type_id'])]
#[UseFactory(StorageFacilityCargoCompatibilityFactory::class)]
class StorageFacilityCargoCompatibility extends Pivot
{
    /** @use HasFactory<StorageFacilityCargoCompatibilityFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<StorageFacility, $this>
     */
    public function storageFacility(): BelongsTo
    {
        return $this->belongsTo(StorageFacility::class, 'storage_facility_id');
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }
}
