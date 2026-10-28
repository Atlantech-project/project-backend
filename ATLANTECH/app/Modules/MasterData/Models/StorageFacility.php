<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\FacilityStatus;
use App\Modules\Shared\Enums\CapacityUnit;
use App\Modules\Shared\Enums\StorageDetailKind;
use App\Modules\Shared\Enums\TerminalType;
use Database\Factories\MasterData\StorageFacilityFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `storage_facilities` table.
 * Generated, never written: `available_capacity`.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'storage_facilities')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['terminal_id', 'terminal_type', 'storage_type_id', 'detail_kind', 'name', 'code', 'area', 'max_floor_load', 'capacity', 'capacity_unit', 'current_occupancy', 'reserved_capacity', 'blocked_capacity', 'status', 'latitude', 'longitude'])]
#[UseFactory(StorageFacilityFactory::class)]
class StorageFacility extends Model
{
    /** @use HasFactory<StorageFacilityFactory> */
    use HasFactory;

    /**
     * The table has no `created_at` column.
     *
     * @var string|null
     */
    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'terminal_type' => TerminalType::class,
            'detail_kind' => StorageDetailKind::class,
            'area' => 'decimal:2',
            'max_floor_load' => 'decimal:2',
            'capacity' => 'decimal:2',
            'capacity_unit' => CapacityUnit::class,
            'current_occupancy' => 'decimal:2',
            'reserved_capacity' => 'decimal:2',
            'blocked_capacity' => 'decimal:2',
            'available_capacity' => 'decimal:2',
            'status' => FacilityStatus::class,
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StorageType, $this>
     */
    public function storageType(): BelongsTo
    {
        return $this->belongsTo(StorageType::class, 'storage_type_id');
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /**
     * @return HasMany<TankDetail, $this>
     */
    public function tankDetails(): HasMany
    {
        return $this->hasMany(TankDetail::class, 'storage_facility_id');
    }

    /**
     * @return HasMany<WarehouseDetail, $this>
     */
    public function warehouseDetails(): HasMany
    {
        return $this->hasMany(WarehouseDetail::class, 'storage_facility_id');
    }

    /**
     * @return HasMany<StorageFacilityCargoCompatibility, $this>
     */
    public function storageFacilityCargoCompatibilities(): HasMany
    {
        return $this->hasMany(StorageFacilityCargoCompatibility::class, 'storage_facility_id');
    }

    /**
     * Through `storage_facility_cargo_compatibilities` (StorageFacilityCargoCompatibility). Attach with the owner's `terminal_id` (NOT NULL, part of the composite FK).
     *
     * @return BelongsToMany<CargoType, $this, StorageFacilityCargoCompatibility>
     */
    public function cargoTypes(): BelongsToMany
    {
        return $this->belongsToMany(CargoType::class, 'storage_facility_cargo_compatibilities', 'storage_facility_id', 'cargo_type_id')->using(StorageFacilityCargoCompatibility::class)->withPivot('terminal_id');
    }
}
