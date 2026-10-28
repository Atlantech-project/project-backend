<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\FacilityStatus;
use Database\Factories\MasterData\BerthFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `berths` table.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'berths')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['code', 'name', 'length', 'water_depth', 'depth_datum', 'max_vessel_loa', 'status', 'latitude', 'longitude'])]
#[UseFactory(BerthFactory::class)]
class Berth extends Model
{
    /** @use HasFactory<BerthFactory> */
    use HasFactory;

    /**
     * The table has no `created_at` column.
     *
     * @var string|null
     */
    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'length' => 'decimal:2',
            'water_depth' => 'decimal:2',
            'max_vessel_loa' => 'decimal:2',
            'status' => FacilityStatus::class,
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<BerthTerminal, $this>
     */
    public function berthTerminals(): HasMany
    {
        return $this->hasMany(BerthTerminal::class, 'berth_id');
    }

    /**
     * @return HasMany<SafetyConstraint, $this>
     */
    public function safetyConstraints(): HasMany
    {
        return $this->hasMany(SafetyConstraint::class, 'berth_id');
    }

    /**
     * Through `berth_terminals` (BerthTerminal).
     *
     * @return BelongsToMany<Terminal, $this, BerthTerminal>
     */
    public function terminals(): BelongsToMany
    {
        return $this->belongsToMany(Terminal::class, 'berth_terminals', 'berth_id', 'terminal_id')->using(BerthTerminal::class);
    }
}
