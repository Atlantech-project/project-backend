<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\MlModelVersion;
use App\Modules\Recommendation\Enums\SolverRunStatus;
use App\Modules\Recommendation\Enums\SolverRunTriggerType;
use Database\Factories\Recommendation\SolverRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Port-wide joint solve (FR-1.4.1). INFEASIBLE is final; FIFO fallback only on technical failure (recommendation.source = FIFO).
 * Columns written by an AI worker are not fillable (CT-15): `status`, `objective_value`, `solve_time_ms`, `solver_version`, `result_detail`, `started_at`, `finished_at`.
 */
#[Table(name: 'solver_runs', timestamps: false)]
#[Fillable(['trigger_type', 'triggered_by', 'horizon_start', 'horizon_end', 'queued_at', 'result_handled_at'])]
#[UseFactory(SolverRunFactory::class)]
class SolverRun extends Model
{
    /** @use HasFactory<SolverRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger_type' => SolverRunTriggerType::class,
            'horizon_start' => 'datetime',
            'horizon_end' => 'datetime',
            'status' => SolverRunStatus::class,
            'objective_value' => 'decimal:4',
            'result_detail' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'result_handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * @return HasMany<SolverRunModelVersion, $this>
     */
    public function solverRunModelVersions(): HasMany
    {
        return $this->hasMany(SolverRunModelVersion::class, 'solver_run_id');
    }

    /**
     * @return HasMany<Recommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'solver_run_id');
    }

    /**
     * Through `solver_run_model_versions` (SolverRunModelVersion).
     *
     * @return BelongsToMany<MlModelVersion, $this, SolverRunModelVersion>
     */
    public function modelVersions(): BelongsToMany
    {
        return $this->belongsToMany(MlModelVersion::class, 'solver_run_model_versions', 'solver_run_id', 'model_version_id')->using(SolverRunModelVersion::class);
    }
}
