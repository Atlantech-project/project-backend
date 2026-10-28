<?php

namespace App\Modules\Environment\Models;

use Database\Factories\Environment\TideConstituentSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `tide_constituent_sets` table.
 * Generated, never written: `active_key`.
 */
#[Table(name: 'tide_constituent_sets', timestamps: false)]
#[Fillable(['version_label', 'datum_reference', 'valid_from', 'is_active'])]
#[UseFactory(TideConstituentSetFactory::class)]
class TideConstituentSet extends Model
{
    /** @use HasFactory<TideConstituentSetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TideConstituent, $this>
     */
    public function tideConstituentsByConstituentSet(): HasMany
    {
        return $this->hasMany(TideConstituent::class, 'constituent_set_id');
    }

    /**
     * @return HasMany<TidePredictionRun, $this>
     */
    public function tidePredictionRunsByConstituentSet(): HasMany
    {
        return $this->hasMany(TidePredictionRun::class, 'constituent_set_id');
    }
}
