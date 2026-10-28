<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\VesselTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `vessel_types` table.
 */
#[Table(name: 'vessel_types', timestamps: false)]
#[Fillable(['code', 'name'])]
#[UseFactory(VesselTypeFactory::class)]
class VesselType extends Model
{
    /** @use HasFactory<VesselTypeFactory> */
    use HasFactory;

    /**
     * @return HasMany<SafetyConstraint, $this>
     */
    public function safetyConstraints(): HasMany
    {
        return $this->hasMany(SafetyConstraint::class, 'vessel_type_id');
    }

    /**
     * @return HasMany<Vessel, $this>
     */
    public function vessels(): HasMany
    {
        return $this->hasMany(Vessel::class, 'vessel_type_id');
    }

    /**
     * @return HasMany<TerminalProductivityProfile, $this>
     */
    public function terminalProductivityProfiles(): HasMany
    {
        return $this->hasMany(TerminalProductivityProfile::class, 'vessel_type_id');
    }
}
