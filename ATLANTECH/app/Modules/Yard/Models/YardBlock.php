<?php

namespace App\Modules\Yard\Models;

use Database\Factories\Yard\YardBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `yard_blocks` table.
 */
#[Table(name: 'yard_blocks', timestamps: false)]
#[Fillable(['yard_zone_id', 'code'])]
#[UseFactory(YardBlockFactory::class)]
class YardBlock extends Model
{
    /** @use HasFactory<YardBlockFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<YardZone, $this>
     */
    public function yardZone(): BelongsTo
    {
        return $this->belongsTo(YardZone::class, 'yard_zone_id');
    }

    /**
     * @return HasMany<YardSlot, $this>
     */
    public function yardSlots(): HasMany
    {
        return $this->hasMany(YardSlot::class, 'yard_block_id');
    }

    /**
     * @return HasMany<YardPlacementItem, $this>
     */
    public function yardPlacementItems(): HasMany
    {
        return $this->hasMany(YardPlacementItem::class, 'yard_block_id');
    }
}
