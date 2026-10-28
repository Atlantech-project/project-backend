<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Shared\Enums\TerminalType;
use Database\Factories\MasterData\TerminalFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The `terminals` table.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'terminals')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['code', 'name', 'terminal_type', 'boundary_coordinates', 'is_active'])]
#[UseFactory(TerminalFactory::class)]
class Terminal extends Model
{
    /** @use HasFactory<TerminalFactory> */
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
            'terminal_type' => TerminalType::class,
            'boundary_coordinates' => 'array',
            'is_active' => 'boolean',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<BerthTerminal, $this>
     */
    public function berthTerminals(): HasMany
    {
        return $this->hasMany(BerthTerminal::class, 'terminal_id');
    }

    /**
     * @return HasMany<SafetyConstraint, $this>
     */
    public function safetyConstraints(): HasMany
    {
        return $this->hasMany(SafetyConstraint::class, 'terminal_id');
    }

    /**
     * @return HasMany<TerminalCargoType, $this>
     */
    public function terminalCargoTypes(): HasMany
    {
        return $this->hasMany(TerminalCargoType::class, 'terminal_id');
    }

    /**
     * @return HasMany<StorageFacility, $this>
     */
    public function storageFacilities(): HasMany
    {
        return $this->hasMany(StorageFacility::class, 'terminal_id');
    }

    /**
     * @return HasMany<Equipment, $this>
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'terminal_id');
    }

    /**
     * @return HasMany<TerminalProductivityProfile, $this>
     */
    public function terminalProductivityProfiles(): HasMany
    {
        return $this->hasMany(TerminalProductivityProfile::class, 'terminal_id');
    }

    /**
     * Through `berth_terminals` (BerthTerminal).
     *
     * @return BelongsToMany<Berth, $this, BerthTerminal>
     */
    public function berths(): BelongsToMany
    {
        return $this->belongsToMany(Berth::class, 'berth_terminals', 'terminal_id', 'berth_id')->using(BerthTerminal::class);
    }

    /**
     * Through `terminal_cargo_types` (TerminalCargoType).
     *
     * @return BelongsToMany<CargoType, $this, TerminalCargoType>
     */
    public function cargoTypes(): BelongsToMany
    {
        return $this->belongsToMany(CargoType::class, 'terminal_cargo_types', 'terminal_id', 'cargo_type_id')->using(TerminalCargoType::class);
    }

    /**
     * The TERMINAL organisation unit created with each terminal (Access keeps `terminal_id` as a plain column).
     *
     * @return HasOne<OrganizationUnit, $this>
     */
    public function organizationUnit(): HasOne
    {
        return $this->hasOne(OrganizationUnit::class, 'terminal_id');
    }
}
