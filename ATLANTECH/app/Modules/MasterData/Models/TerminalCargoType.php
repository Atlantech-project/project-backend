<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\TerminalCargoTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `terminal_cargo_types` table.
 */
#[Table(name: 'terminal_cargo_types', timestamps: false, incrementing: false)]
#[Fillable(['terminal_id', 'cargo_type_id'])]
#[UseFactory(TerminalCargoTypeFactory::class)]
class TerminalCargoType extends Pivot
{
    /** @use HasFactory<TerminalCargoTypeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CargoType, $this>
     */
    public function cargoType(): BelongsTo
    {
        return $this->belongsTo(CargoType::class, 'cargo_type_id');
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /**
     * @return HasMany<StorageFacilityCargoCompatibility, $this>
     */
    public function storageFacilityCargoCompatibilitiesByTerminal(): HasMany
    {
        return $this->hasMany(StorageFacilityCargoCompatibility::class, 'terminal_id', 'terminal_id');
    }

    /**
     * @return HasMany<EquipmentCargoCompatibility, $this>
     */
    public function equipmentCargoCompatibilitiesByTerminal(): HasMany
    {
        return $this->hasMany(EquipmentCargoCompatibility::class, 'terminal_id', 'terminal_id');
    }
}
