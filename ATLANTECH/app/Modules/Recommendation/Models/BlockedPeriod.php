<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\MasterData\Models\ConstraintType;
use Database\Factories\Recommendation\BlockedPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `blocked_periods` table.
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'blocked_periods', timestamps: false)]
#[Fillable([])]
#[UseFactory(BlockedPeriodFactory::class)]
class BlockedPeriod extends Model
{
    /** @use HasFactory<BlockedPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ConstraintType, $this>
     */
    public function limitingConstraintType(): BelongsTo
    {
        return $this->belongsTo(ConstraintType::class, 'limiting_constraint_type_id');
    }

    /**
     * @return BelongsTo<EntryWindowProposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(EntryWindowProposal::class, 'proposal_id');
    }
}
