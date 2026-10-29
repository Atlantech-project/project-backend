<?php

namespace App\Modules\Yard\Models;

use App\Modules\Yard\Enums\ZoneDetail;
use Database\Factories\Yard\YardZoneTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `yard_zone_types` table.
 */
#[Table(name: 'yard_zone_types', timestamps: false)]
#[Fillable(['code', 'name', 'zone_detail'])]
#[UseFactory(YardZoneTypeFactory::class)]
class YardZoneType extends Model
{
    /** @use HasFactory<YardZoneTypeFactory> */
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
     * @return HasMany<YardZone, $this>
     */
    public function yardZones(): HasMany
    {
        return $this->hasMany(YardZone::class, 'yard_zone_type_id');
    }
}
