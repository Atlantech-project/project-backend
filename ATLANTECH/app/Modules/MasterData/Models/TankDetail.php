<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Shared\Enums\StorageDetailKind;
use Database\Factories\MasterData\TankDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `tank_details` table.
 */
#[Table(name: 'tank_details', timestamps: false, key: 'storage_facility_id', incrementing: false, keyType: 'int')]
#[Fillable(['storage_facility_id', 'detail_kind', 'min_temperature', 'max_temperature', 'max_pressure_bar'])]
#[UseFactory(TankDetailFactory::class)]
class TankDetail extends Model
{
    /** @use HasFactory<TankDetailFactory> */
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
            'max_pressure_bar' => 'decimal:2',
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
