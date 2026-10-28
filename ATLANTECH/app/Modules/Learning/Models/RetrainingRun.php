<?php

namespace App\Modules\Learning\Models;

use App\Modules\Access\Models\User;
use App\Modules\Learning\Enums\RetrainingRunDecision;
use App\Modules\MasterData\Models\MlModel;
use App\Modules\MasterData\Models\MlModelVersion;
use Database\Factories\Learning\RetrainingRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Champion vs Challenger history. Hard safety limits are never changed by retraining (D12).
 * Generated, never written: `review_pending`.
 */
#[Table(name: 'retraining_runs', timestamps: false)]
#[Fillable(['ml_model_id', 'champion_version_id', 'challenger_version_id', 'started_at', 'finished_at', 'decision', 'decision_reason', 'reviewed_by', 'reviewed_at', 'review_note'])]
#[UseFactory(RetrainingRunFactory::class)]
class RetrainingRun extends Model
{
    /** @use HasFactory<RetrainingRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'decision' => RetrainingRunDecision::class,
            'review_pending' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MlModelVersion, $this>
     */
    public function challengerVersion(): BelongsTo
    {
        return $this->belongsTo(MlModelVersion::class, 'challenger_version_id');
    }

    /**
     * @return BelongsTo<MlModelVersion, $this>
     */
    public function championVersion(): BelongsTo
    {
        return $this->belongsTo(MlModelVersion::class, 'champion_version_id');
    }

    /**
     * @return BelongsTo<MlModel, $this>
     */
    public function mlModel(): BelongsTo
    {
        return $this->belongsTo(MlModel::class, 'ml_model_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<EvaluationMetric, $this>
     */
    public function evaluationMetrics(): HasMany
    {
        return $this->hasMany(EvaluationMetric::class, 'retraining_run_id');
    }
}
