<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\Access\Models\User;
use Database\Factories\Recommendation\RecommendationReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `recommendation_reviews` table.
 */
#[Table(name: 'recommendation_reviews', timestamps: false, incrementing: false)]
#[Fillable(['recommendation_id', 'user_id', 'viewed_at'])]
#[UseFactory(RecommendationReviewFactory::class)]
class RecommendationReview extends Pivot
{
    /** @use HasFactory<RecommendationReviewFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Recommendation, $this>
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class, 'recommendation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
