<?php

namespace App\Modules\Environment\Models;

use Database\Factories\Environment\TideConstituentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `tide_constituents` table.
 */
#[Table(name: 'tide_constituents', timestamps: false)]
#[Fillable(['constituent_set_id', 'constituent_code', 'amplitude', 'phase_lag', 'speed_deg_per_hour'])]
#[UseFactory(TideConstituentFactory::class)]
class TideConstituent extends Model
{
    /** @use HasFactory<TideConstituentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amplitude' => 'decimal:4',
            'phase_lag' => 'decimal:3',
            'speed_deg_per_hour' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<TideConstituentSet, $this>
     */
    public function constituentSet(): BelongsTo
    {
        return $this->belongsTo(TideConstituentSet::class, 'constituent_set_id');
    }
}
