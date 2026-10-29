<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Recommendation\Enums\RecommendationOptionServiceTimeMethod;
use Database\Factories\Recommendation\RecommendationOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * No is_selected column: the selected option is the one operational_plan references (backend reads it, P15-03).
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'recommendation_options', timestamps: false)]
#[Fillable([])]
#[UseFactory(RecommendationOptionFactory::class)]
class RecommendationOption extends Model
{
    /** @use HasFactory<RecommendationOptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_start' => 'datetime',
            'planned_end' => 'datetime',
            'service_time_method' => RecommendationOptionServiceTimeMethod::class,
            'service_time_p50_hours' => 'decimal:2',
            'service_time_p90_hours' => 'decimal:2',
            'delay_risk' => 'decimal:3',
            'objective_score' => 'decimal:4',
            'rationale_params' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Berth, $this>
     */
    public function berth(): BelongsTo
    {
        return $this->belongsTo(Berth::class, 'berth_id');
    }

    /**
     * @return BelongsTo<PilotProfile, $this>
     */
    public function pilotUser(): BelongsTo
    {
        return $this->belongsTo(PilotProfile::class, 'pilot_user_id');
    }

    /**
     * @return BelongsTo<Recommendation, $this>
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class, 'recommendation_id');
    }

    /**
     * @return BelongsTo<RationaleTemplate, $this>
     */
    public function rationaleTemplate(): BelongsTo
    {
        return $this->belongsTo(RationaleTemplate::class, 'rationale_template_id');
    }

    /**
     * @return HasMany<RecommendationOptionTug, $this>
     */
    public function recommendationOptionTugsByOption(): HasMany
    {
        return $this->hasMany(RecommendationOptionTug::class, 'option_id');
    }

    /**
     * @return HasMany<RecommendationOverride, $this>
     */
    public function recommendationOverrides(): HasMany
    {
        return $this->hasMany(RecommendationOverride::class, 'recommendation_option_id');
    }

    /**
     * @return HasMany<RecommendationRationaleFactor, $this>
     */
    public function recommendationRationaleFactorsByOption(): HasMany
    {
        return $this->hasMany(RecommendationRationaleFactor::class, 'option_id');
    }

    /**
     * Through `recommendation_option_tugs` (RecommendationOptionTug).
     *
     * @return BelongsToMany<Tugboat, $this, RecommendationOptionTug>
     */
    public function tugboats(): BelongsToMany
    {
        return $this->belongsToMany(Tugboat::class, 'recommendation_option_tugs', 'option_id', 'tugboat_id')->using(RecommendationOptionTug::class);
    }
}
