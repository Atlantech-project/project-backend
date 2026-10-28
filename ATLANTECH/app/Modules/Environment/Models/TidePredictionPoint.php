<?php

namespace App\Modules\Environment\Models;

use Database\Factories\Environment\TidePredictionPointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `tide_prediction_points` table.
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'tide_prediction_points', timestamps: false)]
#[Fillable([])]
#[UseFactory(TidePredictionPointFactory::class)]
class TidePredictionPoint extends Model
{
    /** @use HasFactory<TidePredictionPointFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_at' => 'datetime',
            'height' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<TidePredictionRun, $this>
     */
    public function tidePredictionRun(): BelongsTo
    {
        return $this->belongsTo(TidePredictionRun::class, 'tide_prediction_run_id');
    }
}
