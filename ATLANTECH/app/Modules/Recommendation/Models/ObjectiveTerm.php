<?php

namespace App\Modules\Recommendation\Models;

use Database\Factories\Recommendation\ObjectiveTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CP-SAT objective terms (P16-02). Codes agreed with the AI team.
 */
#[Table(name: 'objective_terms', timestamps: false)]
#[Fillable(['code', 'name'])]
#[UseFactory(ObjectiveTermFactory::class)]
class ObjectiveTerm extends Model
{
    /** @use HasFactory<ObjectiveTermFactory> */
    use HasFactory;

    /**
     * @return HasMany<RecommendationRationaleFactor, $this>
     */
    public function recommendationRationaleFactors(): HasMany
    {
        return $this->hasMany(RecommendationRationaleFactor::class, 'objective_term_id');
    }
}
