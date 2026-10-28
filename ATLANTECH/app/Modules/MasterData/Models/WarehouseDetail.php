<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Shared\Enums\StorageDetailKind;
use Database\Factories\MasterData\WarehouseDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `warehouse_details` table.
 */
#[Table(name: 'warehouse_details', timestamps: false, key: 'storage_facility_id', incrementing: false, keyType: 'int')]
#[Fillable(['storage_facility_id', 'detail_kind', 'min_temperature', 'max_temperature', 'temperature_monitoring'])]
#[UseFactory(WarehouseDetailFactory::class)]
class WarehouseDetail extends Model
{
    /** @use HasFactory<WarehouseDetailFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'detail_kind' => StorageDetailKind::class,
            'min_temperature' => 'decimal:2',
            'max_temperature' => 'decimal:2',
            'temperature_monitoring' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<StorageFacility, $this>
     */
    public function storageFacility(): BelongsTo
    {
        return $this->belongsTo(StorageFacility::class, 'storage_facility_id');
    }
}
