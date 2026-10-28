<?php

namespace App\Modules\Yard\Models;

use App\Modules\Yard\Enums\ZoneDetail;
use Database\Factories\Yard\ReeferZoneDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `reefer_zone_details` table.
 */
#[Table(name: 'reefer_zone_details', timestamps: false, key: 'yard_zone_id', incrementing: false, keyType: 'int')]
#[Fillable(['yard_zone_id', 'zone_detail', 'total_power_plugs', 'power_type'])]
#[UseFactory(ReeferZoneDetailFactory::class)]
class ReeferZoneDetail extends Model
{
    /** @use HasFactory<ReeferZoneDetailFactory> */
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
     * @return BelongsTo<YardZone, $this>
     */
    public function yardZone(): BelongsTo
    {
        return $this->belongsTo(YardZone::class, 'yard_zone_id');
    }
}
