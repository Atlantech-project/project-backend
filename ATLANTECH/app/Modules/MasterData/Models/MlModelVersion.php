<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\MlModelVersionStatus;
use Database\Factories\MasterData\MlModelVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `ml_model_versions` table.
 * Generated, never written: `champion_key`.
 */
#[Table(name: 'ml_model_versions', timestamps: false)]
#[Fillable(['ml_model_id', 'version_no', 'status', 'hyperparameters', 'training_window_start', 'training_window_end', 'training_row_count', 'artifact_path', 'trained_at', 'deployed_at', 'retired_at'])]
#[UseFactory(MlModelVersionFactory::class)]
class MlModelVersion extends Model
{
    /** @use HasFactory<MlModelVersionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MlModelVersionStatus::class,
            'hyperparameters' => 'array',
            'training_window_start' => 'datetime',
            'training_window_end' => 'datetime',
            'trained_at' => 'datetime',
            'deployed_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MlModel, $this>
     */
    public function mlModel(): BelongsTo
    {
        return $this->belongsTo(MlModel::class, 'ml_model_id');
    }
}
