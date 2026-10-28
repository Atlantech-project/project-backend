<?php

namespace App\Modules\Learning\Models;

use App\Modules\Learning\Enums\EvaluationMetricDatasetType;
use App\Modules\Learning\Enums\EvaluationMetricName;
use App\Modules\MasterData\Models\MlModelVersion;
use Database\Factories\Learning\EvaluationMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `evaluation_metrics` table.
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'evaluation_metrics', timestamps: false)]
#[Fillable([])]
#[UseFactory(EvaluationMetricFactory::class)]
class EvaluationMetric extends Model
{
    /** @use HasFactory<EvaluationMetricFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_name' => EvaluationMetricName::class,
            'dataset_type' => EvaluationMetricDatasetType::class,
            'metric_value' => 'decimal:5',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RetrainingRun, $this>
     */
    public function retrainingRun(): BelongsTo
    {
        return $this->belongsTo(RetrainingRun::class, 'retraining_run_id');
    }

    /**
     * @return BelongsTo<MlModelVersion, $this>
     */
    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(MlModelVersion::class, 'model_version_id');
    }
}
