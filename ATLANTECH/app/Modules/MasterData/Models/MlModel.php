<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\MlModelKey;
use Database\Factories\MasterData\MlModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `ml_models` table.
 */
#[Table(name: 'ml_models', timestamps: false)]
#[Fillable(['model_key', 'algorithm_family', 'description'])]
#[UseFactory(MlModelFactory::class)]
class MlModel extends Model
{
    /** @use HasFactory<MlModelFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'model_key' => MlModelKey::class,
        ];
    }

    /**
     * @return HasMany<MlModelVersion, $this>
     */
    public function mlModelVersions(): HasMany
    {
        return $this->hasMany(MlModelVersion::class, 'ml_model_id');
    }
}
