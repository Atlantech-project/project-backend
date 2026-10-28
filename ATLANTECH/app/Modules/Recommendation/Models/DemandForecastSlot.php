<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\MasterData\Models\MlModelVersion;
use App\Modules\Recommendation\Enums\DemandForecastSlotMethod;
use Database\Factories\Recommendation\DemandForecastSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `demand_forecast_slots` table.
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'demand_forecast_slots', timestamps: false)]
#[Fillable([])]
#[UseFactory(DemandForecastSlotFactory::class)]
class DemandForecastSlot extends Model
{
    /** @use HasFactory<DemandForecastSlotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'slot_start' => 'datetime',
            'slot_end' => 'datetime',
            'congestion_score' => 'decimal:3',
            'method' => DemandForecastSlotMethod::class,
        ];
    }

    /**
     * @return BelongsTo<MlModelVersion, $this>
     */
    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(MlModelVersion::class, 'model_version_id');
    }
}
