<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\MasterData\Models\MlModelVersion;
use Database\Factories\Recommendation\SolverRunModelVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `solver_run_model_versions` table.
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'solver_run_model_versions', timestamps: false, incrementing: false)]
#[Fillable([])]
#[UseFactory(SolverRunModelVersionFactory::class)]
class SolverRunModelVersion extends Pivot
{
    /** @use HasFactory<SolverRunModelVersionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<MlModelVersion, $this>
     */
    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(MlModelVersion::class, 'model_version_id');
    }

    /**
     * @return BelongsTo<SolverRun, $this>
     */
    public function solverRun(): BelongsTo
    {
        return $this->belongsTo(SolverRun::class, 'solver_run_id');
    }
}
