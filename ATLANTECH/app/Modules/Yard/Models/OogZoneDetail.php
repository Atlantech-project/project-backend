<?php

namespace App\Modules\Yard\Models;

use App\Modules\Yard\Enums\ZoneDetail;
use Database\Factories\Yard\OogZoneDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `oog_zone_details` table.
 */
#[Table(name: 'oog_zone_details', timestamps: false, key: 'yard_zone_id', incrementing: false, keyType: 'int')]
#[Fillable(['yard_zone_id', 'zone_detail', 'max_length', 'max_width', 'max_height', 'max_weight'])]
#[UseFactory(OogZoneDetailFactory::class)]
class OogZoneDetail extends Model
{
    /** @use HasFactory<OogZoneDetailFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'zone_detail' => ZoneDetail::class,
            'max_length' => 'decimal:2',
            'max_width' => 'decimal:2',
            'max_height' => 'decimal:2',
            'max_weight' => 'decimal:2',
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
