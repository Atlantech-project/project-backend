<?php

namespace App\Modules\Yard\Models;

use App\Modules\Baplie\Models\ContainerManifestEntry;
use App\Modules\Yard\Enums\TerminalRecommendationType;
use Database\Factories\Yard\YardPlacementItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `yard_placement_items` table.
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'yard_placement_items', timestamps: false)]
#[Fillable([])]
#[UseFactory(YardPlacementItemFactory::class)]
class YardPlacementItem extends Model
{
    /** @use HasFactory<YardPlacementItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rec_type' => TerminalRecommendationType::class,
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
     * @return BelongsTo<ContainerManifestEntry, $this>
     */
    public function manifestEntry(): BelongsTo
    {
        return $this->belongsTo(ContainerManifestEntry::class, 'manifest_entry_id');
    }

    /**
     * @return BelongsTo<YardSlot, $this>
     */
    public function yardSlot(): BelongsTo
    {
        return $this->belongsTo(YardSlot::class, 'yard_slot_id');
    }

    /**
     * @return BelongsTo<TerminalRecommendation, $this>
     */
    public function terminalRecommendation(): BelongsTo
    {
        return $this->belongsTo(TerminalRecommendation::class, 'terminal_recommendation_id');
    }
}
