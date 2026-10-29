<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\MasterData\Models\Tugboat;
use Database\Factories\Recommendation\RecommendationOptionTugFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Number of tugs decided by the AI formula.
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'recommendation_option_tugs', timestamps: false, incrementing: false)]
#[Fillable([])]
#[UseFactory(RecommendationOptionTugFactory::class)]
class RecommendationOptionTug extends Pivot
{
    /** @use HasFactory<RecommendationOptionTugFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<RecommendationOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(RecommendationOption::class, 'option_id');
    }

    /**
     * @return BelongsTo<Tugboat, $this>
     */
    public function tugboat(): BelongsTo
    {
        return $this->belongsTo(Tugboat::class, 'tugboat_id');
    }
}
