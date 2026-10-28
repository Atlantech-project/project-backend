<?php

namespace App\Modules\Learning\Models;

use App\Modules\Learning\Enums\DriftMetricType;
use App\Modules\MasterData\Models\MlModelVersion;
use Database\Factories\Learning\DriftMeasurementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `drift_measurements` table.
 * Rows are inserted by an AI worker; the Backend may change only the fillable columns (CT-15).
 * Generated, never written: `is_drifted`.
 */
#[Table(name: 'drift_measurements', timestamps: false)]
#[Fillable(['notified_at'])]
#[UseFactory(DriftMeasurementFactory::class)]
class DriftMeasurement extends Model
{
    /** @use HasFactory<DriftMeasurementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_type' => DriftMetricType::class,
            'metric_value' => 'decimal:5',
            'threshold' => 'decimal:5',
            'is_drifted' => 'boolean',
            'measured_at' => 'datetime',
            'notified_at' => 'datetime',
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
