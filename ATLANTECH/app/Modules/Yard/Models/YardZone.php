<?php

namespace App\Modules\Yard\Models;

use App\Modules\MasterData\Models\ImdgClass;
use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\Shared\Enums\StorageDetailKind;
use App\Modules\Yard\Enums\ZoneDetail;
use Database\Factories\Yard\YardZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Capacity/occupancy are derived from yard_slot.
 */
#[Table(name: 'yard_zones', timestamps: false)]
#[Fillable(['container_yard_id', 'detail_kind', 'yard_zone_type_id', 'zone_detail', 'code'])]
#[UseFactory(YardZoneFactory::class)]
class YardZone extends Model
{
    /** @use HasFactory<YardZoneFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'detail_kind' => StorageDetailKind::class,
            'zone_detail' => ZoneDetail::class,
        ];
    }

    /**
     * @return BelongsTo<YardZoneType, $this>
     */
    public function yardZoneType(): BelongsTo
    {
        return $this->belongsTo(YardZoneType::class, 'yard_zone_type_id');
    }

    /**
     * @return BelongsTo<StorageFacility, $this>
     */
    public function containerYard(): BelongsTo
    {
        return $this->belongsTo(StorageFacility::class, 'container_yard_id');
    }

    /**
     * @return HasMany<DgZoneAllowedClass, $this>
     */
    public function dgZoneAllowedClasses(): HasMany
    {
        return $this->hasMany(DgZoneAllowedClass::class, 'yard_zone_id');
    }

    /**
     * @return HasMany<OogZoneDetail, $this>
     */
    public function oogZoneDetails(): HasMany
    {
        return $this->hasMany(OogZoneDetail::class, 'yard_zone_id');
    }

    /**
     * @return HasMany<ReeferZoneDetail, $this>
     */
    public function reeferZoneDetails(): HasMany
    {
        return $this->hasMany(ReeferZoneDetail::class, 'yard_zone_id');
    }

    /**
     * @return HasMany<YardBlock, $this>
     */
    public function yardBlocks(): HasMany
    {
        return $this->hasMany(YardBlock::class, 'yard_zone_id');
    }

    /**
     * Through `dg_zone_allowed_classes` (DgZoneAllowedClass).
     *
     * @return BelongsToMany<ImdgClass, $this, DgZoneAllowedClass>
     */
    public function imdgClasses(): BelongsToMany
    {
        return $this->belongsToMany(ImdgClass::class, 'dg_zone_allowed_classes', 'yard_zone_id', 'imdg_class', 'id', 'code')->using(DgZoneAllowedClass::class);
    }
}
