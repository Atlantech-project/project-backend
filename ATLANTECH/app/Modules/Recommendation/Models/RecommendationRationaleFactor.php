<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\MasterData\Models\ConstraintType;
use App\Modules\Recommendation\Enums\RationaleFactorType;
use Database\Factories\Recommendation\RecommendationRationaleFactorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constraints/objective terms that actually drove the solve (FR-1.4.10).
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'recommendation_rationale_factors', timestamps: false)]
#[Fillable([])]
#[UseFactory(RecommendationRationaleFactorFactory::class)]
class RecommendationRationaleFactor extends Model
{
    /** @use HasFactory<RecommendationRationaleFactorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor_type' => RationaleFactorType::class,
            'factor_value' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<ConstraintType, $this>
     */
    public function constraintType(): BelongsTo
    {
        return $this->belongsTo(ConstraintType::class, 'constraint_type_id');
    }

    /**
     * @return BelongsTo<ObjectiveTerm, $this>
     */
    public function objectiveTerm(): BelongsTo
    {
        return $this->belongsTo(ObjectiveTerm::class, 'objective_term_id');
    }

    /**
     * @return BelongsTo<RecommendationOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(RecommendationOption::class, 'option_id');
    }
}
