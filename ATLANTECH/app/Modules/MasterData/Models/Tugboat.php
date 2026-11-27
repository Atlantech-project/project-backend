<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\TugboatStatus;
use App\Modules\MasterData\Enums\TugPowerUnit;
use Database\Factories\MasterData\TugboatFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Port tug fleet (shared Marine Services resource).
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'tugboats')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['name', 'tug_power', 'tug_power_unit', 'capacity', 'status'])]
#[UseFactory(TugboatFactory::class)]
class Tugboat extends Model
{
    /** @use HasFactory<TugboatFactory> */
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
            'tug_power' => 'decimal:2',
            'tug_power_unit' => TugPowerUnit::class,
            'capacity' => 'decimal:2',
            'status' => TugboatStatus::class,
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<TugMasterProfile, $this>
     */
    public function tugMasterProfilesByAssignedTugboat(): HasMany
    {
        return $this->hasMany(TugMasterProfile::class, 'assigned_tugboat_id');
    }

    /**
     * The tug's one tug master (`tug_master_profiles.assigned_tugboat_id` is UNIQUE, FR-MD-08), or null.
     *
     * @return HasOne<TugMasterProfile, $this>
     */
    public function tugMaster(): HasOne
    {
        return $this->hasOne(TugMasterProfile::class, 'assigned_tugboat_id');
    }
}
