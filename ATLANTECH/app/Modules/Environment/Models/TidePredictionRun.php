<?php

namespace App\Modules\Environment\Models;

use Database\Factories\Environment\TidePredictionRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Daily in-house harmonic computation (D7, D10).
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'tide_prediction_runs', timestamps: false)]
#[Fillable([])]
#[UseFactory(TidePredictionRunFactory::class)]
class TidePredictionRun extends Model
{
    /** @use HasFactory<TidePredictionRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'computed_at' => 'datetime',
            'horizon_start' => 'datetime',
            'horizon_end' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TideConstituentSet, $this>
     */
    public function constituentSet(): BelongsTo
    {
        return $this->belongsTo(TideConstituentSet::class, 'constituent_set_id');
    }

    /**
     * @return HasMany<TidePredictionPoint, $this>
     */
    public function tidePredictionPoints(): HasMany
    {
        return $this->hasMany(TidePredictionPoint::class, 'tide_prediction_run_id');
    }
}
