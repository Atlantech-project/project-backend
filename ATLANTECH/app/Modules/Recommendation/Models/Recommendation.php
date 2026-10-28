<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\Berthing\Models\BerthingRequest;
use App\Modules\Recommendation\Enums\RecommendationPurpose;
use App\Modules\Recommendation\Enums\RecommendationSource;
use App\Modules\Recommendation\Enums\RecommendationStatus;
use Database\Factories\Recommendation\RecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `recommendations` table.
 * Rows are inserted by an AI worker; the Backend may change only the fillable columns (CT-15).
 * Generated, never written: `active_key`.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'recommendations')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['status'])]
#[UseFactory(RecommendationFactory::class)]
class Recommendation extends Model
{
    /** @use HasFactory<RecommendationFactory> */
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
            'status' => RecommendationStatus::class,
            'source' => RecommendationSource::class,
            'purpose' => RecommendationPurpose::class,
            'generated_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EntryWindowProposal, $this>
     */
    public function entryWindowProposal(): BelongsTo
    {
        return $this->belongsTo(EntryWindowProposal::class, 'entry_window_proposal_id');
    }

    /**
     * @return BelongsTo<BerthingRequest, $this>
     */
    public function berthingRequest(): BelongsTo
    {
        return $this->belongsTo(BerthingRequest::class, 'berthing_request_id');
    }

    /**
     * @return BelongsTo<SolverRun, $this>
     */
    public function solverRun(): BelongsTo
    {
        return $this->belongsTo(SolverRun::class, 'solver_run_id');
    }

    /**
     * @return HasMany<RecommendationOption, $this>
     */
    public function recommendationOptions(): HasMany
    {
        return $this->hasMany(RecommendationOption::class, 'recommendation_id');
    }

    /**
     * @return HasMany<RecommendationReview, $this>
     */
    public function recommendationReviews(): HasMany
    {
        return $this->hasMany(RecommendationReview::class, 'recommendation_id');
    }
}
