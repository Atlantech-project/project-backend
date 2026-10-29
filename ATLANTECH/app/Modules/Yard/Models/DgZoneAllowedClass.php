<?php

namespace App\Modules\Yard\Models;

use App\Modules\MasterData\Models\ImdgClass;
use App\Modules\Yard\Enums\ZoneDetail;
use Database\Factories\Yard\DgZoneAllowedClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `dg_zone_allowed_classes` table.
 */
#[Table(name: 'dg_zone_allowed_classes', timestamps: false, incrementing: false)]
#[Fillable(['yard_zone_id', 'zone_detail', 'imdg_class'])]
#[UseFactory(DgZoneAllowedClassFactory::class)]
class DgZoneAllowedClass extends Pivot
{
    /** @use HasFactory<DgZoneAllowedClassFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'zone_detail' => ZoneDetail::class,
        ];
    }

    /**
     * @return BelongsTo<ImdgClass, $this>
     */
    public function imdgClass(): BelongsTo
    {
        return $this->belongsTo(ImdgClass::class, 'imdg_class');
    }

    /**
     * @return BelongsTo<YardZone, $this>
     */
    public function yardZone(): BelongsTo
    {
        return $this->belongsTo(YardZone::class, 'yard_zone_id');
    }
}
