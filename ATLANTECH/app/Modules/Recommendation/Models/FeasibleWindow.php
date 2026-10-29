<?php

namespace App\Modules\Recommendation\Models;

use Database\Factories\Recommendation\FeasibleWindowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `feasible_windows` table.
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'feasible_windows', timestamps: false)]
#[Fillable([])]
#[UseFactory(FeasibleWindowFactory::class)]
class FeasibleWindow extends Model
{
    /** @use HasFactory<FeasibleWindowFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'confidence' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<EntryWindowProposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(EntryWindowProposal::class, 'proposal_id');
    }

    /**
     * @return HasMany<EntryWindowProposal, $this>
     */
    public function entryWindowProposalsByConfirmedWindow(): HasMany
    {
        return $this->hasMany(EntryWindowProposal::class, 'confirmed_window_id');
    }
}
