<?php

namespace App\Modules\Yard\Models;

use App\Modules\MasterData\Models\Container;
use App\Modules\Yard\Enums\YardSlotStatus;
use Database\Factories\Yard\YardSlotFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `yard_slots` table.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'yard_slots')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['yard_block_id', 'bay', 'row_no', 'tier', 'status', 'container_id'])]
#[UseFactory(YardSlotFactory::class)]
class YardSlot extends Model
{
    /** @use HasFactory<YardSlotFactory> */
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
            'status' => YardSlotStatus::class,
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<YardBlock, $this>
     */
    public function yardBlock(): BelongsTo
    {
        return $this->belongsTo(YardBlock::class, 'yard_block_id');
    }

    /**
     * @return BelongsTo<Container, $this>
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'container_id');
    }

    /**
     * @return HasMany<YardPlacementItem, $this>
     */
    public function yardPlacementItems(): HasMany
    {
        return $this->hasMany(YardPlacementItem::class, 'yard_slot_id');
    }
}
